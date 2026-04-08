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
use function array_key_exists;
use function array_shift;
use function count;
use function is_array;
use function is_string;
use function json_decode;
use function max;
use function method_exists;
use function microtime;

class CheckAsyncTask {
	/** @var list<array{0:string,1:string,2:array,3:int,4:float,5:float,6:int}> */
	private static array $queue = [];
	private static int $inFlight = 0;
	private static int $maxConcurrentWorkers = 4;
	private static int $maxQueueSize = 2048;
	private static float $workerTimeoutSeconds = 3.0;
	private static float $degradedCooldownSeconds = 6.0;
	private static float $degradedUntil = 0.0;

	/** @var array<string,array{startedAt:float,queuedAt:float,capturedAt:float,checkClass:string,playerName:string,payload:array,sequence:int,attempt:int}> */
	private static array $activeTasks = [];

	private static int $totalDispatched = 0;
	private static int $totalCompleted = 0;
	private static int $totalDropped = 0;
	private static int $totalRecoveredStuck = 0;
	private static int $totalAutoRestarts = 0;
	private static int $totalLateCompletions = 0;
	private static int $totalSyncFallback = 0;
	private static int $totalFallbackErrors = 0;
	private static float $lastDispatchAt = 0.0;
	private static float $lastCompleteAt = 0.0;
	private static float $lastHealthCheckAt = 0.0;
	private static float $totalWorkerTime = 0.0;
	private static float $totalQueueWaitTime = 0.0;
	private static float $totalBuildDelayTime = 0.0;
	private static float $totalMergeTime = 0.0;

	public static function getMetrics() : array {
		$now = microtime(true);
		self::runHealthCheck($now);

		$completed = self::$totalCompleted > 0 ? self::$totalCompleted : 1;
		return [
			"queueSize" => count(self::$queue),
			"inFlight" => self::$inFlight,
			"maxConcurrentWorkers" => self::$maxConcurrentWorkers,
			"maxQueueSize" => self::$maxQueueSize,
			"workerTimeoutSeconds" => self::$workerTimeoutSeconds,
			"totalDispatched" => self::$totalDispatched,
			"totalCompleted" => self::$totalCompleted,
			"totalDropped" => self::$totalDropped,
			"totalRecoveredStuck" => self::$totalRecoveredStuck,
			"totalAutoRestarts" => self::$totalAutoRestarts,
			"totalLateCompletions" => self::$totalLateCompletions,
			"totalSyncFallback" => self::$totalSyncFallback,
			"totalFallbackErrors" => self::$totalFallbackErrors,
			"lastDispatchAt" => self::$lastDispatchAt,
			"lastCompleteAt" => self::$lastCompleteAt,
			"lastHealthCheckAt" => self::$lastHealthCheckAt,
			"syncFallbackActive" => self::isSyncFallbackActive($now),
			"degradedUntil" => self::$degradedUntil,
			"avgBuildDelay" => self::$totalBuildDelayTime / $completed,
			"avgQueueWait" => self::$totalQueueWaitTime / $completed,
			"avgWorkerTime" => self::$totalWorkerTime / $completed,
			"avgMergeTime" => self::$totalMergeTime / $completed,
		];
	}

	public static function configure(int $maxConcurrentWorkers, int $maxQueueSize, float $workerTimeoutSeconds = 3.0, float $degradedCooldownSeconds = 6.0) : void {
		self::$maxConcurrentWorkers = $maxConcurrentWorkers > 0 ? $maxConcurrentWorkers : 1;
		self::$maxQueueSize = $maxQueueSize > 0 ? $maxQueueSize : 1;
		self::$workerTimeoutSeconds = $workerTimeoutSeconds > 0.1 ? $workerTimeoutSeconds : 0.1;
		self::$degradedCooldownSeconds = $degradedCooldownSeconds > 0.1 ? $degradedCooldownSeconds : 0.1;
	}

	public static function dispatch(string $checkClass, string $playerName, array $payload, int $sequence) : void {
		$now = microtime(true);
		self::runHealthCheck($now);

		if (self::isSyncFallbackActive($now)) {
			self::executeSyncFallback($checkClass, $playerName, $payload, $sequence);
			return;
		}

		if (self::$inFlight >= self::$maxConcurrentWorkers && count(self::$queue) >= self::$maxQueueSize) {
			self::activateDegradedMode($now);
			self::executeSyncFallback($checkClass, $playerName, $payload, $sequence);
			self::$totalDropped++;
			return;
		}

		$queuedAt = $now;
		$capturedAt = (float) ($payload["captureTime"] ?? $queuedAt);
		self::$queue[] = [$checkClass, $playerName, $payload, $sequence, $queuedAt, $capturedAt, 0];
		self::$lastDispatchAt = $queuedAt;
		self::drain();
	}

	private static function drain() : void {
		self::runHealthCheck(microtime(true));

		while (self::$inFlight < self::$maxConcurrentWorkers && self::$queue !== []) {
			[$checkClass, $playerName, $payload, $sequence, $queuedAt, $capturedAt, $attempt] = array_shift(self::$queue);
			self::startTask($checkClass, $playerName, $payload, $sequence, $queuedAt, $capturedAt, $attempt);
		}
	}

