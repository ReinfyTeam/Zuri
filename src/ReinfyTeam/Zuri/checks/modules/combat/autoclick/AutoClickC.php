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

use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Event;
use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\network\mcpe\protocol\DataPacket;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\config\CacheData;
use ReinfyTeam\Zuri\config\CheckConstants;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function is_numeric;
use function microtime;

class AutoClickC extends Check {
	private bool $canDamagable = false;

	public function getName() : string {
		return "AutoClick";
	}

	public function getSubType() : string {
		return "C";
	}

	public function checkJustEvent(Event $event) : void {
		if ($event instanceof EntityDamageEvent) {
			$this->canDamagable = $event->isCancelled();
		}
	}

	/** @throws DiscordWebhookException */
	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		if ($packet instanceof AnimatePacket) {
			if ($packet->action === AnimatePacket::ACTION_SWING_ARM) {
				$animationDiffTimeRaw = $this->getConstant(CheckConstants::AUTOCLICKC_ANIMATION_DIFF_TIME);
				$animationDiffTicksRaw = $this->getConstant(CheckConstants::AUTOCLICKC_ANIMATION_DIFF_TICKS);
				$this->dispatchAsyncCheck($playerAPI->getPlayer()->getName(), [
					"checkName" => $this->getName(),
					"checkSubType" => $this->getSubType(),
					"isDigging" => $playerAPI->isDigging(),
					"placingTicks" => $playerAPI->getPlacingTicks(),
					"attackTicks" => $playerAPI->getAttackTicks(),
					"isSurvival" => $playerAPI->getPlayer()->isSurvival(),
					"canDamagable" => $this->canDamagable,
					"ticks" => $playerAPI->getExternalData(CacheData::AUTOCLICK_C_TICKS),
					CacheData::AUTOCLICK_C_LAST_CLICK => $playerAPI->getExternalData(CacheData::AUTOCLICK_C_LAST_CLICK),
					"animationDiffTime" => is_numeric($animationDiffTimeRaw) ? (float) $animationDiffTimeRaw : 0.0,
					"animationDiffTicks" => is_numeric($animationDiffTicksRaw) ? (int) $animationDiffTicksRaw : 0,
					"now" => microtime(true),
				]);
			}
		}
	}

	public static function evaluateAsync(array $payload) : array {
		$check = new self();
		if (($payload["checkName"] ?? null) !== $check->getName() || ($payload["checkSubType"] ?? null) !== $check->getSubType()) {
			return [];
		}

		if (
			(bool) ($payload["isDigging"] ?? false) ||
			(is_numeric($payload["placingTicks"] ?? 0) ? (int) ($payload["placingTicks"] ?? 0) : 0) < 100 ||
			(is_numeric($payload["attackTicks"] ?? 0) ? (int) ($payload["attackTicks"] ?? 0) : 0) < 40 ||
			!(bool) ($payload["isSurvival"] ?? false) ||
			!(bool) ($payload["canDamagable"] ?? false)
		) {
			return [];
		}

		$ticks = $payload["ticks"] ?? null;
		$lastClick = $payload[CacheData::AUTOCLICK_C_LAST_CLICK] ?? null;
		if ($ticks === null || $lastClick === null) {
			return ["set" => [CacheData::AUTOCLICK_C_TICKS => 0, CacheData::AUTOCLICK_C_LAST_CLICK => $payload["now"] ?? microtime(true)]];
		}

		$nowRaw = $payload["now"] ?? microtime(true);
		$now = is_numeric($nowRaw) ? (float) $nowRaw : microtime(true);
		$lastClickValue = is_numeric($lastClick) ? (float) $lastClick : 0.0;
		$animationDiffTimeRaw = $payload["animationDiffTime"] ?? 0.0;
		$animationDiffTime = is_numeric($animationDiffTimeRaw) ? (float) $animationDiffTimeRaw : 0.0;
		$diff = $now - $lastClickValue;
		if ($diff > $animationDiffTime) {
			$result = ["unset" => [CacheData::AUTOCLICK_C_TICKS, CacheData::AUTOCLICK_C_LAST_CLICK]];
			$ticksValue = is_numeric($ticks) ? (int) $ticks : 0;
			$animationDiffTicksRaw = $payload["animationDiffTicks"] ?? 0;
			$animationDiffTicks = is_numeric($animationDiffTicksRaw) ? (int) $animationDiffTicksRaw : 0;
			if ($ticksValue > $animationDiffTicks) {
				$result["failed"] = true;
			}
			return $result;
		}

		$ticksValue = is_numeric($ticks) ? (int) $ticks : 0;
		return ["set" => [CacheData::AUTOCLICK_C_TICKS => $ticksValue + 1, CacheData::AUTOCLICK_C_LAST_CLICK => $lastClickValue]];
	}
}