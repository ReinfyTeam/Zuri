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
use function ceil;
use function max;
use function min;
use function sqrt;

class MathUtil {
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

	public static function getEffectsMultiplier(Player $player) : float {
		$effects = $player->getEffects();
		$speed = $effects->get(VanillaEffects::SPEED());
		$slowness = $effects->get(VanillaEffects::SLOWNESS());

		$speed = $speed != null ? $speed->getEffectLevel() : 0;
		$slowness = $slowness != null ? $slowness->getEffectLevel() : 0;

		return (1 + 0.2 * $speed) * (1 - 0.15 * $slowness);
	}

	public static function getMomentum(float $lastDistance, float $friction) : float {
		return $lastDistance * $friction * 0.91;
	}

	public static function getAcceleration(float $movement, float $effectMultiplier, float $friction, bool $onGround) : float {
		if (!$onGround) {
			return 0.02 * $movement;
		}

		return 0.1 * $movement * $effectMultiplier * ((0.6 / $friction) ** 3);
	}


	public static function getVectorOnEyeHeight(PlayerAPI $playerAPI) : Vector3 {
		return $playerAPI->getPlayer()->getLocation()->add(0, $playerAPI->getPlayer()->getEyeHeight(), 0);
	}

	public static function getDeltaDirectionVector(PlayerAPI $playerAPI, float $distance) : Vector3 {
		return ($playerAPI->getPlayer()->getDirectionVector() ?? $playerAPI->getPlayer()->getLocation())->multiply($distance);
	}

	public static function distance(Vector3 $from, Vector3 $to) : float|int {
		return sqrt((($from->getX() - $to->getX()) ** 2) + (($from->getY() - $to->getY()) ** 2) + (($from->getZ() - $to->getZ()) ** 2));
	}

	public static function pingFormula(float $ping) : int {
		return (int) ceil($ping / 50);
	}

	public static function XZDistanceSquared(Vector3 $v1, Vector3 $v2) : float {
		return ($v1->x - $v2->x) ** 2 + ($v1->z - $v2->z) ** 2;
	}
}