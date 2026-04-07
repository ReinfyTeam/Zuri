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

namespace ReinfyTeam\Zuri\checks\badpackets\timer;

use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\config\CacheData;
use ReinfyTeam\Zuri\config\CheckConstants;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function microtime;
use function round;

class TimerB extends Check {
	public function getName() : string {
		return "Timer";
	}

	public function getSubType() : string {
		return "B";
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		if ($packet instanceof PlayerAuthInputPacket) {
			$this->dispatchAsyncCheck($playerAPI->getPlayer()->getName(), [
				"type" => "TimerB",
				"alive" => $playerAPI->getPlayer()->isAlive(),
				"currentTime" => microtime(true) * 1000,
				"lastTime" => $playerAPI->getExternalData(CacheData::TIMER_A_LAST_TIME),
				"balance" => $playerAPI->getExternalData(CacheData::TIMER_A_BALANCE, 0),
				"diffBalance" => (float) $this->getConstant(CheckConstants::TIMERB_DIFF_BALANCE),
			]);
		}
	}

	public static function evaluateAsync(array $payload) : array {
		if (!(bool) ($payload["alive"] ?? false)) {
			return ["set" => [CacheData::TIMER_A_BALANCE => 0], "unset" => [CacheData::TIMER_A_LAST_TIME],];
		}

		$currentTime = (float) ($payload["currentTime"] ?? 0.0);
		$lastTime = $payload["lastTime"] ?? null;
		$balance = (float) ($payload["balance"] ?? 0.0);
		$diffBalance = (float) ($payload["diffBalance"] ?? 0.0);

		if ($lastTime === null) {
			return ["set" => [CacheData::TIMER_A_LAST_TIME => $currentTime, CacheData::TIMER_A_BALANCE => $balance]];
		}

		$timeDiff = round(($currentTime - (float) $lastTime) / 50, 2);
		$newBalance = ($balance - 1) + $timeDiff;
		$result = [
			"set" => [
				CacheData::TIMER_A_LAST_TIME => $currentTime,
				CacheData::TIMER_A_BALANCE => $newBalance,
			],
		];

		if ($newBalance <= $diffBalance) {
			$result["failed"] = true;
			$result["set"][CacheData::TIMER_A_BALANCE] = 0;
		}

		return $result;
	}
}