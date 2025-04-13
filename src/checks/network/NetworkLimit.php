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

namespace ReinfyTeam\Zuri\checks\network;

use pocketmine\event\Event;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;

class NetworkLimit extends Check {
	public function getName() : string {
		return "NetworkLimit";
	}

	public function getSubType() : string {
		return "A";
	}

	private array $ipList = [];

	public function checkJustEvent(Event $event) : void {
		if ($event instanceof PlayerPreLoginEvent) {
			$ip = $event->getIp();
			if (!isset($this->ipList[$ip])) {
				$this->ipList[$ip] = 1;
			} else {
				$this->ipList[$ip]++;
			}

			if ($this->ipList[$ip] > self::getData(self::NETWORK_LIMIT)) {
				$this->warn($event->getPlayerInfo()->getUsername());
				$event->setKickFlag(0, self::getData(self::NETWORK_MESSAGE));
				$this->ipList[$ip]--;
			}
		}
	}

	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if ($event instanceof PlayerQuitEvent) {
			$ip = $event->getPlayer()->getNetworkSession()->getIp();

			if (isset($this->ipList[$ip])) {
				$this->ipList[$ip]--;
			}
		}
	}
}