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
use pocketmine\world\Position;
use ReinfyTeam\Zuri\player\PlayerZuri;
use function abs;
use function floor;
use function in_array;
use function max;
use function min;
use function preg_match_all;
use function sqrt;
use function strtolower;

/**
 * Mathematical helpers used by movement checks.
 */
final class MathUtil {
	/**
	 * Calculates Euclidean distance between two positions.
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

	public static function horizontalDistance(float $fromX, float $fromZ, float $toX, float $toZ) : float {
		$dx = $toX - $fromX;
		$dz = $toZ - $fromZ;
		return sqrt(($dx * $dx) + ($dz * $dz));
	}

	public static function horizontalVelocity(float $fromX, float $fromZ, float $toX, float $toZ, float $deltaTicks) : array {
		$scale = 20.0 / max(1.0, $deltaTicks);
		return [
			"x" => ($toX - $fromX) * $scale,
			"z" => ($toZ - $fromZ) * $scale
		];
	}

	public static function horizontalSpeed(float $velocityX, float $velocityZ) : float {
		return sqrt(($velocityX ** 2) + ($velocityZ ** 2));
	}

	public static function horizontalAcceleration(
		float $velocityX,
		float $velocityZ,
		float $previousVelocityX,
		float $previousVelocityZ
	) : float {
		return self::horizontalSpeed(
			$velocityX - $previousVelocityX,
			$velocityZ - $previousVelocityZ
		);
	}

	public static function directionChange(
		float $velocityX,
		float $velocityZ,
		float $previousVelocityX,
		float $previousVelocityZ
	) : float {
		$currentSpeed = self::horizontalSpeed($velocityX, $velocityZ);
		$previousSpeed = self::horizontalSpeed($previousVelocityX, $previousVelocityZ);
		if ($currentSpeed <= 0.0001 || $previousSpeed <= 0.0001) {
			return 0.0;
		}

		$cosine = (($velocityX * $previousVelocityX) + ($velocityZ * $previousVelocityZ)) / ($currentSpeed * $previousSpeed);
		return 1.0 - max(-1.0, min(1.0, $cosine));
	}

	public static function verticalVelocityAfterTicks(float $initialVelocity, int $ticks) : float {
		if ($ticks <= 0) {
			return $initialVelocity;
		}

		return (0.98 ** ($ticks - 1)) * ($initialVelocity + 3.92) - 3.92;
	}

	public static function verticalDisplacement(float $initialVelocity, int $ticks) : float {
		if ($ticks <= 0) {
			return 0.0;
		}

		return 50.0 * ($initialVelocity + 3.92) * (1.0 - (0.98 ** $ticks)) - (3.92 * $ticks);
	}

	public static function verticalVelocityAtSeconds(float $initialVelocity, float $seconds) : float {
		$ticks = max(0, (int) floor(20.0 * $seconds));
		return self::verticalVelocityAfterTicks($initialVelocity, $ticks);
	}

	public static function verticalDisplacementAtSeconds(float $initialVelocity, float $seconds) : float {
		$ticks = max(0, (int) floor(20.0 * $seconds));
		return self::verticalDisplacement($initialVelocity, $ticks);
	}

	public static function speed3D(float $velocityX, float $velocityY, float $velocityZ) : float {
		return sqrt(($velocityX ** 2) + ($velocityY ** 2) + ($velocityZ ** 2));
	}

	public static function nextVerticalVelocity(float $velocity) : float {
		return ($velocity - 0.08) * 0.98;
	}

	public static function movementSpeed(
		int $state,
		bool $underwater,
		bool $sprinting,
		bool $sneaking,
		bool $jumping,
		bool $twoBlockPassage,
		float $pitch,
		int $speedLevel,
		int $slownessLevel,
		bool $soulSpeedSurface,
		int $soulSpeedLevel
	) : float {
		$base = match ($state) {
			PlayerZuri::STATE_SWIMMING => $underwater ? ($sprinting ? 5.612 : 1.97) : ($sprinting ? 5.612 : 2.2),
			PlayerZuri::STATE_LAVA => 1.0,
			PlayerZuri::STATE_CREATIVE => $sprinting ? 22.0 : 11.0,
			PlayerZuri::STATE_SPECTATOR => $sprinting ? 87.111 : 43.556,
			PlayerZuri::STATE_GLIDING => self::glideSpeed($pitch),
			PlayerZuri::STATE_CLIMBING => 2.35,
			PlayerZuri::STATE_STAIRS,
			PlayerZuri::STATE_ICE,
			PlayerZuri::STATE_SOUL_SPEED,
			PlayerZuri::STATE_SPRINT_JUMP,
			PlayerZuri::STATE_SPRINT,
			PlayerZuri::STATE_SNEAK,
			PlayerZuri::STATE_WALK => 4.317,
			default => 4.317
		};

		if ($state === PlayerZuri::STATE_SNEAK || ($state === PlayerZuri::STATE_ICE && $sneaking)) {
			$base = 1.3;
		} elseif ($state === PlayerZuri::STATE_SPRINT_JUMP && $sprinting && $jumping) {
			$base = $twoBlockPassage ? 9.346 : 7.127;
		} elseif ($state === PlayerZuri::STATE_SPRINT && $sprinting) {
			$base = 5.612;
		}

		$directMovement = !in_array($state, [
			PlayerZuri::STATE_CLIMBING,
			PlayerZuri::STATE_LAVA,
			PlayerZuri::STATE_CREATIVE,
			PlayerZuri::STATE_SPECTATOR,
			PlayerZuri::STATE_GLIDING
		], true);
		if ($directMovement) {
			$speedLevel = max(0, $speedLevel);
			$slownessLevel = max(0, $slownessLevel);
			$base *= 1.0 + (0.20 * $speedLevel);
			$base *= max(0.0, 1.0 - (0.15 * $slownessLevel));
		}

		if ($soulSpeedSurface) {
			$soulSpeedLevel = max(0, $soulSpeedLevel);
			$base *= $soulSpeedLevel > 0 ? (1.3 + (0.105 * $soulSpeedLevel)) : 0.4;
		}

		return $base;
	}

	public static function applyExternalVelocity(float $speed, float $velocityX, float $velocityZ) : float {
		return $speed + self::horizontalSpeed($velocityX, $velocityZ);
	}

	public static function iceSpeed(float $baseSpeed, float $previousSpeed, bool $onIce, bool $wasOnIce) : float {
		if (!$onIce && !$wasOnIce) {
			return $baseSpeed;
		}
		$momentum = ($previousSpeed * 0.98) + ($baseSpeed * 0.10);
		return min(40.0, max($baseSpeed, $momentum));
	}

	public static function glideSpeed(float $pitch) : float {
		$downward = max(0.0, min(90.0, abs($pitch)));
		return 30.0 + (($downward / 90.0) * (78.4 - 30.0));
	}

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
}