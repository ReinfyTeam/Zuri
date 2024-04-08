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

class FastLadder extends Check {
	public function getName() : string {
		return "FastLadder";
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

	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if ($event instanceof PlayerMoveEvent) {
			$lastY = $event->getFrom()->getY();
			$newY = $event->getTo()->getY();
			$player = $playerAPI->getPlayer();
			if ($player === null) {
				return;
			}

			$x = intval($player->getLocation()->getX());
			$z = intval($player->getLocation()->getZ());

			$checkLadderLastX = $player->getWorld()->getBlockAt($x, intval($lastY), $z)->getTypeId() === BlockTypeIds::LADDER;
			$checkLadderNewY = $player->getWorld()->getBlockAt($x, intval($newY), $z)->getTypeId() === BlockTypeIds::LADDER;

			$diff = abs($newY - $lastY);

			if ($checkLadderLastX || $checkLadderNewY) {
				if ($diff > 0.5) { // impossible 0.6~
					$this->failed($playerAPI);
				}
				$this->debug($playerAPI, "lastY=$lastY, newY=$newY, diffY=$diff");
			}
		}
	}
}