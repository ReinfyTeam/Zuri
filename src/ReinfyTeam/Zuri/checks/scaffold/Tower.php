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

use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\Event;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use function abs;

class Tower extends Check {
	private bool $place = false;

	public function getName() : string {
		return "Tower";
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
		return 1;
	}

	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if ($event instanceof BlockPlaceEvent) {
			$player = $playerAPI->getPlayer();
			if ($player === null) {
				return;
			}
			$pitch = abs($playerAPI->getLocation()->getPitch());
			$this->debug($playerAPI, "pitch=$pitch, ping=" . $playerAPI->getPing());
			$block = $event->getBlockAgainst();
			$posBlock = $block->getPosition();
			$posPlayer = $playerAPI->getLocation();
			$distanceY = abs($posBlock->getY() - $posPlayer->getY());
			$this->debug($playerAPI, "pitch=$pitch, distanceY=$distanceY");
			if ($pitch > 80 && $distanceY > 1.0 && !$player->isFlying() && !$playerAPI->getJumpTicks() < 40 && $player->isSurvival()) {
				$this->failed($playerAPI);
			}
		}
	}
}