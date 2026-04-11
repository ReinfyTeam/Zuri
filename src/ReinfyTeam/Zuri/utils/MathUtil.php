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

use pocketmine\entity\effect\VanillaEffects;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use ReinfyTeam\Zuri\player\PlayerAPI;
use function abs;
use function ceil;
use function fmod;
use function max;
use function microtime;
use function min;
use function sqrt;

/**
 * Math helper collection for movement, angle, and timing calculations.
 */
class MathUtil {
	/**
	 * Calculates movement multiplier from live player movement state.
	 *
	 * @param Player $player Player entity.
	 * @param Vector3 $move Current movement vector snapshot.
	 */
	public static function getMovement(Player $player, Vector3 $move) : float {
		$armorLeggings = $player->getArmorInventory()->getLeggings();
		$movement = 1.0;

		if ($player->isSprinting()) {
			$movement = 1.3;
		}

		if ($player->isSneaking()) {
			$movement = max(0.3, min(1.0, 0.3 + (0.15 * $armorLeggings->getEnchantmentLevel(VanillaEnchantments::SWIFT_SNEAK()))));
		}

		if ($player->isUsingItem()) {
			$movement = 0.2;
		}

		return $movement;
	}

	/**
	 * Calculates movement multiplier using precomputed state values.
	 *
	 * @param bool $sprinting Sprinting state.
	 * @param bool $sneaking Sneaking state.
	 * @param bool $usingItem Item-use state.
	 * @param int $swiftSneakLevel Swift Sneak enchant level.
	 */
	public static function getMovementSnapshot(bool $sprinting, bool $sneaking, bool $usingItem, int $swiftSneakLevel) : float {
		$movement = 1.0;

		if ($sprinting) {
			$movement = 1.3;
		}

		if ($sneaking) {
			$movement = max(0.3, min(1.0, 0.3 + (0.15 * $swiftSneakLevel)));
		}

		if ($usingItem) {
			$movement = 0.2;
		}

		return $movement;
	}

	/**
	 * Computes speed/slowness potion multiplier from a live player.
	 *
	 * @param Player $player Player entity.
	 */
	public static function getEffectsMultiplier(Player $player) : float {
		$effects = $player->getEffects();
		$speed = $effects->get(VanillaEffects::SPEED());
		$slowness = $effects->get(VanillaEffects::SLOWNESS());

		$speed = $speed != null ? $speed->getEffectLevel() : 0;
		$slowness = $slowness != null ? $slowness->getEffectLevel() : 0;

		return (1 + 0.2 * $speed) * (1 - 0.15 * $slowness);
	}

	/**
	 * Computes speed/slowness multiplier using raw effect levels.
	 *
	 * @param int $speedLevel Speed amplifier.
	 * @param int $slownessLevel Slowness amplifier.
	 */
	public static function getEffectsMultiplierSnapshot(int $speedLevel, int $slownessLevel) : float {
		return (1 + 0.2 * $speedLevel) * (1 - 0.15 * $slownessLevel);
	}

	/**
	 * Calculates momentum carryover from prior distance and friction.
	 *
	 * @param float $lastDistance Last horizontal distance.
	 * @param float $friction Surface friction factor.
	 */
	public static function getMomentum(float $lastDistance, float $friction) : float {
		return $lastDistance * $friction * 0.91;
	}

	/**
	 * Returns euclidean distance from scalar coordinate components.
	 *
	 * @param float $fromX Source X.
	 * @param float $fromY Source Y.
	 * @param float $fromZ Source Z.
	 * @param float $toX Target X.
	 * @param float $toY Target Y.
	 * @param float $toZ Target Z.
	 */
	public static function distanceFromComponents(float $fromX, float $fromY, float $fromZ, float $toX, float $toY, float $toZ) : float {
		return sqrt((($toX - $fromX) ** 2) + (($toY - $fromY) ** 2) + (($toZ - $fromZ) ** 2));
	}

