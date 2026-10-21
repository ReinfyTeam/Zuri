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

namespace ReinfyTeam\Zuri\check\combat\hitbox;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\math\AxisAlignedBB;
use ReinfyTeam\Zuri\check\Check;
use ReinfyTeam\Zuri\config\ConstantPath;
use ReinfyTeam\Zuri\utils\MathUtil;
use ReinfyTeam\Zuri\utils\Utils;
use ReinfyTeam\Zuri\utils\WorldUtil;
use function is_a;


/**
 * Hitbox check type B for anti-cheat.
 */
class HitboxB extends Check {
	/**
	 * Returns the name of the check.
	 *
	 * @return string Human-readable name used in config keys (e.g., 'Speed').
	 */
	public static function getName() : string {
		return "Hitbox";
	}


	/**
	 * Returns the subtype of the check.
	 *
	 * Subtypes are used to group related checks under a single config key.
	 *
	 * @return string Short subtype identifier (e.g., 'A').
	 */
	public static function getSubType() : string {
		return "B";
	}


	/**
	 * Returns the type of the check (packet).
	 *
	 * @return int One of self::TYPE_PACKET, self::TYPE_PLAYER or self::TYPE_EVENT.
	 */
	public static function getType() : int {
		return self::TYPE_PLAYER;
	}

	/**
	 * Runs the Reach (A) check logic on the provided data.
	 * Reach (A) focuses on distances of attacks and interactions to detect potential reach hacks.
	 *
	 * The worker provides a payload containing player and environment data.
	 * Implementations should return an array created via `self::buildResult`.
	 *
	 * @param array<string,mixed> $data Worker payload (keys: 'type', 'playerData', 'constantData', etc.)
	 * @return array{failed:bool,debug:array<array-key,mixed>,externalData:array<string,mixed>} Result array with `failed` and `debug` keys.
	 */
	public static function check(array $data) : array {
		$type = Utils::readString($data["type"] ?? null);
		if ($type !== EntityDamageByEntityEvent::class && !is_a($type, EntityDamageByEntityEvent::class, true)) {
			return self::buildResult(false);
		}

		$playerData = Utils::readArray($data["playerData"] ?? null);
		$constantData = Utils::readArray($data["constantData"] ?? null);
		$dataCheck = Utils::readArray($data["data"] ?? null);

		$enemyZuri = Utils::readArray($dataCheck["enemy"] ?? null);

		/** @var array{x: float, y: float, z: float} $rawEyePos */
		$rawEyePos = Utils::readArray($playerData["eyePos"]);
		$eyePos = Utils::arrayToVector3($rawEyePos);
		/** @var array{minX: float, minY: float, minZ: float, maxX: float, maxY: float, maxZ: float} $boundingBoxData */
		$boundingBoxData = Utils::readArray($playerData["boundingBox"]);
		$boundingBox = Utils::arrayToAxisAlignedBB($boundingBoxData);

		/** @var AxisAlignedBB[] */
		$blockCollisionBoxes = Utils::arrayToAxisAlignedBBRecursive(Utils::readArray($playerData["blockCollisionBoxes"]));
		$aimBox = MathUtil::expandedCopyAABB($boundingBox, Utils::readFloat($constantData[ConstantPath::HITBOX_TOLERANCE] ?? 0.08));
		$boundingBoxCenter = MathUtil::getBoundingBoxCenter($aimBox);

		/** @var array{x: float, y: float, z: float} $directionVectorRaw */
		$directionVectorRaw = Utils::readArray($playerData["directionVector"]);
		$directionVector = Utils::arrayToVector3($directionVectorRaw);

		$rayEnd = MathUtil::calculateRayEnd($eyePos, $boundingBoxCenter, $directionVector);
		$hasLineOfSightToAnyPoint = WorldUtil::hasLineOfSightToAnyPoint($blockCollisionBoxes, $eyePos, $aimBox);

		return self::buildResult($hasLineOfSightToAnyPoint);
	}
}
