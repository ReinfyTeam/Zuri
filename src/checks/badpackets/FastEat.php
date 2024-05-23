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
 * Zuri attempts to enforce "vanilla Minecraft" mechanics, as well as preventing
 * players from abusing weaknesses in Minecraft or its protocol, making your server
 * more safe. Organized in different sections, various checks are performed to test
 * players doing, covering a wide range including flying and speeding, fighting
 * hacks, fast block breaking and nukers, inventory hacks, chat spam and other types
 * of malicious behaviour.
 *
 * @author ReinfyTeam
 * @link https://github.com/ReinfyTeam/
 *
 *
 */

declare(strict_types=1);

namespace ReinfyTeam\Zuri\checks\badpackets;

use pocketmine\event\Event;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\item\ConsumableItem;
use pocketmine\network\mcpe\protocol\ActorEventPacket;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\types\ActorEvent;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use function microtime;

class FastEat extends Check {
	public function getName() : string {
		return "FastEat";
	}

	public function getSubType() : string {
		return "A";
	}

	public function maxViolations() : int {
		return 5;
	}

	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		if ($packet instanceof ActorEventPacket) {
			if ($packet->eventId === ActorEvent::EATING_ITEM) {
				$lastTick = $playerAPI->getExternalData("lastTickP");
				if ($lastTick === null) {
					$playerAPI->setExternalData("lastTickP", microtime(true));
				}
				$this->debug($playerAPI, "lastTick=$lastTick");
			}
		}
	}

	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if ($event instanceof PlayerItemConsumeEvent) {
			if ($event->getItem() instanceof ConsumableItem) {
				$lastTick = $playerAPI->getExternalData("lastTickP");
				if ($lastTick !== null) {
					$diff = microtime(true) - $lastTick;
					$ping = $playerAPI->getPing();
					if ($diff < $this->getConstant("timediff-limit") && $ping < self::getData(self::PING_LAGGING)) {
						$event->cancel();
						$this->failed($playerAPI);
						$playerAPI->unsetExternalData("lastTickP");
					}
					$this->debug($playerAPI, "lastTick=$lastTick, diff=$diff");
				}
			}
		}
	}
}