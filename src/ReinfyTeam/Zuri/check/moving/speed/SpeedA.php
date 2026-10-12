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
use ReinfyTeam\Zuri\config\ConstantPath;
use ReinfyTeam\Zuri\utils\Utils;
use function abs;
use function sqrt;


/**
 * Speed check type A for anti-cheat.
 */
class SpeedA extends Check {
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
		return "A";
	}


	/**
	 * Returns the type of the check (packet).
	 *
	 * @return int One of self::TYPE_PACKET, self::TYPE_PLAYER or self::TYPE_EVENT.
	 */
	public function getType() : int {
		return self::TYPE_PLAYER;
	}

	/**
	 * Runs the Speed (A) check logic on the provided data.
	 * Speed (A) focuses on horizontal logic for base threshold for speed.
	 *
	 * The worker provides a payload containing player and environment data.
	 * Implementations should return an array created via `self::buildResult`.
	 *
	 * @param array<string,mixed> $data Worker payload (keys: 'type', 'playerData', 'constantData', etc.)
	 * @return array{failed:bool,debug:array<array-key,mixed>,externalData:array<string,mixed>} Result array with `failed` and `debug` keys.
	 */
	public static function check(array $data) : array {
		if ((Utils::readString($data["type"]) ?? null) !== "PlayerMoveEvent") {
			return self::buildResult(false);
		}

		$playerData = Utils::readArray($data["playerData"]);
		$constantData = Utils::readArray($data["constantData"]);
		$externalData = Utils::readArray($playerData["externalData"]);

		if ($playerData["isRecentlyEnderPearled"] && $player["hasNoClientPredictions"]) {
			return self::buildResult(false); // Prevent flag with ender pearls teleportation and frozen/no client prediction movements.
		}

		$movement = Utils::readArray($playerData["movement"]);
		$from = Utils::arrayToVector3($movement["from"]);
		$to = Utils::arrayToVector3($movement["to"]);

		// Calculate distance (X, Y)
		$dx = $to->getX() - $from->getX();
		$dz = $to->getZ() - $from->getZ();
		$distance = sqrt(($dx * $dx) + ($dz * $dz));

		$limit = 0.0;
		if (Utils::readBool($playerZuri["isSprinting"])) { // Sprinting
			$baseLimit = Utils::readBool($playerZuri["isRecentlyJumped"]) ? Utils::readFloat($constantData[ConstantPath::SPEED_SPRINT_JUMP]) : Utils::readFloat($constantData[ConstantPath::SPEED_SPRINT]); // Sprinting threshold

			if (Utils::readBool($playerZuri["isElevated"])) {
				$limit += Utils::readFloat($constantData[ConstantPath::SPEED_SPRINT_JUMP_ELEVATION]) - $baseLimit;
			}

			if (Utils::readBool($playerZuri["hasTwoBlockPassage"])) {
				$limit += Utils::readFloat($constantData[ConstantPath::SPEED_SPRINT_JUMP_2_BLOCK_PASSAGE])
				- $baseLimit;
			}
		} elseif (Utils::readBool($playerZuri["isSneaking"])) { // Sneaking
			$baseLimit = Utils::readFloat($constantData[ConstantPath::SPEED_SNEAKING_FLAT]);

			if (abs($dx) !== 0 || abs($dz) !== 0) {
				$limit += $limit - Utils::readFloat($constantData[ConstantPath::SPEED_SNEAKING_DIAGONAL]);
			}

			if (Utils::readBool($playerZuri["isOnSoulSurface"])) {
				$limit += Utils::readFloat($constantData[ConstantPath::SPEED_SNEAKING_SOUL]) - $baseLimit;
			}

			if (Utils::readBool($playerZuri["hasSwiftSneak"])) {
				$limit += Utils::readFloat($constantData[ConstantPath::SPEED_SNEAKING_SWIFT]) - $baseLimit;
			}
		} elseif (Utils::readBool($playerZuri["isSwimming"])) { // Swimming
			$baseLimit = Utils::readFloat($constantData[ConstantPath::SPEED_SWIM_SURFACE]);

			if (Utils::readBool($playerZuri["isDiving"])) {
				$limit += Utils::readFloat($constantData[ConstantPath::SPEED_SWIM_DIVING]);
			}
		} elseif (Utils::readBool($playerZuri["isCreative"]) && Utils::readBool($playerZuri["isFlying"])) { // Creative flight
			$baseLimit = Utils::readBool($playerZuri["isSprinting"]) ? Utils::readFloat($constantData[ConstantPath::SPEED_CREATIVE_SPRINT_FLIGHT]) : Utils::readFloat($constantData[ConstantPath::SPEED_CREATIVE_FLIGHT]);
		} elseif (Utils::readBool($playerZuri["isSpectator"]) && Utils::readBool($playerZuri["isFlying"])) { // Spectator flight
			$baseLimit = Utils::readBool($playerZuri["isSprinting"]) ? Utils::readFloat($constantData[ConstantPath::SPEED_SPECTATOR_FLIGHT]) : Utils::readFloat($constantData[ConstantPath::SPEED_SPECTATOR_SPRINT_FLIGHT]);
		} else {
			// For Walking
			$baseLimit = Utils::readFloat($constantData[ConstantPath::SPEED_WALKING]); // Base speed for walking

			// Check if diagonally moving, add threshold
			if (abs($dx) !== 0 || abs($dz) !== 0) {
				$limit += $baseLimit - Utils::readFloat($constantData[ConstantPath::SPEED_WALK_DIAGONAL]);
			}
		}

		$limit += $baseLimit * $constantData[ConstantPath::THRESHOLD_PERCENTAGE]; // this ensure will be higher on limit so this certain percentage will be accurate as limits according to approximate values and prevent false-flagging.

		if (($result = $distance > $limit)) {
			return self::buildResult($result, [
				"limit" => $limit,
				"distance" => $distance
			]);
		}
	}
}