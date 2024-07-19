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

namespace ReinfyTeam\Zuri\checks\blockbreak;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\Event;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;

class Breaker extends Check {
	public function getName() : string {
		return "Breaker";
	}

	public function getSubType() : string {
		return "A";
	}

	public function maxViolations() : int {
		return 5;
	}

	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if ($event instanceof BlockBreakEvent) {
			$block = $event->getBlock();
			$player = $playerAPI->getPlayer();
			$playerPos = $player->getPosition();
			$eyePos = $player->getEyePos();
			$world = $player->getWorld();
			if ($block instanceof Bed) {
				$distance = $playerPos->distance($block->getPosition());
				if ($distance > $this->getConstant("max-range")) {
					$this->debug($playerAPI, "distance=$distance");
					$this->failed($playerAPI);
					return;
				}

				$direction = $blockPos->subtract($eyePos)->normalize();
				if (!$eyePos->floor()->equals($blockPos->floor())) {
					$this->failed($playerAPI);
					return;
				}

				while ($playerPos->distance($blockPos) > 1) {
					$currentPos = $playerPos->add($direction);

					$blockAtCurrentPos = $world->getBlock($currentPos->floor());

					if (!$blockAtCurrentPos->isSolid()) {
						$this->failed($playerAPI);
						return;
					}
				}
			}
		}
	}
}