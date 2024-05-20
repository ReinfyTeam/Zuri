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

namespace ReinfyTeam\Zuri\checks\combat\reach;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Event;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;

class ReachC extends Check {
	public function getName() : string {
		return "Reach";
	}

	public function getSubType() : string {
		return "C";
	}

	public function maxViolations() : int {
		return 3;
	}

	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if ($event instanceof EntityDamageByEntityEvent) {
			if (($victim = $event->getEntity()) instanceof Player && ($damager = $event->getDamager()) instanceof Player) {
				$eyeHeight = $damager->getEyePos();
				$cuboid = $victim->getBoundingBox();
				// get the distance between the eye height and the cuboid
				$distance = $eyeHeight->distance(new Vector3($cuboid->minX, $cuboid->minY, $cuboid->minZ));
				$this->debug($playerAPI, "distance=$distance");

				if ($distance > $this->getConstant("max-reach-eye-distance")) {
					$this->failed($playerAPI);
				}
			}
		}
	}
}