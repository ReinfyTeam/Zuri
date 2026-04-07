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
use function is_array;
use function is_string;
use function json_decode;
use function method_exists;

class CheckAsyncTask {
	public static function dispatch(string $checkClass, string $playerName, array $payload) : void {
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
		$thread->start()->then(function(string $output) use ($checkClass, $playerName) : void {
			$result = json_decode($output, true);
			if (!is_array($result) || isset($result['error'])) {
				return;
			}

			$player = Server::getInstance()->getPlayerExact($playerName);
			if ($player === null || !$player->isOnline() || !$player->isConnected()) {
				return;
			}

			$playerAPI = PlayerAPI::getAPIPlayer($player);
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
