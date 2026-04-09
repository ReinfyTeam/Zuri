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

namespace ReinfyTeam\Zuri\checks\modules\combat\autoclick;

use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\config\CacheData;
use ReinfyTeam\Zuri\config\CheckConstants;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function abs;
use function is_numeric;

class AutoClickA extends Check {
	public function getName() : string {
		return "AutoClick";
	}

	public function getSubType() : string {
		return "A";
	}

	/** @throws DiscordWebhookException */
	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		if ($packet instanceof LevelSoundEventPacket) {
			if ($packet->sound === LevelSoundEvent::ATTACK_NODAMAGE) {
				$maxTicksRaw = $this->getConstant(CheckConstants::AUTOCLICKA_MAX_TICKS);
				$maxDeviationRaw = $this->getConstant(CheckConstants::AUTOCLICKA_MAX_DEVIATION);
				$this->dispatchAsyncCheck($playerAPI->getPlayer()->getName(), [
					"type" => "AutoClickA",
					"isDigging" => $playerAPI->isDigging(),
					"ticks" => $playerAPI->getExternalData(CacheData::AUTOCLICK_A_TICKS),
					CacheData::AUTOCLICK_A_AVG_SPEED => $playerAPI->getExternalData(CacheData::AUTOCLICK_A_AVG_SPEED),
					CacheData::AUTOCLICK_A_AVG_DEVIATION => $playerAPI->getExternalData(CacheData::AUTOCLICK_A_AVG_DEVIATION),
					"maxTicks" => is_numeric($maxTicksRaw) ? (int) $maxTicksRaw : 0,
					"maxDeviation" => is_numeric($maxDeviationRaw) ? (float) $maxDeviationRaw : 0.0,
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

		$ticksValue = is_numeric($ticks) ? (int) $ticks : 0;
		$avgSpeedValue = is_numeric($avgSpeed) ? (float) $avgSpeed : 0.0;
		$avgDeviationValue = is_numeric($avgDeviation) ? (float) $avgDeviation : 0.0;
		$maxTicksRaw = $payload["maxTicks"] ?? 0;
		$maxTicks = is_numeric($maxTicksRaw) ? (int) $maxTicksRaw : 0;
		if ((bool) ($payload["isDigging"] ?? false) || $ticksValue > $maxTicks) {
			return ["unset" => [CacheData::AUTOCLICK_A_TICKS, CacheData::AUTOCLICK_A_AVG_SPEED, CacheData::AUTOCLICK_A_AVG_DEVIATION]];
		}

		$speed = $ticksValue * 50;
		$newAvgSpeed = (($avgSpeedValue * 14) + $speed) / 15;
		$deviation = abs($speed - $newAvgSpeed);
		$newAvgDeviation = (($avgDeviationValue * 9) + $deviation) / 10;
		$result = [
			"set" => [
				CacheData::AUTOCLICK_A_TICKS => $ticksValue + 1,
				CacheData::AUTOCLICK_A_AVG_SPEED => $newAvgSpeed,
				CacheData::AUTOCLICK_A_AVG_DEVIATION => $newAvgDeviation,
			],
			"debug" => "avgDeviation={$avgDeviationValue}, speed={$speed}, deviation={$deviation}, ticksClick={$ticksValue}, avgSpeed={$avgSpeedValue}",
		];
		$maxDeviationRaw = $payload["maxDeviation"] ?? 0.0;
		$maxDeviation = is_numeric($maxDeviationRaw) ? (float) $maxDeviationRaw : 0.0;
		if ($newAvgDeviation < $maxDeviation) {
			$result["failed"] = true;
		}
		return $result;
	}
}