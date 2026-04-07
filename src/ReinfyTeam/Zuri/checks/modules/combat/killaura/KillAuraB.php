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

namespace ReinfyTeam\Zuri\checks\modules\combat\killaura;

use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\config\CheckConstants;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function cos;

class KillAuraB extends Check {
	public function getName() : string {
		return "KillAura";
	}

	public function getSubType() : string {
		return "B";
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		if ($packet instanceof PlayerAuthInputPacket) {
			$player = $playerAPI->getPlayer();
			$this->dispatchAsyncCheck($player->getName(), [
				"type" => "KillAuraB",
				"flying" => $player->isFlying(),
				"allowFlight" => $player->getAllowFlight(),
				"attackTicks" => $playerAPI->getAttackTicks(),
				"teleportTicks" => $playerAPI->getTeleportTicks(),
				"survival" => $player->isSurvival(),
				"recentlyCancelled" => $playerAPI->isRecentlyCancelledEvent(),
				"pitch" => $packet->getPitch(),
				"yaw" => $packet->getYaw(),
				"deltaPitch" => $this->getConstant(CheckConstants::KILLAURAB_DELTA_PITCH),
				"deltaYaw" => $this->getConstant(CheckConstants::KILLAURAB_DELTA_YAW),
			]);
		}
	}

	public static function evaluateAsync(array $payload) : array {
		if (($payload["type"] ?? null) !== "KillAuraB") {
			return [];
		}

		if (
			!(bool) ($payload["flying"] ?? false) ||
			!(bool) ($payload["allowFlight"] ?? false) ||
			(int) ($payload["attackTicks"] ?? 0) < 100 ||
			(int) ($payload["teleportTicks"] ?? 0) < 100 ||
			(bool) ($payload["survival"] ?? false) ||
			(bool) ($payload["recentlyCancelled"] ?? false)
		) {
			return [];
		}

		$deltaPitch = cos((float) ($payload["pitch"] ?? 0.0));
		$deltaYaw = cos((float) ($payload["yaw"] ?? 0.0));
		if ($deltaPitch === (float) ($payload["deltaPitch"] ?? 0.0) && $deltaYaw === (float) ($payload["deltaYaw"] ?? 0.0)) {
			return ["failed" => true, "debug" => "deltaPitch={$deltaPitch}, deltaYaw={$deltaYaw}"];
		}

		return ["debug" => "deltaPitch={$deltaPitch}, deltaYaw={$deltaYaw}"];
	}
}