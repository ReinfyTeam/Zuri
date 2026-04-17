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
		return self::TYPE_PACKET;
	}

	/**
	 * Runs the SpeedA check logic on the provided data.
	 *
	 * The worker provides a payload containing player and environment data.
	 * Implementations should return an array created via `self::buildResult`.
	 *
	 * @param array $data Worker payload (keys: 'type', 'playerData', 'constantData', etc.)
	 * @return array{failed:bool,debug:array} Result array with `failed` and `debug` keys.
	 */
	public static function check(array $data) : array {
		if ($data["type"] === "PlayerAuthInputPacket") {
			$playerData = $data["playerData"];
			$constantData = $data["constantData"];

			if (
				$playerData["attackTicks"] < 20 ||
				$playerData["projectileAttackTicks"] < 20 ||
				$playerData["teleportTicks"] < 60 ||
				$playerData["bowShotTicks"] < 20 ||
				$playerData["hurtTicks"] < 40 ||
				$playerData["commandTicks"] < 40 ||
				$playerData["isOnAdhesion"] ||
				$playerData["allowFlight"] ||
				$playerData["airTicks"] > 40 ||
				$playerData["isFlying"] ||
				$playerData["hasNoClientPredictions"] ||
				$playerData["isSurvival"] ||
				$playerData["isCreative"] ||
				$playerData["isSpectator"] ||
				!$playerData["isCurrentChunkLoaded"] ||
				$playerData["isRecentlyCancelled"] < 40
			) {
				return false;
			}

			$previous = $playerData["movement"]["from"];
			$next = $playerData["movement"]["from"];

			$externalData = $playerData["externalData"];

			$friction = $externalData[ExternalDataPath::FRICTION_FACTOR];
			$lastDistanceXZ = $externalData[ExternalDataPath::LAST_DISTANCE_XZ];
			$momentum = $externalData[ExternalDataPath::MOMENTUM];
			$movementMultiplier = $externalData[ExternalDataPath::MOVEMENT_MULTIPLIER];
			$acceleration = $externalData[ExternalDataPath::ACCELERATION];

			$expected = $momentum + $acceleration;
			$expected += ($playerData["jumpTicks"] < 5 && $player["isBlockAbove"]) ? $constantData[ConstantPath::JUMP_FACTOR] : 0;
			$expected += ($playerData["isOnGround"]) ? $constantData[ConstantPath::GROUND_FACTOR] : 0;
			$expected += ($playerData["isStartedJumping"] && $playerData["lastMoveTick"] > 5) ? $constantData[ConstantPath::LAST_JUMP_FACTOR] : 0;
			$expected += ($playerData["jumpTicks"] <= 20 && $playerData["isOnIce"]) ? $constantData[ConstantPath::ICE_FACTOR] : 0;

			$motion = Utils::arrayToVector3($playerData["motion"]);
			if (abs($motion->getX()) > 0 || abs($motion->getZ()) > 0) {
				$motion = Utils::arrayToVector3($playerData["motion"]);
				$motionX = abs($motion->getX());
				$motionZ = abs($motion->getZ());
				$knockback = $motionX * $motionX + $motionZ * $motionZ;

				$knockback *= $constantData[ConstantPath::KNOCKBACK_FACTOR];
				$expected += $knockback;
			}

			$expected += $playerData["lastMoveTick"] < 5 ? $constantData[ConstantPath::LAST_MOVE_FACTOR] : 0;

			$dist = $previous->distance($next);
			$distDiff = abs($dist - $expected);

			if ($dist > $expected && $distDiff > $constantData[ConstantPath::SPEED_THRESHOLD]) {
				$failed = true;
			}

			return self::buildResult($failed, [
				"expected" => $expected,
				"dist" => $dist,
				"distDiff" => $distDiff,
			]);
		}

		return self::buildResult(false);
	}
}