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
use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\data\bedrock\EnchantmentIds;
use pocketmine\entity\effect\EffectManager;
use pocketmine\entity\effect\Effect;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function max;
use function min;

/**
 * General utilities for vector conversion and movement calculations.
 */
final class Utils {
	public static function readFloat(mixed $value, float $default = 0.0) : float {
		return is_int($value) || is_float($value) ? (float) $value : $default;
	}

	/** @return array<array-key|mixed> */
	public static function readArray(mixed $value) : array {
		return is_array($value) ? $value : [];
	}

	public static function readInt(mixed $value, int $default = 0) : int {
		return is_int($value) ? $value : $default;
	}

	public static function readBool(mixed $value, bool $default = false) : bool {
		return is_bool($value) ? $value : $default;
	}

	public static function readString(mixed $value, string $default = "") : string {
		return is_string($value) ? $value : $default;
	}

	/**
	 * Extracts the effects from an EffectManager instance and returns them as an array.
	 * @return list<array{name: string, amplifier: int, duration: int}> Array of effects with name, amplifier, and duration.
	 */
	public static function effectsToArray(EffectManager $instance) : array {
		$effects = [];
		foreach ($instance->all() as $effect) {
			$effectType = $effect->getType(); // Returns the generic Effect type

			$name = self::getEffectName($effectType); // e.g., "potion.moveSpeed"
			$amplifier = $effect->getAmplifier(); // e.g., 0 for Speed I, 1 for Speed II
			$duration = $effect->getDuration(); // Duration remaining in server ticks

			$effects[] = ["name" => $name, "amplifier" => $amplifier, "duration" => $duration];
		}

		return $effects;
	
	}


	/**
	 * Checks if a specific effect is present in the provided effects array.
	 * @param array{name: string, amplifier: int, duration: int}[] $effects Array of effects with name, amplifier, and duration.
	 * @param string $effectName The name of the effect to check for (e.g., "potion.moveSpeed").
	 * @return bool
	 */
	public static function checkEffect(array $effects, string $effectName) : bool {
		foreach ($effects as $effect) {
			$effect = self::readArray($effect);
			if (self::readString($effect["name"]) === $effectName) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Returns the lowercase name of the effect type.
	 *
	 * @param Effect $effectType The effect type to get the name for.
	 * @return string The lowercase name of the effect type, or "unknown" if not found.
	 */
	public static function getEffectName(Effect $effectType) : string {
		foreach (VanillaEffects::getAll() as $name => $registeredEffect) {
			if ($effectType === $registeredEffect) {
				return strtolower((string) $name);
			}
		}

		return "unknown";
	}

	/**
	 * Calculates the horizontal movement multiplier contributed by Speed and Slowness effects.
	 *
	 * The effect snapshot stores the raw amplifier, where 0 represents level I.
	 *
	 * @param array{name: string, amplifier: int, duration: int}[] $effects Array of effects with name, amplifier, and duration.
	 */
	public static function getEffectMovementHorizontalMultiplier(array $effects) : float {
		$speedLevel = 0;
		$slownessLevel = 0;
		foreach ($effects as $effect) {
			$data = self::readArray($effect);
			$name = self::readString($data["name"] ?? "");
			$duration = self::readInt($data["duration"] ?? 1, 1);
			if ($duration <= 0) {
				continue;
			}

			$level = self::readInt($data["amplifier"] ?? -1, -1) + 1;
			if ($level <= 0) {
				continue;
			}
			if ($name === "speed") {
				$speedLevel = max($speedLevel, $level);
			} elseif ($name === "slowness") {
				$slownessLevel = max($slownessLevel, $level);
			}
		}

		return (1.0 + (0.2 * $speedLevel)) * max(0.0, 1.0 - (0.15 * $slownessLevel));
	}

	/**
	 * Calculates the vertical movement multiplier contributed by Jump Boost, Levitation, and Slow Falling effects.
	 *
	 * The effect snapshot stores the raw amplifier, where 0 represents level I.
	 *
	 * @param array{name: string, amplifier: int, duration: int}[] $effects Array of effects with name, amplifier, and duration.
	 * @param bool $isMovingUp Whether the player is moving upwards.
	 * @param bool $isMovingDown Whether the player is moving downwards.
	 * @return float The calculated vertical movement multiplier.
	 */
	public static function getEffectMovementVerticalMultiplier(array $effects, bool $isMovingUp, bool $isMovingDown) : float {
		$jumpBoostLevel = 0;
		$levitationLevel = 0;
		$hasSlowFalling = false;

		foreach ($effects as $effect) {
			$data = self::readArray($effect);
			$name = self::readString($data["name"] ?? "");
			$duration = self::readInt($data["duration"] ?? 1, 1);

			if ($duration <= 0) {
				continue;
			}

			$level = self::readInt($data["amplifier"] ?? -1, -1) + 1;
			if ($level <= 0) {
				continue;
			}

			switch ($name) {
				case "jump_boost":
					$jumpBoostLevel = max($jumpBoostLevel, $level);
					break;

				case "levitation":
					$levitationLevel = max($levitationLevel, $level);
					break;

				case "slow_falling":
					$hasSlowFalling = true;
					break;
			}
		}

		$multiplier = 1.0;

		if ($isMovingUp && $jumpBoostLevel > 0) {
			$multiplier = max($multiplier, 1.0 + (0.1 * $jumpBoostLevel));
		}

		if ($levitationLevel > 0) {
			$multiplier = max($multiplier, 1.0 + (0.2 * $levitationLevel));
		}

		if ($isMovingDown && $hasSlowFalling) {
			$multiplier = max($multiplier, 1.25);
		}

		return $multiplier;
	}

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
	 */
	public static function arrayToVector3(array $array) : Vector3 {
		return new Vector3(
			self::readFloat($array["x"]),
			self::readFloat($array["y"]),
			self::readFloat($array["z"])
		);
	}

	/**
	 * Converts a Vector2 to an associative array.
	 *
	 * @return array{x: float, z: float}
	 */
	public static function vector2ToArray(Vector2 $vector2) : array {
		return [
			"x" => $vector2->getX(),
			"z" => $vector2->getY()
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
	 */
	public static function getMovementMultiplier(Player $player) : float {
		$multiplier = 1.0;

		if ($player->isUsingItem()) {
			return 0.2;
		}

		if ($player->isSneaking()) {
			$leggings = $player->getArmorInventory()->getLeggings();
			$swift = $leggings->getEnchantmentLevel(VanillaEnchantments::SWIFT_SNEAK());

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
		$level = ($enchantment = EnchantmentIdMap::getInstance()->fromId(EnchantmentIds::SOUL_SPEED)) !== null ? $boots->getEnchantmentLevel($enchantment) : 0;

		if ($level > 0) {
			return 1.0 + (0.105 * $level);
		}

		return 0.4;
	}
}
