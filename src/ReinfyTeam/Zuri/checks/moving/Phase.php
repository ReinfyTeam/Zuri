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

namespace ReinfyTeam\Zuri\checks\moving;

use pocketmine\block\BlockTypeIds;
use pocketmine\event\Event;
use pocketmine\event\player\PlayerMoveEvent;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\BlockUtil;
use function in_array;

class Phase extends Check {
	public function getName() : string {
		return "Phase";
	}

	public function getSubType() : string {
		return "A";
	}

	public function enable() : bool {
		return true;
	}

	public function ban() : bool {
		return false;
	}

	public function kick() : bool {
		return true;
	}

	public function flag() : bool {
		return false;
	}

	public function captcha() : bool {
		return false;
	}

	public function maxViolations() : int {
		return 1;
	}

	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if ($event instanceof PlayerMoveEvent) {
			$player = $event->getPlayer();
			$world = $player->getWorld();
			$block = $world->getBlock($player->getLocation()->asVector3()->add(0, 0.75, 0));
			$skip = [BlockTypeIds::SAND, BlockTypeIds::GRAVEL, BlockTypeIds::ANVIL, BlockTypeIds::AIR];
			$skip2 = [BlockTypeIds::TORCH, BlockTypeIds::ACACIA_SIGN, BlockTypeIds::ACACIA_WALL_SIGN, BlockTypeIds::REDSTONE_TORCH, BlockTypeIds::REDSTONE_WIRE, BlockTypeIds::SEA_PICKLE, BlockTypeIds::REDSTONE_REPEATER, BlockTypeIds::LANTERN, BlockTypeIds::REDSTONE_COMPARATOR, BlockTypeIds::BIRCH_WALL_SIGN, BlockTypeIds::DARK_OAK_WALL_SIGN, BlockTypeIds::JUNGLE_WALL_SIGN, BlockTypeIds::OAK_WALL_SIGN, BlockTypeIds::SPRUCE_WALL_SIGN, BlockTypeIds::MANGROVE_WALL_SIGN, BlockTypeIds::CRIMSON_WALL_SIGN, BlockTypeIds::WARPED_WALL_SIGN, BlockTypeIds::CHERRY_WALL_SIGN, BlockTypeIds::ACACIA_SIGN, BlockTypeIds::ACACIA_WALL_SIGN, BlockTypeIds::BIRCH_SIGN, BlockTypeIds::BIRCH_WALL_SIGN, BlockTypeIds::DARK_OAK_SIGN, BlockTypeIds::DARK_OAK_WALL_SIGN, BlockTypeIds::JUNGLE_SIGN, BlockTypeIds::JUNGLE_WALL_SIGN, BlockTypeIds::OAK_SIGN, BlockTypeIds::OAK_WALL_SIGN, BlockTypeIds::SPRUCE_SIGN, BlockTypeIds::SPRUCE_WALL_SIGN, BlockTypeIds::MANGROVE_SIGN, BlockTypeIds::CRIMSON_SIGN, BlockTypeIds::WARPED_SIGN, BlockTypeIds::CHERRY_SIGN, BlockTypeIds::CHERRY_WALL_SIGN];
			if ($player->isSurvival() && !$playerAPI->isOnCarpet() && !$playerAPI->isOnPlate() && !$playerAPI->isOnDoor() && !$playerAPI->isOnSnow() && !$playerAPI->isOnPlant() && !$playerAPI->isOnAdhesion() && !$playerAPI->isOnStairs() && !$playerAPI->isInLiquid() && !$playerAPI->isInWeb() && !in_array($block->getId(), $skip, true) && !BlockUtil::isUnderBlock($event->getTo(), $skip2, 0)) {
				$event->cancel();
			}
		}
	}
}