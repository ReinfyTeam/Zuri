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

namespace ReinfyTeam\Zuri\checks\moving;

use pocketmine\event\Event;
use pocketmine\event\player\PlayerMoveEvent;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;

class AntiImmobile extends Check {
	public function getName() : string {
		return "AntiImmobile";
	}

	public function getSubType() : string {
		return "A";
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if ($event instanceof PlayerMoveEvent) {
			$player = $playerAPI->getPlayer();
			if ($player->hasNoClientPredictions()) {
				if ($playerAPI->getTeleportCommandTicks() < 40 || $playerAPI->getOnlineTime() < 2 || $playerAPI->recentlyCancelledEvent() < 40) { // fix false positive when teleporting player when immobile is true
					return;
				}
				if ($event->getFrom()->getX() !== $event->getTo()->getX() || $event->getFrom()->getY() !== $event->getTo()->getY() || $event->getFrom()->getZ() !== $event->getTo()->getZ()) {
					$this->failed($playerAPI);
				}
				$this->debug($playerAPI, "lastX=" . $event->getFrom()->getX() . ", lastY=" . $event->getFrom()->getY() . ", lastZ=" . $event->getFrom()->getZ() . ", newX=" . $event->getTo()->getX() . ", newY=" . $event->getTo()->getY() . ", newZ=" . $event->getTo()->getZ());
			}
		}
	}
}