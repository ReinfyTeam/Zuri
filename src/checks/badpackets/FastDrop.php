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

use pocketmine\event\Event;
use pocketmine\event\player\PlayerDropItemEvent;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use function microtime;

class FastDrop extends Check {
	public function getName() : string {
		return "FastDrop";
	}

	public function getSubType() : string {
		return "A";
	}

	public function maxViolations() : int {
		return 5;
	}

	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if ($event instanceof PlayerDropItemEvent) {
			$lastTick = $playerAPI->getExternalData("lastTickD");
			$currentTick = microtime(true);
			if ($lastTick !== null) {
				$diff = $currentTick - $lastTick;
				$ping = $playerAPI->getPing();
				if ($diff < $this->getConstant("time-limit") && $ping < self::getData(self::PING_LAGGING)) { // Wtf same as fastthrow?
					$event->cancel();
					$this->failed($playerAPI);
				}
				$this->debug($playerAPI, "lastTick=$lastTick, diff=$diff");
			}
			$playerAPI->setExternalData("lastTickD", $currentTick);
		}
	}
}