	private static function startTask(string $checkClass, string $playerName, array $payload, int $sequence, float $queuedAt, float $capturedAt, int $attempt) : void {
		self::$inFlight++;
		self::$totalDispatched++;

		$startedAt = microtime(true);
		$id = self::taskId($checkClass, $playerName, $sequence, $attempt);
		self::$activeTasks[$id] = [
			"startedAt" => $startedAt,
			"queuedAt" => $queuedAt,
			"capturedAt" => $capturedAt,
			"checkClass" => $checkClass,
			"playerName" => $playerName,
			"payload" => $payload,
			"sequence" => $sequence,
			"attempt" => $attempt,
		];

		self::$totalQueueWaitTime += max(0.0, $startedAt - $queuedAt);
		self::$totalBuildDelayTime += max(0.0, $queuedAt - $capturedAt);

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
		$thread->start()->then(function(string $output) use ($id, $checkClass, $playerName, $sequence) : void {
			if (!array_key_exists($id, self::$activeTasks)) {
				self::$totalLateCompletions++;
				return;
			}

			$taskMeta = self::$activeTasks[$id];
			unset(self::$activeTasks[$id]);

			self::$inFlight = self::$inFlight > 0 ? self::$inFlight - 1 : 0;
			self::$lastCompleteAt = microtime(true);
			self::$totalCompleted++;

			self::$totalWorkerTime += max(0.0, self::$lastCompleteAt - $taskMeta["startedAt"]);

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
			$mergeStartedAt = microtime(true);
			self::applyResult($check, $playerAPI, $result);
			self::$totalMergeTime += max(0.0, microtime(true) - $mergeStartedAt);
		});
	}

	private static function executeSyncFallback(string $checkClass, string $playerName, array $payload, int $sequence) : void {
		self::$totalSyncFallback++;

		try {
			if (!method_exists($checkClass, "evaluateAsync")) {
				self::$totalFallbackErrors++;
				return;
			}

			$result = $checkClass::evaluateAsync($payload);
			if (!is_array($result) || isset($result["error"])) {
				self::$totalFallbackErrors++;
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
			$mergeStartedAt = microtime(true);
			self::applyResult($check, $playerAPI, $result);
			self::$totalMergeTime += max(0.0, microtime(true) - $mergeStartedAt);
		} catch (Throwable) {
			self::$totalFallbackErrors++;
		}
	}

	/**
	 * Health check for stuck workers. Reclaims slots and requeues one retry attempt.
	 * @return array{reclaimed:int,restarted:int,degraded:bool}
	 */
	public static function runHealthCheck(?float $now = null) : array {
		$now ??= microtime(true);
		self::$lastHealthCheckAt = $now;

		$reclaimed = 0;
		$restarted = 0;
		$degraded = false;

		foreach (self::$activeTasks as $id => $meta) {
			if (($now - $meta["startedAt"]) <= self::$workerTimeoutSeconds) {
				continue;
			}

			unset(self::$activeTasks[$id]);
			self::$inFlight = self::$inFlight > 0 ? self::$inFlight - 1 : 0;
			$reclaimed++;
			self::$totalRecoveredStuck++;

			if ($meta["attempt"] < 1) {
				self::$queue[] = [
					$meta["checkClass"],
					$meta["playerName"],
					$meta["payload"],
					$meta["sequence"],
					$now,
					$meta["capturedAt"],
					$meta["attempt"] + 1,
				];
				$restarted++;
				self::$totalAutoRestarts++;
			}
		}

		if ($reclaimed > 0) {
			$degraded = true;
			self::activateDegradedMode($now);
			self::drain();
		}

		return [
			"reclaimed" => $reclaimed,
			"restarted" => $restarted,
			"degraded" => $degraded,
		];
	}

	private static function activateDegradedMode(float $now) : void {
		self::$degradedUntil = max(self::$degradedUntil, $now + self::$degradedCooldownSeconds);
	}

	private static function isSyncFallbackActive(float $now) : bool {
		return self::$degradedUntil > $now;
	}

	private static function taskId(string $checkClass, string $playerName, int $sequence, int $attempt) : string {
		return $checkClass . ":" . $playerName . ":" . $sequence . ":" . $attempt;
	}

	/**
	 * Test helper: reset static async pipeline state.
	 * @internal
	 */
	public static function resetForTesting() : void {
		self::$queue = [];
		self::$activeTasks = [];
		self::$inFlight = 0;
		self::$maxConcurrentWorkers = 4;
		self::$maxQueueSize = 2048;
		self::$workerTimeoutSeconds = 3.0;
		self::$degradedCooldownSeconds = 6.0;
		self::$degradedUntil = 0.0;

		self::$totalDispatched = 0;
		self::$totalCompleted = 0;
		self::$totalDropped = 0;
		self::$totalRecoveredStuck = 0;
		self::$totalAutoRestarts = 0;
		self::$totalLateCompletions = 0;
		self::$totalSyncFallback = 0;
		self::$totalFallbackErrors = 0;
		self::$lastDispatchAt = 0.0;
		self::$lastCompleteAt = 0.0;
		self::$lastHealthCheckAt = 0.0;
		self::$totalWorkerTime = 0.0;
		self::$totalQueueWaitTime = 0.0;
		self::$totalBuildDelayTime = 0.0;
		self::$totalMergeTime = 0.0;
	}

	/**
	 * Test helper: inject an active task to simulate stuck-worker recovery.
	 * @internal
	 */
	public static function injectActiveTaskForTesting(
		string $checkClass,
		string $playerName,
		int $sequence,
		float $startedAt,
		float $queuedAt,
		float $capturedAt,
		int $attempt = 0
	) : void {
		$id = self::taskId($checkClass, $playerName, $sequence, $attempt);
		self::$activeTasks[$id] = [
			"startedAt" => $startedAt,
			"queuedAt" => $queuedAt,
			"capturedAt" => $capturedAt,
			"checkClass" => $checkClass,
			"playerName" => $playerName,
			"payload" => [],
			"sequence" => $sequence,
			"attempt" => $attempt,
		];
		self::$inFlight++;
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
