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

namespace ReinfyTeam\Zuri\checks\combat\killaura;

use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\network\mcpe\protocol\DataPacket;
use ReflectionException;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;

class KillAuraD extends Check {
	public function getName() : string {
		return "KillAura";
	}

	public function getSubType() : string {
		return "D";
	}

	/**
	 * @throws ReflectionException
	 * @throws DiscordWebhookException
	 */
	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		if (($player = $playerAPI->getPlayer()) === null) {
			return;
		}
		if ($packet instanceof AnimatePacket) {
			$this->dispatchAsyncCheck($player->getName(), [
				"type" => "KillAuraD",
				"isDigging" => $playerAPI->isDigging(),
				"placingTicks" => $playerAPI->getPlacingTicks(),
				"attackTicks" => $playerAPI->getAttackTicks(),
				"survival" => $player->isSurvival(),
				"recentlyCancelled" => $playerAPI->isRecentlyCancelledEvent(),
				"action" => $packet->action,
			]);
		}
	}

	public static function evaluateAsync(array $payload) : array {
		if (($payload["type"] ?? null) !== "KillAuraD") {
			return [];
		}

		if (
			(bool) ($payload["isDigging"] ?? false) ||
			(int) ($payload["placingTicks"] ?? 0) < 100 ||
			(int) ($payload["attackTicks"] ?? 0) < 20 ||
			!(bool) ($payload["survival"] ?? false) ||
			(bool) ($payload["recentlyCancelled"] ?? false)
		) {
			return [];
		}

		$action = (int) ($payload["action"] ?? -1);
		$debug = "isDigging=" . ((bool) ($payload["isDigging"] ?? false) ? "true" : "false") . ", placingTicks=" . (int) ($payload["placingTicks"] ?? 0) . ", attackTicks=" . (int) ($payload["attackTicks"] ?? 0) . ", isSurvival=" . ((bool) ($payload["survival"] ?? false) ? "true" : "false");
		if ($action !== AnimatePacket::ACTION_SWING_ARM && (int) ($payload["attackTicks"] ?? 0) > 40) {
			return ["failed" => true, "debug" => $debug];
		}

		return ["debug" => $debug];
	}
}