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

class VelocityA extends Check {
	public function getName() : string {
		return "Velocity";
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
		return 5;
	}

	public function checkJustEvent(Event $event) : void {
		if ($event instanceof EntityDamageByEntityEvent) {
			$entity = $event->getEntity();
			if ($entity instanceof Player) {
				$playerAPI = PlayerAPI::getAPIPlayer($entity);
				$player = $playerAPI->getPlayer();
				if (!$player->spawned && !$player->isConnected()) {
					return;
				} // Effect::$effectInstance bug fix
				$location = $entity->getLocation();
				$lastLocation = $playerAPI->getExternalData("lastLocationV");
				if ($lastLocation !== null) {
					if (!$event->isCancelled() && $entity->isOnGround() && !$playerAPI->isInWeb() && !$playerAPI->isUnderBlock() && !$playerAPI->isInBoxBlock()) {
						$velocity = MathUtil::distance($location->asVector3(), $lastLocation->asVector3());
						if ($velocity < 0.6 && $playerAPI->getPing() < self::getData(self::PING_LAGGING)) {
							$this->failed($playerAPI);
						}
					}
					$playerAPI->unsetExternalData("lastLocationV");
				} else {
					$playerAPI->setExternalData("lastLocationV", $location);
				}
			}
		}
	}
}