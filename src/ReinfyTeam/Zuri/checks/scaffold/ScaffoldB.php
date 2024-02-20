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
use pocketmine\network\mcpe\protocol\DataPacket;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use function abs;

class ScaffoldB extends Check {
	public function getName() : string {
		return "Scaffold";
	}

	public function getSubType() : string {
		return "B";
	}

	public function enable() : bool {
		return true;
	}

	public function ban() : bool {
		return true;
	}

	public function kick() : bool {
		return false;
	}

	public function flag() : bool {
		return false;
	}

	public function captcha() : bool {
		return false;
	}

	public function maxViolations() : int {
		return 10;
	}

	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
	}

	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if ($event instanceof BlockPlaceEvent) {
			$pitch = abs($playerAPI->getLocation()->getPitch());
			$player = $playerAPI->getPlayer();
			if (!$player->spawned && !$player->isConnected()) {
				return;
			} //bug fix
			if (
				$pitch < 35 &&
				$event->getBlockAgainst()->getPosition()->getY() < $playerAPI->getLocation()->getY() &&
				$playerAPI->getPing() < self::getData(self::PING_LAGGING)
			) {
				$this->failed($playerAPI);
			}
			$this->debug($playerAPI, "pitch=$pitch, ping=" . $playerAPI->getPing());
		}
	}
}
