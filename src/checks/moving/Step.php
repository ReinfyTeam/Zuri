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

namespace ReinfyTeam\Zuri\checks\moving;

use pocketmine\event\Event;
use pocketmine\event\player\PlayerMoveEvent;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;

class Step extends Check {
	public function getName() : string {
		return "Step";
	}

	public function getSubType() : string {
		return "A";
	}

	public function maxViolations() : int {
		return 3;
	}

	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if ($event instanceof PlayerMoveEvent) {
			$player = $event->getPlayer();
			if ($playerAPI->getPlayer() === null) {
				return;
			}
			if (
				$playerAPI->getPing() > self::getData(self::PING_LAGGING) ||
				!$playerAPI->isOnGround() ||
				!$player->isSurvival() ||
				$player->getAllowFlight() ||
				$player->isFlying() ||
				$playerAPI->isInLiquid() ||
				$playerAPI->isOnAdhesion() ||
				$playerAPI->getTeleportTicks() < 40 ||
				$playerAPI->getAttackTicks() < 40 ||
				$playerAPI->getDeathTicks() < 40 ||
				$playerAPI->getPlacingTicks() < 40 ||
				$event->isCancelled()
			) {
				return;
			}
			$lastY = $playerAPI->getExternalData("lastY");
			$locationPlayer = $player->getLocation();
			$limit = $this->getConstant("y-limit");
			if ($lastY !== null) {
				$diff = $locationPlayer->getY() - $lastY;
				$limit += $playerAPI->isOnStairs() ? $this->getConstant("stairs-limit") : 0;
				$limit += $playerAPI->getJumpTicks() < 40 ? $this->getConstant("jump-limit") : 0;
				if ($diff > $limit) {
					$this->failed($playerAPI);
				}
				$playerAPI->unsetExternalData("lastY");
				$this->debug($playerAPI, "lastY=$lastY, limit=$limit, diff=$diff");
			} else {
				$playerAPI->setExternalData("lastY", $locationPlayer->getY());
			}
		}
	}
}