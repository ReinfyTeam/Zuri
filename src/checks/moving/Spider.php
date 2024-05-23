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

use pocketmine\block\BlockTypeIds;
use pocketmine\event\Event;
use pocketmine\event\player\PlayerMoveEvent;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use function abs;

class Spider extends Check {
	public function getName() : string {
		return "Spider";
	}

	public function getSubType() : string {
		return "A";
	}

	public function maxViolations() : int {
		return 5;
	}

	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if ($event instanceof PlayerMoveEvent) {
			$player = $playerAPI->getPlayer();
			if ($player === null) {
				return;
			}

			if (
				$playerAPI->getAttackTicks() < 40 ||
				$playerAPI->isInWeb() ||
				$playerAPI->isOnAdhesion() ||
				$player->getAllowFlight() ||
				$player->isFlying() ||
				$player->hasNoClientPredictions() ||
				!$playerAPI->isCurrentChunkIsLoaded()
			) {
				return;
			}

			$west = $player->getWorld()->getBlock($player->getPosition()->west())->isSolid() && $player->getWorld()->getBlock($player->getPosition()->west()->up())->isSolid();
			$south = $player->getWorld()->getBlock($player->getPosition()->south())->isSolid() && $player->getWorld()->getBlock($player->getPosition()->south()->up()->normalize())->isSolid();
			$east = $player->getWorld()->getBlock($player->getPosition()->east())->isSolid() && $player->getWorld()->getBlock($player->getPosition()->east()->up())->isSolid();
			$north = $player->getWorld()->getBlock($player->getPosition()->north())->isSolid() && $player->getWorld()->getBlock($player->getPosition()->north()->up())->isSolid();
			$onLadder = $player->getWorld()->getBlock($player->getPosition())->getTypeId() === BlockTypeIds::LADDER;

			if ($west || $south || $east || $north && !$onLadder) { // diagonals are solid and the player is not on ladder..
				$diff = abs($newY - $oldY);
				if ($newY > $oldY) { // if bigger newY > oldY
					if ($diff > $this->getConstant("limit-y-diff")) { // impossible :O y update 0.6~?
						$this->failed($playerAPI);
					}
				}
				$this->debug($playerAPI, "oldY=$oldY, newY=$newY, diffY=$diff, onLadder=$onLadder");
			}
		}
	}
}