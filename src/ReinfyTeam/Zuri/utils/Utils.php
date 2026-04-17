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

use pocketmine\block\BlockTypeIds;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use function max;
use function min;

/**
 * General utilities for vector conversion and movement calculations.
 */
final class Utils {
	/**
	 * Converts a Vector3 to an associative array.
	 *
	 * @return array{x: float, y: float, z: float}
	 */
	public static function vector3ToArray(Vector3 $vector3) : array {
		return [
			"x" => $vector3->getX(),
			"y" => $vector3->getY(),
			"z" => $vector3->getZ()
		];
	}

	/**
	 * Creates a Vector3 from an associative array.
	 *
	 * @param array{x: float, y: float, z: float} $array
	 * @return Vector3
	 */
	public static function arrayToVector3(array $array) {
		return new Vector3($array["x"], $array["y"], $array["z"]);
	}

	/**
	 * Converts a Vector2 to an associative array.
	 *
	 * @return array{x: float, z: float}
	 */
	public static function vector2ToArray(Vector2 $vector2) : array {
		return [
			"x" => $vector2->getX(),
			"z" => $vector2->getZ()
		];
	}

	/**
	 * Creates a Vector2 from an associative array.
	 *
	 * @param array{x: float, z: float} $array
	 */
	public static function arrayToVector2(array $array) : Vector2 {
		return new Vector2($array["x"], $array["z"]);
	}

	/**
	 * Calculates the movement multiplier for a player based on state and effects.
	 *
	 * @param \pocketmine\player\Player $player
	 */
	public static function getMovementMultiplier(Player $player) : float {
		$multiplier = 1.0;

		if ($player->isUsingItem()) {
			return 0.2;
		}

		if ($player->isSneaking()) {
			$leggings = $player->getArmorInventory()->getLeggings();
			$swift = $leggings?->getEnchantmentLevel(VanillaEnchantments::SWIFT_SNEAK()) ?? 0;

			$multiplier *= self::getSneakMultiplier($swift);
		}

		if ($player->isSprinting()) {
			$multiplier *= 1.3;
		}

		[$speedAmp, $slowAmp] = self::getSpeedEffects($player);

		$multiplier *= 1.0 + (0.2 * $speedAmp);
		$multiplier *= max(0.0, 1.0 - (0.15 * $slowAmp));

		$multiplier *= self::getSoulSpeedMultiplier($player);

		return $multiplier;
	}

	/**
	 * Returns the sneak movement multiplier for a given enchant level.
	 */
	public static function getSneakMultiplier(int $level) : float {
		return min(1.0, max(0.3, 0.3 + ($level * 0.15)));
	}

	/**
	 * Returns the strength of speed and slowness effects on the player.
	 *
	 * @param \pocketmine\player\Player $player
	 * @return int[] [speedLevel, slownessLevel]
	 */
	public static function getSpeedEffects(Player $player) : array {
		$effects = $player->getEffects();
		$speed = $effects->get(VanillaEffects::SPEED());
		$slowness = $effects->get(VanillaEffects::SLOWNESS());

		$speed = $speed != null ? $speed->getEffectLevel() : 0;
		$slowness = $slowness != null ? $slowness->getEffectLevel() : 0;

		return [$speed, $slowness];
	}

	/**
	 * Calculates the soul speed multiplier based on boots enchant and ground block.
	 *
	 * @param \pocketmine\player\Player $player
	 */
	public static function getSoulSpeedMultiplier(Player $player) : float {
		$pos = $player->getPosition();
		$world = $player->getWorld();

		$blockId = $world->getBlockAt(
			(int) $pos->x,
			(int) $pos->y - 1,
			(int) $pos->z
		)->getTypeId();

		if ($blockId !== BlockTypeIds::SOUL_SAND && $blockId !== BlockTypeIds::SOUL_SOIL) {
			return 1.0;
		}

		$boots = $player->getArmorInventory()->getBoots();
		$level = $boots?->getEnchantmentLevel(VanillaEnchantments::SOUL_SPEED()) ?? 0;

		if ($level > 0) {
			return 1.0 + (0.105 * $level);
		}

		return 0.4;
	}
}