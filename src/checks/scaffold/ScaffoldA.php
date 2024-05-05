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

namespace ReinfyTeam\Zuri\checks\scaffold;

use pocketmine\block\BlockTypeIds;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\Event;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use function abs;

class ScaffoldA extends Check {
	public function getName() : string {
		return "Scaffold";
	}

	public function getSubType() : string {
		return "A";
	}

	public function maxViolations() : int {
		return 2;
	}

	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if ($event instanceof BlockPlaceEvent) {
			$block = $event->getBlockAgainst();
			$posBlock = $block->getPosition();
			$player = $playerAPI->getPlayer();
			$loc = $player->getLocation();
			$itemHand = $playerAPI->getInventory()->getItemInHand();
			if ($itemHand->getTypeId() === BlockTypeIds::AIR) {
				$x = abs($posBlock->getX() - $loc->getX());
				$y = abs($posBlock->getY() - $loc->getY());
				$z = abs($posBlock->getZ() - $loc->getZ());
				$this->debug($playerAPI, "x=$x, y=$y, z=$z");
				if ($x > 1.0 || $y > 1.0 || $z > 1.0) {
					$this->failed($playerAPI);
				}
			}
		}
	}
}
