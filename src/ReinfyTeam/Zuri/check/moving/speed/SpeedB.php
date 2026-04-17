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

namespace ReinfyTeam\Zuri\check\moving\speed;

use ReinfyTeam\Zuri\check\Check;
use function abs;


/**
 * Speed check type B for anti-cheat.
 */
class SpeedB extends Check {
	/**
	 * Returns the name of the check.
	 *
	 * @return string Human-readable name used in config keys (e.g., 'Speed').
	 */
	public function getName() : string {
		return "Speed";
	}


	/**
	 * Returns the subtype of the check.
	 *
	 * Subtypes are used to group related checks under a single config key.
	 *
	 * @return string Short subtype identifier (e.g., 'A').
	 */
	public function getSubType() : string {
		return "B";
	}


	/**
	 * Returns the type of the check (packet).
	 *
	 * @return int One of self::TYPE_PACKET, self::TYPE_PLAYER or self::TYPE_EVENT.
	 */
	public function getType() : int {
		return self::TYPE_PACKET;
	}

	/**
	 * Runs the SpeedB check logic on the provided data.
	 *
	 * The worker provides a payload containing player and environment data.
	 * Implementations should return an array created via `self::buildResult`.
	 *
	 * @param array $data Worker payload (keys: 'type', 'playerData', 'constantData', etc.)
	 * @return array{failed:bool,debug:array} Result array with `failed` and `debug` keys.
	 */
	public static function check(array $data) : array {
		if ($data["type"] === "PlayerMoveEvent") {
			$playerData = $data["playerData"];
			$constantData = $data["constantData"];

			$movement = $playerData["movement"];
			$movementX = abs($movement["to"]["x"] - $movement["from"]["x"]);
			$movementZ = abs($movement["to"]["z"] - $movement["from"]["z"]);
			$movementY = abs($movement["to"]["y"] - $movement["from"]["y"]);

			if (
				$movementX < 0.0001 &&
				$movementY < 0.0001 &&
				$movementZ < 0.0001
			) {
				return self::buildResult(false);
			}

			if (
				$playerData["isSurvival"] ||
				$playerData["attackTicks"] < 40 ||
				$playerData["projectileAttackTicks"] < 20 ||
				$playerData["bowShotTicks"] < 20 ||
				$playerData["hurtTicks"] < 10 ||
				$playerData["teleportTicks"] < 60 ||
				$playerData["slimeBlockTicks"] < 20 ||
				$playerData["teleportCommandTicks"] < 40 ||
				$playerData["onlineTime"] < 2 ||
				$playerData["isOnAdhesion"] ||
				!$playerData["isOnGround"] ||
				$playerData["isFlying"] ||
				$playerData["getAllowFlight"] ||
				$playerData["hasNoClientPredictions"] ||
				!$playerData["isCurrentChunkLoaded"] ||
				$playerData["isGroundSolid"] ||
				$playerData["isGliding"] ||
				$playerData["isRecentlyCancelledEvent"]
			) {
				return self::buildResult(false);
			}
		}
	}
}