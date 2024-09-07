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

use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\Event;
use pocketmine\network\mcpe\protocol\DataPacket;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use ReinfyTeam\Zuri\utils\MathUtil;
use function abs;

class ScaffoldC extends Check {
	public function getName() : string {
		return "Scaffold";
	}

	public function getSubType() : string {
		return "C";
	}

	public function maxViolations() : int {
		return 10;
	}

	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if ($event instanceof BlockPlaceEvent) {
			$block = $event->getBlockAgainst();
			$posBlock = $block->getPosition();
			$posPlayer = $playerAPI->getLocation();
			$distance = MathUtil::distance($posPlayer->asVector3(), $posBlock->asVector3());
			$this->debug($playerAPI, "distance=$distance, pitch=" . abs($posPlayer->getPitch()));
			if ($distance < $this->getConstant("max-place-distance") && abs($posPlayer->getPitch()) > 90) {
				$this->failed($playerAPI);
			}
		}
	}
}