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

namespace ReinfyTeam\Zuri\checks\badpackets;

use pocketmine\entity\projectile\Arrow;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\event\Event;
use pocketmine\player\Player;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use function microtime;

class FastThrow extends Check {
	public function getName() : string {
		return "FastThrow";
	}

	public function getSubType() : string {
		return "A";
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
		if ($event instanceof ProjectileLaunchEvent) {
			if (($entity = $event->getEntity()->getOwningEntity()) instanceof Player) { // prevent from crashing
				$playerAPI = PlayerAPI::getAPIPlayer($entity);
				$player = $playerAPI->getPlayer();
				$projectile = $event->getEntity();
				if (!$projectile instanceof Arrow) { // ignore for Arrows
					$lastUse = $playerAPI->getExternalData("lastUseFT");
					if ($lastUse !== null) {
						$diff = microtime(true) - $lastUse; // by ticks
						$this->debug($playerAPI, "diff=$diff");
						if ($diff < 0.2 && $playerAPI->getPing() < self::getData(self::PING_LAGGING)) { // < ~0.5 sec
							$event->cancel(); // cancel the event for safety
							$this->failed($playerAPI);
						}
						$playerAPI->unsetExternalData("lastUseFT");
					} else {
						$playerAPI->setExternalData("lastUseFT", microtime(true));
					}
				}
			}
		}
	}
}