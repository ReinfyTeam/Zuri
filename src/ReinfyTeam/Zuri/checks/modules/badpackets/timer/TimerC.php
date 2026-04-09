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

namespace ReinfyTeam\Zuri\checks\modules\badpackets\timer;

use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\config\CacheData;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function is_numeric;
use function is_string;

class TimerC extends Check {
	public function getName() : string {
		return "Timer";
	}

	public function getSubType() : string {
		return "C";
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		$this->dispatchAsyncCheck($playerAPI->getPlayer()->getName(), [
			"type" => $packet instanceof PlayerAuthInputPacket ? "auth" : ($packet instanceof MovePlayerPacket ? "move" : "other"),
			"delay" => $playerAPI->getExternalData(CacheData::TIMER_C_DELAY_COUNTER),
			"noClientPredictions" => $playerAPI->getPlayer()->hasNoClientPredictions(),
			"alive" => $playerAPI->getPlayer()->isAlive(),
		]);
	}

	public static function evaluateAsync(array $payload) : array {
		$typeRaw = $payload["type"] ?? "other";
		$type = is_string($typeRaw) ? $typeRaw : "other";
		$delay = $payload["delay"] ?? null;

		if ($type === "auth") {
			if ($delay === null) {
				return ["set" => [CacheData::TIMER_C_DELAY_COUNTER => 0]];
			}
			$delayValue = is_numeric($delay) ? (int) $delay : 0;
			return ["set" => [CacheData::TIMER_C_DELAY_COUNTER => $delayValue + 1]];
		}

		if ($type === "move") {
			$delayValue = is_numeric($delay) ? (int) $delay : 0;
			if ((bool) ($payload["noClientPredictions"] ?? false) && (bool) ($payload["alive"] ?? false) && $delayValue < 2) {
				return ["set" => [CacheData::TIMER_C_DELAY_COUNTER => 0], "failed" => true, "debug" => "delay={$delayValue}"];
			}
			return ["set" => [CacheData::TIMER_C_DELAY_COUNTER => 0]];
		}

		return [];
	}
}