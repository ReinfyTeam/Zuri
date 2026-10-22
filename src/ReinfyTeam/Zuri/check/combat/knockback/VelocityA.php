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

use pocketmine\event\entity\EntityMotionEvent;
use ReinfyTeam\Zuri\check\Check;
use ReinfyTeam\Zuri\utils\MathUtil;
use ReinfyTeam\Zuri\utils\Utils;
use function is_a;


/**
 * Scaffold check type A for anti-cheat.
 */
class VelocityA extends Check {
	/**
	 * Returns the name of the check.
	 *
	 * @return string Human-readable name used in config keys (e.g., 'Speed').
	 */
	public static function getName() : string {
		return "Velocity";
	}


	/**
	 * Returns the subtype of the check.
	 *
	 * Subtypes are used to group related checks under a single config key.
	 *
	 * @return string Short subtype identifier (e.g., 'A').
	 */
	public static function getSubType() : string {
		return "A";
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
	 * Runs the Scaffold (A) check logic on the provided data.
	 * Scaffold (A) focuses on horizontal logic for base threshold for scaffold.
	 *
	 * The worker provides a payload containing player and environment data.
	 * Implementations should return an array created via `self::buildResult`.
	 *
	 * @param array<string,mixed> $data Worker payload (keys: 'type', 'playerData', 'constantData', etc.)
	 * @return array{failed:bool,debug:array<array-key,mixed>,externalData:array<string,mixed>} Result array with `failed` and `debug` keys.
	 */
	public static function check(array $data) : array {
		$type = Utils::readString($data["type"] ?? null);
		if ($type !== EntityMotionEvent::class && !is_a($type, EntityMotionEvent::class, true)) {
			return self::buildResult(false);
		}

		$playerData = Utils::readArray($data["playerData"] ?? null);
		$constantData = Utils::readArray($data["constantData"] ?? null);

		/** @var array{x:float,y:float,z:float} $oldMotion */
		$oldMotion = Utils::readArray($playerData["oldMotion"] ?? null);
		/** @var array{x:float,y:float,z:float} $newMotion */
		$newMotion = Utils::readArray($playerData["motion"] ?? null);

		$oldMotionVector = Utils::arrayToVector3($oldMotion);
		$newMotionVector = Utils::arrayToVector3($newMotion);

		$horizontalSpeed = MathUtil::hypoVector($newMotionVector);
		$horizontalDelta = MathUtil::distance2D($oldMotionVector, $newMotionVector);

		$dirX = $newMotionVector->x / $horizontalSpeed;
		$dirZ = $newMotionVector->z / $horizontalSpeed;

		return self::buildResult(false);
	}
}
