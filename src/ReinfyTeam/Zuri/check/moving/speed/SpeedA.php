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
use ReinfyTeam\Zuri\player\ExternalDataPath;
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
		return self::TYPE_PACKET;
	}

	/**
	 * Runs the SpeedA check logic on the provided data.
	 *
	 * The worker provides a payload containing player and environment data.
	 * Implementations should return an array created via `self::buildResult`.
	 *
	 * @param array<string,mixed> $data Worker payload (keys: 'type', 'playerData', 'constantData', etc.)
	 * @return array{failed:bool,debug:array<array-key,mixed>,externalData:array<string,mixed>} Result array with `failed` and `debug` keys.
	 */
	public static function check(array $data) : array {
		if (($data["type"] ?? null) === "PlayerAuthInputPacket") {
			$playerData = Utils::readArray($data["playerData"] ?? null);
			$constantData = Utils::readArray($data["constantData"] ?? null);

			if (
				Utils::readFloat($playerData["attackTicks"] ?? null) < 20 ||
				Utils::readFloat($playerData["projectileAttackTicks"] ?? null) < 20 ||
				Utils::readFloat($playerData["teleportTicks"] ?? null) < 60 ||
				Utils::readFloat($playerData["bowShotTicks"] ?? null) < 20 ||
				Utils::readFloat($playerData["hurtTicks"] ?? null) < 40 ||
				Utils::readFloat($playerData["teleportCommandTicks"] ?? null) < 40 ||
				Utils::readBool($playerData["isClimbing"] ?? null) ||
				Utils::readBool($playerData["allowFlight"] ?? null) ||
				Utils::readFloat($playerData["airTicks"] ?? null) > 40 ||
				Utils::readBool($playerData["isFlying"] ?? null) ||
				Utils::readBool($playerData["hasNoClientPredictions"] ?? null) ||
				Utils::readBool($playerData["isCreative"] ?? null) ||
				Utils::readBool($playerData["isSpectator"] ?? null) ||
				!Utils::readBool($playerData["isCurrentChunkLoaded"] ?? null) ||
				Utils::readBool($playerData["isRecentlyCancelledEvent"] ?? null)
			) {
				return self::buildResult(false);
			}

			$movement = Utils::readArray($playerData["movement"] ?? null);
			$from = Utils::readArray($movement["from"] ?? null);
			$to = Utils::readArray($movement["to"] ?? null);
			$previous = new \pocketmine\math\Vector3(Utils::readFloat($from["x"] ?? null), Utils::readFloat($from["y"] ?? null), Utils::readFloat($from["z"] ?? null));
			$next = new \pocketmine\math\Vector3(Utils::readFloat($to["x"] ?? null), Utils::readFloat($to["y"] ?? null), Utils::readFloat($to["z"] ?? null));

			$externalData = Utils::readArray($playerData["externalData"] ?? null);

			$friction = Utils::readFloat($externalData[ExternalDataPath::FRICTION_FACTOR] ?? null);
			$lastDistanceXZ = Utils::readFloat($externalData[ExternalDataPath::LAST_DISTANCE_XZ] ?? null);
			$momentum = Utils::readFloat($externalData[ExternalDataPath::MOMENTUM] ?? null);
			$movementMultiplier = Utils::readFloat($externalData[ExternalDataPath::MOVEMENT_MULTIPLIER] ?? null);
			$acceleration = Utils::readFloat($externalData[ExternalDataPath::ACCELERATION] ?? null);

			$expected = $momentum + $acceleration;
			$expected += (Utils::readFloat($playerData["jumpTicks"] ?? null) < 5 && Utils::readBool($playerData["isBlockAbove"] ?? null)) ? Utils::readFloat($constantData[ConstantPath::JUMP_FACTOR] ?? null) : 0;
			$expected += (Utils::readBool($playerData["isOnGround"] ?? null)) ? Utils::readFloat($constantData[ConstantPath::GROUND_FACTOR] ?? null) : 0;
			$expected += (Utils::readBool($playerData["isStartedJumping"] ?? null) && Utils::readFloat($playerData["lastMoveTick"] ?? null) > 5) ? Utils::readFloat($constantData[ConstantPath::LAST_JUMP_FACTOR] ?? null) : 0;
			$expected += (Utils::readFloat($playerData["jumpTicks"] ?? null) <= 20 && Utils::readBool($playerData["isIce"] ?? null)) ? Utils::readFloat($constantData[ConstantPath::ICE_FACTOR] ?? null) : 0;
			$expected += (Utils::readBool($playerData["isOnSnow"] ?? null)) ? Utils::readFloat($constantData[ConstantPath::SNOW_FACTOR] ?? null) : 0;
			$motionData = Utils::readArray($playerData["motion"] ?? null);
			$motion = (new \pocketmine\math\Vector3(Utils::readFloat($motionData["x"] ?? null), Utils::readFloat($motionData["y"] ?? null), Utils::readFloat($motionData["z"] ?? null)));
			if (abs($motion->getX()) > 0 || abs($motion->getZ()) > 0) {
				$motion = (new \pocketmine\math\Vector3(Utils::readFloat($motionData["x"] ?? null), Utils::readFloat($motionData["y"] ?? null), Utils::readFloat($motionData["z"] ?? null)));
				$motionX = abs($motion->getX());
				$motionZ = abs($motion->getZ());
				$knockback = $motionX * $motionX + $motionZ * $motionZ;

				$knockback *= Utils::readFloat($constantData[ConstantPath::KNOCKBACK_FACTOR] ?? null);
				$expected += $knockback;
			}

			$expected += Utils::readFloat($playerData["lastMoveTick"] ?? null) < 5 ? Utils::readFloat($constantData[ConstantPath::LAST_MOVE_FACTOR] ?? null) : 0;

			$dist = sqrt(($previous->x - $next->x) ** 2 + ($previous->z - $next->z) ** 2);
			$distDiff = abs($dist - $expected);

			$failed = $dist > $expected && $distDiff > Utils::readFloat($constantData[ConstantPath::SPEED_THRESHOLD] ?? null);

			return self::buildResult($failed, [
				"expected" => $expected,
				"dist" => $dist,
				"distDiff" => $distDiff,
			]);
		}

		return self::buildResult(false);
	}
}