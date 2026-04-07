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

namespace ReinfyTeam\Zuri\checks\combat\autoclick;

use ReinfyTeam\Zuri\config\CheckConstants;
use ReinfyTeam\Zuri\config\CacheData;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function abs;

class AutoClickA extends Check {
	public function getName() : string {
		return "AutoClick";
	}

	public function getSubType() : string {
		return "A";
	}

	/**
	 * @throws ReflectionException
	 * @throws DiscordWebhookException
	 */
	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		if ($packet instanceof LevelSoundEventPacket) {
			if ($packet->sound === LevelSoundEvent::ATTACK_NODAMAGE) {
				$this->dispatchAsyncCheck($playerAPI->getPlayer()->getName(), [
					"type" => "AutoClickA",
					"isDigging" => $playerAPI->isDigging(),
					"ticks" => $playerAPI->getExternalData(CacheData::AUTOCLICK_A_TICKS),
					CacheData::AUTOCLICK_A_AVG_SPEED => $playerAPI->getExternalData(CacheData::AUTOCLICK_A_AVG_SPEED),
					CacheData::AUTOCLICK_A_AVG_DEVIATION => $playerAPI->getExternalData(CacheData::AUTOCLICK_A_AVG_DEVIATION),
					"maxTicks" => (int) $this->getConstant(CheckConstants::AUTOCLICKA_MAX_TICKS),
					"maxDeviation" => (float) $this->getConstant(CheckConstants::AUTOCLICKA_MAX_DEVIATION),
				]);
			}
		}
	}

	public static function evaluateAsync(array $payload) : array {
		if (!isset($payload["type"]) || $payload["type"] !== "AutoClickA") {
			return [];
		}

		$ticks = $payload["ticks"] ?? null;
		$avgSpeed = $payload[CacheData::AUTOCLICK_A_AVG_SPEED] ?? null;
		$avgDeviation = $payload[CacheData::AUTOCLICK_A_AVG_DEVIATION] ?? null;
		if ($ticks === null || $avgSpeed === null || $avgDeviation === null) {
			return ["set" => [CacheData::AUTOCLICK_A_AVG_SPEED => 0, CacheData::AUTOCLICK_A_AVG_DEVIATION => 0, CacheData::AUTOCLICK_A_TICKS => 0]];
		}

		if ((bool) ($payload["isDigging"] ?? false) || (int) $ticks > (int) ($payload["maxTicks"] ?? 0)) {
			return ["unset" => [CacheData::AUTOCLICK_A_TICKS, CacheData::AUTOCLICK_A_AVG_SPEED, CacheData::AUTOCLICK_A_AVG_DEVIATION]];
		}

		$speed = (int) $ticks * 50;
		$newAvgSpeed = (((float) $avgSpeed * 14) + $speed) / 15;
		$deviation = abs($speed - $newAvgSpeed);
		$newAvgDeviation = (((float) $avgDeviation * 9) + $deviation) / 10;
		$result = [
			"set" => [
				CacheData::AUTOCLICK_A_TICKS => (int) $ticks + 1,
				CacheData::AUTOCLICK_A_AVG_SPEED => $newAvgSpeed,
				CacheData::AUTOCLICK_A_AVG_DEVIATION => $newAvgDeviation,
			],
			"debug" => "avgDeviation={$avgDeviation}, speed={$speed}, deviation={$deviation}, ticksClick={$ticks}, avgSpeed={$avgSpeed}",
		];
		if ($newAvgDeviation < (float) ($payload["maxDeviation"] ?? 0.0)) {
			$result["failed"] = true;
		}
		return $result;
	}
}