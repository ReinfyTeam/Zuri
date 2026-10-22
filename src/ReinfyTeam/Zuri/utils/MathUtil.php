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
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\world\Position;
use function array_sum;
use function count;
use function hypot;
use function is_array;
use function is_finite;
use function preg_match_all;
use function sqrt;
use function strtolower;

/**
 * Mathematical helpers used by movement checks.
 */
final class MathUtil {
	public const WINDOW_TICKS = 10;

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
	 * Calculates the average blocks per second (BPS) from a given array of movement samples.
	 * @param array<int, float> $data An associative array containing movement samples.
	 * @param float $currentTick The current tick rate.
	 * @return float The calculated average BPS.
	 */
	public static function calculateAverageBps(array $data, float $currentTick = 20.0) : float {
		return (
			array_sum($data) * $currentTick
		) / self::WINDOW_TICKS;
	}

	/**
	 * Calculates the 2D distances between consecutive positions in the provided array.
	 *
	 * @param array<int, Vector3|array{from: Vector3, to: Vector3}|array<mixed>> $samples An array of Vector3 positions or movement snapshots.
	 * @return array<int, float> An array of 2D distances.
	 */
	public static function calculateDistance2DSamples(array $samples) : array {
		// Normalize samples into a flat array of Vector3 objects
		$vectors = [];
		foreach ($samples as $sample) {
			if ($sample instanceof Vector3) {
				$vectors[] = $sample;
			} elseif (is_array($sample)) {
				if (isset($sample['to']) && $sample['to'] instanceof Vector3) {
					$vectors[] = $sample['to'];
				} elseif (isset($sample[1]) && $sample[1] instanceof Vector3) {
					$vectors[] = $sample[1];
				}
			}
		}

		$distances = [];
		for ($i = 1, $count = count($vectors); $i < $count; $i++) {
			$distances[] = self::distance2D($vectors[$i - 1], $vectors[$i]);
		}
		return $distances;
	}

	/**
	 * Calculates the vertical distances between consecutive positions in the provided array.
	 *
	 * @param array<int, Vector3|array{from: Vector3, to: Vector3}|array<mixed>> $samples An array of Vector3 positions or movement snapshots.
	 * @return array<int, float> An array of vertical distances.
	 */
	public static function calculateDistanceYSamples(array $samples) : array {
		// Normalize samples into a flat array of Vector3 objects
		$vectors = [];
		foreach ($samples as $sample) {
			if ($sample instanceof Vector3) {
				$vectors[] = $sample;
			} elseif (is_array($sample)) {
				if (isset($sample['to']) && $sample['to'] instanceof Vector3) {
					$vectors[] = $sample['to'];
				} elseif (isset($sample[1]) && $sample[1] instanceof Vector3) {
					$vectors[] = $sample[1];
				}
			}
		}

		$distances = [];
		$count = count($vectors);
		for ($i = 1; $i < $count; $i++) {
			$yResult = self::distanceY($vectors[$i - 1], $vectors[$i]);
			$distances[] = $yResult['distance'];
		}
		return $distances;
	}

	/**
	 * Calculates the vertical distance between two Vector3 positions.
	 * @param Vector3 $pos1 The first position.
	 * @param Vector3 $pos2 The second position.
	 * @return array{distance: float, direction: string} An array containing the distance and direction.
	 */
	public static function distanceY(Vector3 $pos1, Vector3 $pos2) : array {
		$pos1Y = $pos1->y;
		$pos2Y = $pos2->y;

		if (!is_finite($pos1Y) || !is_finite($pos2Y)) {
			return ["distance" => 0.0, "direction" => "none"];
		}

		return ["distance" => $pos2Y - $pos1Y, "direction" => ($pos2Y - $pos1Y) > 0 ? "up" : "down"];
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

		return hypot($dx, $dz); // more efficient
	}

	public static function hypoVector(Vector3 $vector) : float {
		return hypot($vector->x, $vector->z);
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

	public static function getBoundingBoxCenter(AxisAlignedBB $targetBox) : Vector3 {
		return new Vector3(
			($targetBox->minX + $targetBox->maxX) / 2.0,
			($targetBox->minY + $targetBox->maxY) / 2.0,
			($targetBox->minZ + $targetBox->maxZ) / 2.0
		);
	}

	public static function calculateRayEnd(Vector3 $eye, Vector3 $center, Vector3 $direction) : Vector3 {
		$rayLength = sqrt($eye->distanceSquared($center)) + 2.0;
		return new Vector3(
			$eye->x + $direction->x * $rayLength,
			$eye->y + $direction->y * $rayLength,
			$eye->z + $direction->z * $rayLength
		);
	}

	public static function expandedCopyAABB(AxisAlignedBB $boundingBox, float $amount) : AxisAlignedBB {
		return $boundingBox->expandedCopy($amount, $amount, $amount);
	}
}