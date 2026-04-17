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

namespace ReinfyTeam\Zuri\utils;

use function sqrt;

/**
 * Mathematical helpers used by movement checks.
 */
final class MathUtil {
	/**
	 * Calculates Euclidean distance between two positions.
	 *
	 * @param \pocketmine\world\Position $a
	 * @param \pocketmine\world\Position $b
	 */
	public static function distance(Position $a, Position $b) : float {
		return sqrt((($a->getX() - $b->getX()) ** 2) + (($a->getY() - $b->getY()) ** 2) + (($a->getZ() - $b->getZ()) ** 2));
	}

	/**
	 * Calculates momentum based on last distance and friction.
	 */
	public static function getMomentum(float $lastDistance, float $friction) : float {
		return $lastDistance * $friction * 0.91;
	}

	/**
	 * Calculates expected acceleration based on movement and friction.
	 */
	public static function getAcceleration(float $movement, float $effectMultiplier, float $friction, bool $onGround) : float {
		if (!$onGround) {
			return 0.02 * $movement;
		}

		return 0.1 * $movement * $effectMultiplier * ((0.6 / $friction) ** 3);
	}
}