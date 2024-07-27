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

namespace ReinfyTeam\Zuri\checks\combat;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Event;
use pocketmine\network\mcpe\protocol\ActorEventPacket;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\types\ActorEvent;
use pocketmine\player\Player;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;

class ImposibleHit extends Check {
	public function getName() : string {
		return "InventoryMove";
	}

	public function getSubType() : string {
		return "A";
	}

	public function maxViolations() : int {
		return 2;
	}

	private array $eating = [];

    /**
     * @throws DiscordWebhookException
     */
    public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if ($event instanceof EntityDamageByEntityEvent) {
			if (($event->getEntity()) instanceof Player && ($event->getDamager()) instanceof Player) {
				if ($playerAPI->isInventoryOpen() || isset($this->eating[$playerAPI->getPlayer()->getName()])) {
					$this->failed($playerAPI); // impossible to hit player while opened an inventory :( or while eating..
				}
				$this->debug($playerAPI, "isInventoryOpen=" . $playerAPI->isInventoryOpen() . ", isEating=" . isset($this->eating[$playerAPI->getPlayer()->getName()]));
			}
		}
	}

	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		if ($packet instanceof ActorEventPacket) {
			if ($packet->eventId === ActorEvent::EATING_ITEM) {
				$this->eating[$playerAPI->getPlayer()->getName()] = true;
			} else {
				unset($this->eating[$playerAPI->getPlayer()->getName()]);
			}
			$this->debug($playerAPI, "isInventoryOpen=" . $playerAPI->isInventoryOpen() . ", isEating=" . isset($this->eating[$playerAPI->getPlayer()->getName()]));
		}
	}
}
