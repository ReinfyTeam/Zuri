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

use pocketmine\block\BlockTypeIds;
use pocketmine\event\Event;
use pocketmine\event\player\PlayerMoveEvent;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\MathUtil;

class Jesus extends Check {
	public function getName() : string {
		return "Jesus";
	}

	public function getSubType() : string {
		return "A";
	}

	public function maxViolations() : int {
		return 3;
	}

	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if ($event instanceof PlayerMoveEvent) {
			$player = $playerAPI->getPlayer();
			if ($player === null) {
				return;
			}
			if (
				!$playerAPI->isInLiquid() ||
				$playerAPI->isInWeb() ||
				$playerAPI->isOnGround() ||
				$playerAPI->getTeleportTicks() < 100 ||
				$playerAPI->getDeathTicks() < 100 ||
				$player->getAllowFlight() ||
				$player->isFlying()
			) {
				return;
			}
			$bottomBlockId = $player->getWorld()->getBlock($player->getLocation()->add(0, -1, 0))->getTypeId();
			$halfBlockId = $player->getWorld()->getBlock($player->getLocation())->getTypeId();
			$upperBlockId = $player->getWorld()->getBlock($player->getLocation()->add(0, 1, 0))->getTypeId();
			if (($d = MathUtil::XZDistanceSquared($event->getFrom(), $event->getTo())) > 0.07 && $bottomBlockId === BlockTypeIds::WATER && $upperBlockId !== BlockTypeIds::WATER && $halfBlockId !== BlockTypeIds::WATER) { // i think this is weak type of checking..
				$this->failed($playerAPI);
			}
			$this->debug($playerAPI, "bottomId=$bottomBlockId, upperBlockId=$upperBlockId, halfBlockId=$halfBlockId");
		}
	}
}