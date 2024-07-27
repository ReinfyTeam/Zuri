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

namespace ReinfyTeam\Zuri\checks\blockplace\scaffold;

use pocketmine\block\BlockTypeIds;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\Event;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
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

    /**
     * @throws DiscordWebhookException
     */
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
				if ($x > $this->getConstant("box-range-x") || $y > $this->getConstant("box-range-y") || $z > $this->getConstant("box-range-z")) {
					$this->failed($playerAPI);
				}
			}
		}
	}
}
