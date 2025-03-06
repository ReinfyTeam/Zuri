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

namespace ReinfyTeam\Zuri\checks\combat\killaura;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Event;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use ReinfyTeam\Zuri\utils\MathUtil;
use function count;

class KillAuraE extends Check {
	public function getName() : string {
		return "KillAura";
	}

	public function getSubType() : string {
		return "E";
	}

	public function maxViolations() : int {
		return 3;
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function checkJustEvent(Event $event) : void {
		if ($event instanceof EntityDamageByEntityEvent) {
			$entity = $event->getEntity();
			$damager = $event->getDamager();
			$locDamager = $damager->getLocation();
			if ($damager instanceof Player && $entity instanceof Player) {
				$playerAPI = PlayerAPI::getAPIPlayer($damager);
				$opAPI = PlayerAPI::getAPIPlayer($entity);

				/** 
				 * this might be lazy but it may work, checks if the user has shot his bow and the other user got hit by projectile
				 * tho this must be improved later, this is just an temporary solution.
				 */
				if ($playerAPI->getBowShotTicks() < 40 && $opAPI->getProjectileAttackTicks() < 40) {
                    return;
				}

				$delta = MathUtil::getDeltaDirectionVector($playerAPI, 3);
				$from = new Vector3($locDamager->getX(), $locDamager->getY() + $damager->getEyeHeight(), $locDamager->getZ());
				$to = $damager->getLocation()->add($delta->getX(), $delta->getY() + $damager->getEyeHeight(), $delta->getZ());
				$distance = MathUtil::distance($from, $to);
				$vector = $to->subtract($from->x, $from->y, $from->z)->normalize()->multiply(1);
				$entities = [];
				for ($i = 0; $i <= $distance; ++$i) {
					$from = $from->add($vector->x, $vector->y, $vector->z);
					foreach ($damager->getWorld()->getEntities() as $target) {
						$distanceA = new Vector3($from->x, $from->y, $from->z);
						if ($target->getPosition()->distance($distanceA) <= $this->getConstant("max-range")) {
							$entities[$target->getId()] = $target;
						}
					}
				}
				if (!isset($entities[$entity->getId()])) {
					$this->failed($playerAPI);
				}
				$this->debug($playerAPI, "delta=$delta, distance=$distance, entities=" . count($entities));
			}
		}
	}
}
