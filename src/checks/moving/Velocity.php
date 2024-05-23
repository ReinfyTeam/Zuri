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

namespace ReinfyTeam\Zuri\checks\moving;

use pocketmine\effect\VanillaEffects;
use pocketmine\event\Event;
use pocketmine\event\player\EntityDamageByEntityEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\math\Vector3;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use function abs;
use function cos;
use function microtime;
use function sin;

class Velocity extends Check {
	public function getName() : string {
		return "Velocity";
	}

	public function getSubType() : string {
		return "A";
	}

	public function maxViolations() : int {
		return 5;
	}

	public function checkJustEvent(Event $event) : void {
		if ($event instanceof EntityDamageByEntityEvent) {
			$entity = $event->getEntity();
			$damager = $event->getDamager();
			$playerAPI = PlayerAPI::getAPIPlayer($entity);

			$knockbackFactor = $this->getConstant("knockback-factor");
			$horizontalKnockback = $damage * $knockbackFactor;

			// Consider knockback enchantment
			$knockbackEnchantment = $weapon->getEnchantment(VanillaEnchantments::KNOCKBACK());
			if ($knockbackEnchantment !== null) {
				$horizontalKnockback += 0.5 * ($knockbackEnchantment->getType()->getLevel());
			}

			// Consider effects and block types
			$resistanceFactor = 1.0;
			$resistanceEffect = $player->getEffects()->get(VanillaEffects::RESISTANCE());
			if ($resistanceEffect !== null) {
				$resistanceFactor -= 0.2 * ($resistanceEffect->getEffectLevel() + 1);
			}

			$horizontalKnockback *= $resistanceFactor;

			// Calculate the knockback vector
			$yaw = $player->getLocation()->getYaw() * (M_PI / 180);
			$knockbackX = -sin($yaw) * $horizontalKnockback;
			$knockbackZ = cos($yaw) * $horizontalKnockback;

			$knockbackVector = new Vector3($knockbackX, $knockbackFactor, $knockbackZ);

			$playerAPI->setExternalData("velocityA", ["time" => microtime(true), "vector" => $knockbackVector]);
		}
	}

	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if ($event instanceof PlayerMoveEvent) {
			$velocityData = $playerAPI->getExternalData("velocityA");
			if ($velocityData !== null) {
				$expectedVector = $velocityData["vector"];
				$timePassed = abs(microtime(true) - $velocityData["time"]);

				if ($timePassed < 1) {
					$tolerance = $this->getConstant("tolerance");
					$actualVector = $player->getPosition()->subtract($velocityData["vector"]->x, $velocityData->y, $velocityData->z);
					$this->debug($playerAPI, "actualVector=$actualVector, expectedVector=$expectedVector, tolerance=$tolerance");
					if (!abs($expectedVector->x - $actualVector->x) < $tolerance && abs($expectedVector->z - $actualVector->z) < $tolerance) {
						$this->failed($playerAPI);
					}
				}
			}
		}
	}
}