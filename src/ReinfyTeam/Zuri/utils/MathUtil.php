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

use DateTime;
use pocketmine\math\Vector3;
use pocketmine\world\Position;
use function is_finite;
use function preg_match_all;
use function sqrt;
use function strtolower;

/**
 * Mathematical helpers used by movement checks.
 */
final class MathUtil {
	/**
	 * Parses a time string (e.g. "1h30m") into a DateTime object representing that duration from now.
	 *
	 * Supported units: years (y), months (mo), weeks (w), days (d), hours (h), minutes (m), seconds (s).
	 *
	 * @param string $time The time string to parse.
	 * @return DateTime A DateTime object representing the parsed time duration from now.
	 */
	public static function parseToDateTime(string $time) : DateTime {
		$date = new DateTime("NOW");

		$units = [
			'y' => 'year',
			'year' => 'year',
			'years' => 'year',

			'mo' => 'month',
			'month' => 'month',
			'months' => 'month',

			'w' => 'week',
			'week' => 'week',
			'weeks' => 'week',

			'd' => 'day',
			'day' => 'day',
			'days' => 'day',

			'h' => 'hour',
			'hour' => 'hour',
			'hours' => 'hour',

			'm' => 'minute',
			'min' => 'minute',
			'mins' => 'minute',
			'minute' => 'minute',
			'minutes' => 'minute',

			's' => 'second',
			'sec' => 'second',
			'secs' => 'second',
			'second' => 'second',
			'seconds' => 'second'
		];

		preg_match_all('/(\d+)\s*([a-z]+)/i', $time, $matches, PREG_SET_ORDER);

		foreach ($matches as [, $value, $unit]) {
			$unit = strtolower($unit);

			if (isset($units[$unit])) {
				$date->modify("+{$value} {$units[$unit]}");
			}
		}

		return $date;
	}

	/**
	 * Calculates the horizontal blocks per second (BPS) between two positions over a given number of ticks.
	 *
	 * @param Vector3 $oldPosition The previous position of the player.
	 * @param Vector3 $newPosition The current position of the player.
	 * @param int $elapsedTicks The number of ticks that have elapsed between the two positions.
	 * @return float The calculated horizontal BPS. Returns 0.0 if elapsedTicks is less than or equal to 0.
	 */
	public static function calculateHorizontalBPS(Vector3 $oldPosition, Vector3 $newPosition, int $elapsedTicks) : float {
		if ($elapsedTicks <= 0) {
			return 0.0;
		}

		$distance = self::distance2D($oldPosition, $newPosition);

		return ($distance / $elapsedTicks) * 20.0;
	}

	public static function calculateVerticalBPS(Vector3 $oldPosition, Vector3 $newPosition, int $elapsedTicks) : float {
		if ($elapsedTicks <= 0) {
			return 0.0;
		}

		$deltaY = $newPosition->y - $oldPosition->y;

		return ($deltaY / $elapsedTicks) * 20.0;
	}

	public static function distance2D(Vector3 $pos1, Vector3 $pos2) : float {
		$pos1X = $pos1->x;
		$pos1Z = $pos1->z;
		$pos2X = $pos2->x;
		$pos2Z = $pos2->z;

		if (
			!is_finite($pos1X) ||
			!is_finite($pos1Z) ||
			!is_finite($pos2X) ||
			!is_finite($pos2Z)
		) {
			return 0.0;
		}

		$dx = $pos2X - $pos1X;
		$dz = $pos2Z - $pos1Z;

		return sqrt(($dx * $dx) + ($dz * $dz));
	}


	/**
	 * Calculates the 3D distance between two positions, taking into account the player's eye height.
	 *
	 * @param Vector3 $from The starting position.
	 * @param Vector3 $to The ending position.
	 * @param float $eyeHeight The player's eye height.
	 * @return float The calculated 3D distance.
	 */
	public static function distanceReach(Vector3 $from, Vector3 $to, float $eyeHeight) : float {
		return sqrt(
			(($from->getX() - $to->getX()) ** 2) +
			(($from->getY() + $eyeHeight - $to->getY()) ** 2) +
			(($from->getZ() - $to->getZ()) ** 2)
		);
	}
}