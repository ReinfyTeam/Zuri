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

namespace ReinfyTeam\Zuri\checks\combat\velocity;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Event;
use pocketmine\player\Player;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\MathUtil;

class VelocityB extends Check {
	public function getName() : string {
		return "Velocity";
	}

	public function getSubType() : string {
		return "B";
	}

	public function maxViolations() : int {
		return 5;
	}

	public function checkJustEvent(Event $event) : void {
		if ($event instanceof EntityDamageByEntityEvent) {
			$entity = $event->getEntity();
			if ($entity instanceof Player) {
				$playerAPI = PlayerAPI::getAPIPlayer($entity);
				$player = $playerAPI->getPlayer();
				if ($player === null) {
					return;
				}
				$loc = $player->getLocation();
				$lastLoc = $playerAPI->getExternalData("lastVLocB");

				if ( // prevent false-positive
					$playerAPI->isInWeb() ||
					!$playerAPI->isOnGround() ||
					$playerAPI->isOnAdhesion() ||
					!$entity->isOnGround() ||
					$player->getAllowFlight() ||
					$player->hasNoClientPredictions() ||
					$player->isFlying() ||
					$playerAPI->isInBoxBlock()
				) {
					return;
				}

				if ($lastLoc !== null) {
					$velocity = MathUtil::distance($loc->asVector3(), $lastLoc->asVector3());
					if ($velocity < $this->getConstant("limit-horizontal") && $playerAPI->getPing() < self::getData(self::PING_LAGGING)) {
						$this->failed($playerAPI);
					}
					$this->debug($playerAPI, "velocity=$velocity");
					$playerAPI->unsetExternalData("lastVLocB");
				} else {
					$playerAPI->setExternalData("lastVLocB", $loc);
				}
			}
		}
	}
}