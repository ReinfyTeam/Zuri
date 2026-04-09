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
use function is_numeric;
use function microtime;

class AutoClickB extends Check {
	public function getName() : string {
		return "AutoClick";
	}

	public function getSubType() : string {
		return "B";
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		if ($playerAPI->getPlacingTicks() < 100) {
			return;
		}
		if ($packet instanceof LevelSoundEventPacket) {
			if ($packet->sound === LevelSoundEvent::ATTACK_NODAMAGE) {
				$diffTimeRaw = $this->getConstant(CheckConstants::AUTOCLICKB_DIFF_TIME);
				$diffTicksRaw = $this->getConstant(CheckConstants::AUTOCLICKB_DIFF_TICKS);
				$this->dispatchAsyncCheck($playerAPI->getPlayer()->getName(), [
					"type" => "AutoClickB",
					"placingTicks" => $playerAPI->getPlacingTicks(),
					"ticks" => $playerAPI->getExternalData(CacheData::AUTOCLICK_B_TICKS),
					CacheData::AUTOCLICK_B_LAST_CLICK => $playerAPI->getExternalData(CacheData::AUTOCLICK_B_LAST_CLICK),
					"diffTime" => is_numeric($diffTimeRaw) ? (float) $diffTimeRaw : 0.0,
					"diffTicks" => is_numeric($diffTicksRaw) ? (int) $diffTicksRaw : 0,
					"now" => microtime(true),
				]);
			}
		}
	}

	public static function evaluateAsync(array $payload) : array {
		if (($payload["type"] ?? null) !== "AutoClickB") {
			return [];
		}

		$placingTicksRaw = $payload["placingTicks"] ?? 0;
		$placingTicks = is_numeric($placingTicksRaw) ? (int) $placingTicksRaw : 0;
		if ($placingTicks < 100) {
			return [];
		}

		$ticks = $payload["ticks"] ?? null;
		$lastClick = $payload[CacheData::AUTOCLICK_B_LAST_CLICK] ?? null;
		if ($ticks === null || $lastClick === null) {
			return ["set" => [CacheData::AUTOCLICK_B_TICKS => 0, CacheData::AUTOCLICK_B_LAST_CLICK => $payload["now"] ?? microtime(true)]];
		}

		$nowRaw = $payload["now"] ?? microtime(true);
		$now = is_numeric($nowRaw) ? (float) $nowRaw : microtime(true);
		$lastClickValue = is_numeric($lastClick) ? (float) $lastClick : 0.0;
		$diffTimeRaw = $payload["diffTime"] ?? 0.0;
		$diffTime = is_numeric($diffTimeRaw) ? (float) $diffTimeRaw : 0.0;
		$diff = $now - $lastClickValue;
		if ($diff > $diffTime) {
			$ticksValue = is_numeric($ticks) ? (int) $ticks : 0;
			$result = ["unset" => [CacheData::AUTOCLICK_B_TICKS, CacheData::AUTOCLICK_B_LAST_CLICK], "debug" => "diff={$diff}, lastClick={$lastClickValue}, ticks={$ticksValue}"];
			$diffTicksRaw = $payload["diffTicks"] ?? 0;
			$diffTicks = is_numeric($diffTicksRaw) ? (int) $diffTicksRaw : 0;
			if ($ticksValue >= $diffTicks) {
				$result["failed"] = true;
			}
			return $result;
		}

		$ticksValue = is_numeric($ticks) ? (int) $ticks : 0;
		return ["set" => [CacheData::AUTOCLICK_B_TICKS => $ticksValue + 1, CacheData::AUTOCLICK_B_LAST_CLICK => $lastClickValue], "debug" => "lastClick={$lastClickValue}, ticks={$ticksValue}"];
	}
}