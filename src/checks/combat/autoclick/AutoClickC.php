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

use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Event;
use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\network\mcpe\protocol\DataPacket;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
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

	/**
	 * @throws ReflectionException
	 * @throws DiscordWebhookException
	 */
	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		if ($playerAPI->getPlayer() === null) {
			return;
		}
		if ($packet instanceof AnimatePacket) {
			if ($packet->action === AnimatePacket::ACTION_SWING_ARM) {
				$this->dispatchAsyncCheck($playerAPI->getPlayer()->getName(), [
					"type" => "AutoClickC",
					"isDigging" => $playerAPI->isDigging(),
					"placingTicks" => $playerAPI->getPlacingTicks(),
					"attackTicks" => $playerAPI->getAttackTicks(),
					"isSurvival" => $playerAPI->getPlayer()->isSurvival(),
					"canDamagable" => $this->canDamagable,
					"ticks" => $playerAPI->getExternalData("clicksTicks3"),
					"lastClick" => $playerAPI->getExternalData("lastClick3"),
					"animationDiffTime" => (float) $this->getConstant("animation-diff-time"),
					"animationDiffTicks" => (int) $this->getConstant("animation-diff-ticks"),
					"now" => microtime(true),
				]);
			}
		}
	}

	public static function evaluateAsync(array $payload) : array {
		if (($payload["type"] ?? null) !== "AutoClickC") {
			return [];
		}

		if (
			(bool) ($payload["isDigging"] ?? false) ||
			(int) ($payload["placingTicks"] ?? 0) < 100 ||
			(int) ($payload["attackTicks"] ?? 0) < 40 ||
			!(bool) ($payload["isSurvival"] ?? false) ||
			!(bool) ($payload["canDamagable"] ?? false)
		) {
			return [];
		}

		$ticks = $payload["ticks"] ?? null;
		$lastClick = $payload["lastClick"] ?? null;
		if ($ticks === null || $lastClick === null) {
			return ["set" => ["clicksTicks3" => 0, "lastClick3" => $payload["now"] ?? microtime(true)]];
		}

		$diff = (float) ($payload["now"] ?? microtime(true)) - (float) $lastClick;
		if ($diff > (float) ($payload["animationDiffTime"] ?? 0.0)) {
			$result = ["unset" => ["clicksTicks3", "lastClick3"]];
			if ((int) $ticks > (int) ($payload["animationDiffTicks"] ?? 0)) {
				$result["failed"] = true;
			}
			return $result;
		}

		return ["set" => ["clicksTicks3" => (int) $ticks + 1, "lastClick3" => $lastClick]];
	}
}