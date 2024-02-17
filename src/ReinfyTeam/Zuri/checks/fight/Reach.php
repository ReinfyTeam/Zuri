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
 * @author ReinfyTeam
 * @link https://github.com/ReinfyTeam/
 *
 *
 */

declare(strict_types=1);

namespace ReinfyTeam\Zuri\checks\fight;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Event;
use pocketmine\player\Player;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\MathUtil;

class Reach extends Check {
	public function getName() : string {
		return "Reach";
	}

	public function getSubType() : string {
		return "A";
	}

	public function enable() : bool {
		return true;
	}

	public function ban() : bool {
		return false;
	}

	public function kick() : bool {
		return true;
	}

	public function flag() : bool {
		return false;
	}

	public function captcha() : bool {
		return false;
	}

	public function maxViolations() : int {
		return 3;
	}

	public function checkJustEvent(Event $event) : void {
		if ($event instanceof EntityDamageByEntityEvent) {
			$cause = $event->getCause();
			$entity = $event->getEntity();
			$damager = $event->getDamager();
			$locEntity = $entity->getLocation();
			$locDamager = $damager->getLocation();
			if($damager === null) return;
			if ($cause === EntityDamageEvent::CAUSE_ENTITY_ATTACK && $damager instanceof Player) {
				$playerAPI = PlayerAPI::getAPIPlayer($damager);
				$isPlayerTop = $locEntity->getY() > $locDamager->getY() ? ($locEntity->getY() - $locDamager->getY()) : 0;
				$distance = MathUtil::distance($locEntity, $locDamager) - $isPlayerTop;
				if ($distance > 4.3) {
					$this->failed($playerAPI);
				}
			}
		}
	}
}