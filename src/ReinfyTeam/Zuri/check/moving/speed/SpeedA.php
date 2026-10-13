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
use function is_string;
use function sqrt;
use function str_ends_with;


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
		$type = $data["type"] ?? null;
		if ($type !== "PlayerMoveEvent" && !(is_string($type) && str_ends_with($type, "\\PlayerMoveEvent"))) {
			return self::buildResult(false);
		}

		$playerData = Utils::readArray($data["playerData"] ?? null);
		$constantData = Utils::readArray($data["constantData"] ?? null);

		if (Utils::readBool($playerData["isRecentlyEnderPearled"] ?? false) && Utils::readBool($playerData["hasNoClientPredictions"] ?? false)) {
			return self::buildResult(false); // Prevent flag with ender pearls teleportation and frozen/no client prediction movements.
		}
		if (Utils::readBool($playerData["isRecentlyCancelledEvent"] ?? false)) {
			return self::buildResult(false);
		}
		if (
			Utils::readFloat($playerData["teleportTicks"] ?? 100.0) < 60.0 ||
			Utils::readFloat($playerData["teleportCommandTicks"] ?? 100.0) < 40.0
		) {
			return self::buildResult(false);
		}
		if (!Utils::readBool($playerData["isCurrentChunkLoaded"] ?? true)) {
			return self::buildResult(false);
		}

		$movement = Utils::readArray($playerData["movement"] ?? null);
		if (!isset($movement["from"], $movement["to"])) {
			return self::buildResult(false);
		}
		$from = Utils::arrayToVector3($movement["from"]); // @phpstan-ignore argument.type
		$to = Utils::arrayToVector3($movement["to"]); // @phpstan-ignore argument.type

		// Calculate distance (X, Y)
		$dx = $to->getX() - $from->getX();
		$dz = $to->getZ() - $from->getZ();
		$distance = sqrt(($dx * $dx) + ($dz * $dz));

		$limit = 0.0;
		$baseLimit = Utils::readFloat($constantData[ConstantPath::SPEED_WALKING]);
		$isWalking = true;
		if (Utils::readBool($playerData["isSprinting"] ?? false)) { // Sprinting
			$isWalking = false;
			$baseLimit = Utils::readBool($playerData["isRecentlyJumped"] ?? false) ? Utils::readFloat($constantData[ConstantPath::SPEED_SPRINT_JUMP]) : Utils::readFloat($constantData[ConstantPath::SPEED_SPRINT]); // Sprinting threshold

			if (Utils::readBool($playerData["isElevated"] ?? false)) {
				$limit += Utils::readFloat($constantData[ConstantPath::SPEED_SPRINT_JUMP_ELEVATION]) - $baseLimit;
			}

			if (Utils::readBool($playerData["hasTwoBlockPassage"] ?? false)) {
				$limit += Utils::readFloat($constantData[ConstantPath::SPEED_SPRINT_JUMP_2_BLOCK_PASSAGE])
				- $baseLimit;
			}
		} elseif (Utils::readBool($playerData["isSneaking"] ?? false)) { // Sneaking
			$isWalking = false;
			$baseLimit = Utils::readFloat($constantData[ConstantPath::SPEED_SNEAKING_FLAT]);

			if (abs($dx) !== 0 && abs($dz) !== 0) {
				$limit += Utils::readFloat($constantData[ConstantPath::SPEED_SNEAKING_DIAGONAL]) - $baseLimit;
			}

			if (Utils::readBool($playerData["isOnSoulSurface"] ?? false)) {
				$limit += Utils::readFloat($constantData[ConstantPath::SPEED_SNEAKING_SOUL]) - $baseLimit;
			}

			if (Utils::readBool($playerData["hasSwiftSneak"] ?? false)) {
				$limit += Utils::readFloat($constantData[ConstantPath::SPEED_SNEAKING_SWIFT]) - $baseLimit;
			}
		} elseif (Utils::readBool($playerData["isSwimming"] ?? false)) { // Swimming
			$isWalking = false;
			$baseLimit = Utils::readFloat($constantData[ConstantPath::SPEED_SWIM_SURFACE]);


			if (Utils::readBool($playerData["isDiving"] ?? false)) {
				$limit += Utils::readFloat($constantData[ConstantPath::SPEED_SWIM_DIVING]) - $baseLimit;
			}
		} elseif (Utils::readBool($playerData["isCreative"] ?? false) && Utils::readBool($playerData["isFlying"] ?? false)) { // Creative flight
			$isWalking = false;
			$baseLimit = Utils::readBool($playerData["isSprinting"] ?? false) ? Utils::readFloat($constantData[ConstantPath::SPEED_CREATIVE_SPRINT_FLIGHT]) : Utils::readFloat($constantData[ConstantPath::SPEED_CREATIVE_FLIGHT]); // @phpstan-ignore ternary.alwaysFalse
		} elseif (Utils::readBool($playerData["isSpectator"] ?? false) && Utils::readBool($playerData["isFlying"] ?? false)) { // Spectator flight
			$isWalking = false;
			$baseLimit = Utils::readBool($playerData["isSprinting"] ?? false) ? Utils::readFloat($constantData[ConstantPath::SPEED_SPECTATOR_SPRINT_FLIGHT]) : Utils::readFloat($constantData[ConstantPath::SPEED_SPECTATOR_FLIGHT]); // @phpstan-ignore ternary.alwaysFalse
		}

		$limit += $baseLimit;
		if ($isWalking && abs($dx) !== 0 && abs($dz) !== 0) {
			$limit = Utils::readFloat($constantData[ConstantPath::SPEED_WALK_DIAGONAL]);
		}

		// this ensure will be higher on limit so this certain percentage will be accurate as limits according to approximate values and prevent false-flagging.
		$limit += $baseLimit * Utils::readFloat($constantData[ConstantPath::THRESHOLD_PERCENTAGE]);
		$result = $distance > $limit;

		return self::buildResult($result, [
			"limit" => $limit,
			"distance" => $distance
		]);
	}
}