	/**
	 * Returns vector length on the XZ plane.
	 *
	 * @param float $x X component.
	 * @param float $z Z component.
	 */
	public static function horizontalLength(float $x, float $z) : float {
		return sqrt(($x * $x) + ($z * $z));
	}

	/**
	 * Normalizes an angle into the [-180, 180) range.
	 *
	 * @param float $angle Input angle in degrees.
	 */
	public static function wrapAngleTo180(float $angle) : float {
		$wrapped = fmod($angle, 360.0);
		if ($wrapped >= 180.0) {
			$wrapped -= 360.0;
		}
		if ($wrapped < -180.0) {
			$wrapped += 360.0;
		}

		return $wrapped;
	}

	/**
	 * Returns absolute wrapped angle delta between two angles.
	 *
	 * @param float $from Source angle.
	 * @param float $to Target angle.
	 */
	public static function angleDiff(float $from, float $to) : float {
		return abs(self::wrapAngleTo180($to - $from));
	}

	/**
	 * Converts a timestamp to elapsed game ticks.
	 *
	 * @param float $timestamp Source timestamp.
	 */
	public static function ticksSince(float $timestamp) : int {
		return (int) ((microtime(true) - $timestamp) * 20);
	}

	/**
	 * Checks whether a timestamp is within a tick window.
	 *
	 * @param float $timestamp Source timestamp.
	 * @param float $maxTicks Maximum allowed age in ticks.
	 */
	public static function isRecent(float $timestamp, float $maxTicks) : bool {
		return abs(self::ticksSince($timestamp)) <= $maxTicks;
	}

	/**
	 * Computes movement acceleration factor for ground/air contexts.
	 *
	 * @param float $movement Base movement multiplier.
	 * @param float $effectMultiplier Potion effect multiplier.
	 * @param float $friction Friction coefficient.
	 * @param bool $onGround Ground state.
	 */
	public static function getAcceleration(float $movement, float $effectMultiplier, float $friction, bool $onGround) : float {
		if (!$onGround) {
			return 0.02 * $movement;
		}

		return 0.1 * $movement * $effectMultiplier * ((0.6 / $friction) ** 3);
	}


	/**
	 * Returns player eye-level position vector.
	 *
	 * @param PlayerAPI $playerAPI Player state wrapper.
	 */
	public static function getVectorOnEyeHeight(PlayerAPI $playerAPI) : Vector3 {
		return $playerAPI->getPlayer()->getLocation()->add(0, $playerAPI->getPlayer()->getEyeHeight(), 0);
	}

	/**
	 * Returns direction vector scaled to the provided reach distance.
	 *
	 * @param PlayerAPI $playerAPI Player state wrapper.
	 * @param float $distance Scalar multiplier.
	 */
	public static function getDeltaDirectionVector(PlayerAPI $playerAPI, float $distance) : Vector3 {
		return $playerAPI->getPlayer()->getDirectionVector()->multiply($distance);
	}

	/**
	 * Returns euclidean distance between two vectors.
	 *
	 * @param Vector3 $from Source vector.
	 * @param Vector3 $to Target vector.
	 */
	public static function distance(Vector3 $from, Vector3 $to) : float {
		return sqrt((($from->getX() - $to->getX()) ** 2) + (($from->getY() - $to->getY()) ** 2) + (($from->getZ() - $to->getZ()) ** 2));
	}

	/**
	 * Converts ping milliseconds into coarse compensation ticks.
	 *
	 * @param float $ping Ping in milliseconds.
	 */
	public static function pingFormula(float $ping) : int {
		return (int) ceil($ping / 50);
	}

	/**
	 * Returns squared horizontal distance between two vectors.
	 *
	 * @param Vector3 $v1 First vector.
	 * @param Vector3 $v2 Second vector.
	 */
	public static function XZDistanceSquared(Vector3 $v1, Vector3 $v2) : float {
		return ($v1->x - $v2->x) ** 2 + ($v1->z - $v2->z) ** 2;
	}
}
