<?php

/*
 *
 *  ____           _            __           _____
 * |  _ \    ___  (_)  _ __    / _|  _   _  |_   _|   ___    __ _   _ __ ___
 * | |_) |  / _ \ | | | '_ \  | |_  | | | |   | |    / _ \  / _` | | '_ ` _ \
 * |  _ <  |  __/ | | | | | | |  _| | |_| |   | |   |  __/ | (_| | | | | | | |
 * |_| \_\  \___| |_| |_| |_| |_|    \__, |   |_|    \___|  \__,_| |_| |_| |_|
 *                                   |___/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Zuri attempts to enforce "vanilla Minecraft" mechanics, as well as preventing
 * players from abusing weaknesses in Minecraft or its protocol, making your server
 * more safe. Organized in different sections, various checks are performed to test
 * players doing, covering a wide range including flying and speeding, fighting
 * hacks, fast block breaking and nukers, inventory hacks, chat spam and other types
 * of malicious behaviour.
 *
 * @author ReinfyTeam
 * @link https://github.com/ReinfyTeam/
 *
 *
 */

declare(strict_types=1);

namespace ReinfyTeam\Zuri\task;

use pocketmine\Server;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use Throwable;
use vennv\vapm\ClosureThread;
use function array_shift;
use function count;
use function is_array;
use function is_string;
use function json_decode;
use function max;
use function method_exists;
use function microtime;

class CheckAsyncTask {
	/** @var list<array{0:string,1:string,2:array,3:int}> */
	private static array $queue = [];
	private static int $inFlight = 0;
	private static int $maxConcurrentWorkers = 4;
	private static int $maxQueueSize = 2048;

	private static int $totalDispatched = 0;
	private static int $totalCompleted = 0;
	private static int $totalDropped = 0;
	private static float $lastDispatchAt = 0.0;
	private static float $lastCompleteAt = 0.0;
	/** @var array<string,float> */
	private static array $inFlightStartedAt = [];
	private static float $totalWorkerTime = 0.0;

	public static function getMetrics() : array {
		return [
			"queueSize" => count(self::$queue),
			"inFlight" => self::$inFlight,
			"maxConcurrentWorkers" => self::$maxConcurrentWorkers,
			"maxQueueSize" => self::$maxQueueSize,
			"totalDispatched" => self::$totalDispatched,
			"totalCompleted" => self::$totalCompleted,
			"totalDropped" => self::$totalDropped,
			"lastDispatchAt" => self::$lastDispatchAt,
			"lastCompleteAt" => self::$lastCompleteAt,
			"avgWorkerTime" => self::$totalCompleted > 0 ? self::$totalWorkerTime / self::$totalCompleted : 0.0,
		];
	}

	public static function configure(int $maxConcurrentWorkers, int $maxQueueSize) : void {
		self::$maxConcurrentWorkers = $maxConcurrentWorkers > 0 ? $maxConcurrentWorkers : 1;
		self::$maxQueueSize = $maxQueueSize > 0 ? $maxQueueSize : 1;
	}

	public static function dispatch(string $checkClass, string $playerName, array $payload, int $sequence) : void {
		if (self::$inFlight >= self::$maxConcurrentWorkers && count(self::$queue) >= self::$maxQueueSize) {
			self::$totalDropped++;
			return;
		}

		self::$queue[] = [$checkClass, $playerName, $payload, $sequence];
		self::$lastDispatchAt = microtime(true);
		self::drain();
	}

	private static function drain() : void {
		while (self::$inFlight < self::$maxConcurrentWorkers && self::$queue !== []) {
			[$checkClass, $playerName, $payload, $sequence] = array_shift(self::$queue);
			self::startTask($checkClass, $playerName, $payload, $sequence);
		}
	}

	private static function startTask(string $checkClass, string $playerName, array $payload, int $sequence) : void {
		self::$inFlight++;
		self::$totalDispatched++;

		$id = $checkClass . ":" . $playerName . ":" . $sequence;
		self::$inFlightStartedAt[$id] = microtime(true);

		$thread = new ClosureThread(
			static function (string $checkClass, string $playerName, array $payload) : array {
				try {
					if (!method_exists($checkClass, 'evaluateAsync')) {
						return ['error' => 'Missing evaluateAsync()'];
					}

					return $checkClass::evaluateAsync($payload);
				} catch (Throwable $throwable) {
					return ['error' => $throwable->getMessage()];
				}
			},
			[$checkClass, $playerName, $payload]
		);
		$thread->start()->then(function(string $output) use ($checkClass, $playerName, $sequence) : void {
			self::$inFlight = self::$inFlight > 0 ? self::$inFlight - 1 : 0;
			self::$lastCompleteAt = microtime(true);
			self::$totalCompleted++;

			$id = $checkClass . ":" . $playerName . ":" . $sequence;
			$startedAt = self::$inFlightStartedAt[$id] ?? self::$lastCompleteAt;
			unset(self::$inFlightStartedAt[$id]);
			self::$totalWorkerTime += max(0.0, self::$lastCompleteAt - $startedAt);

			self::drain();

			$result = json_decode($output, true);
			if (!is_array($result) || isset($result['error'])) {
				return;
			}

			$player = Server::getInstance()->getPlayerExact($playerName);
			if ($player === null || !$player->isOnline() || !$player->isConnected()) {
				return;
			}

			$playerAPI = PlayerAPI::getAPIPlayer($player);
			if (!$playerAPI->isAsyncSequenceCurrent($checkClass, $sequence)) {
				return;
			}
			/** @var Check $check */
			$check = new $checkClass();
			self::applyResult($check, $playerAPI, $result);
		});
	}

	private static function applyResult(Check $check, PlayerAPI $playerAPI, array $result) : void {
		foreach (($result['set'] ?? []) as $key => $value) {
			$playerAPI->setExternalData((string) $key, $value);
		}
		foreach (($result['unset'] ?? []) as $key) {
			$playerAPI->unsetExternalData((string) $key);
		}

		if (isset($result['debug']) && is_string($result['debug']) && $result['debug'] !== '') {
			$check->debug($playerAPI, $result['debug']);
		}

		if (!empty($result['failed'])) {
			try {
				$check->failed($playerAPI);
			} catch (Throwable) {
			}
		}
	}
}
