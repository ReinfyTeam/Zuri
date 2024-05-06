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
use function abs;
use function intval;

class Spider extends Check {
	public function getName() : string {
		return "Spider";
	}

	public function getSubType() : string {
		return "A";
	}

	public function maxViolations() : int {
		return 5;
	}

	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if ($event instanceof PlayerMoveEvent) {
			$player = $playerAPI->getPlayer();
			if ($player === null) {
				return;
			}

			$x = $player->getLocation()->getX();
			$z = $player->getLocation()->getZ();

			$oldY = $event->getFrom()->getY();
			$newY = $event->getTo()->getY();

			$blockSide1 = $player->getWorld()->getBlockAt(intval($x) + 1, intval($oldY), intval($z))->isSolid() && $player->getWorld()->getBlockAt(intval($x) + 1, intval($oldY) + 1, intval($z))->isSolid();
			$blockSide2 = $player->getWorld()->getBlockAt(intval($x) - 1, intval($oldY), intval($z))->isSolid() && $player->getWorld()->getBlockAt(intval($x) - 1, intval($oldY) + 1, intval($z))->isSolid();
			$blockSide3 = $player->getWorld()->getBlockAt(intval($x), intval($oldY), intval($z) + 1)->isSolid() && $player->getWorld()->getBlockAt(intval($x), intval($oldY) + 1, intval($z) + 1)->isSolid();
			$blockSide4 = $player->getWorld()->getBlockAt(intval($x), intval($oldY), intval($z) - 1)->isSolid() && $player->getWorld()->getBlockAt(intval($x), intval($oldY) + 1, intval($z) - 1)->isSolid();
			$onLadder = $player->getWorld()->getBlockAt(intval($x), intval($oldY), intval($z))->getTypeId() === BlockTypeIds::LADDER;

			if ($blockSide1 || $blockSide2 || $blockSide3 || $blockSide4 && !$onLadder && !$player->getAllowFlight() && !$player->isFlying() && $player->isSurvival()) { // diagonals are solid and the player is not on ladder..
				$diff = abs($newY - $oldY);
				if ($newY > $oldY) { // if bigger newY > oldY
					if ($diff > $this->getConstant("limit-y-diff")) { // impossible :O y update 0.6~?
						$this->failed($playerAPI);
					}
				}
				$this->debug($playerAPI, "oldY=$oldY, newY=$newY, diffY=$diff, onLadder=$onLadder");
			}
		}
	}
}