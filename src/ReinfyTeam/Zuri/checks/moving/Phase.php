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
use ReinfyTeam\Zuri\utils\MathUtil;
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
		return true;
	}

	public function captcha() : bool {
		return false;
	}

	public function maxViolations() : int {
		return 8;
	}

	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if ($event instanceof PlayerMoveEvent) {
			$player = $event->getPlayer();
			$world = $player->getWorld();
			if (!$player->isConnected() || !$player->spawned) {
				return;
			}
			$id = $world->getBlock($player->getLocation()->add(0, -1, 0))->getTypeId();
			if ($world->getBlock($player->getLocation()->add(0, 1, 0))->isSolid() && $world->getBlock($player->getLocation()->add(0, -1, 0))->isSolid()) {
				$skip = [
					BlockTypeIds::SAND,
					BlockTypeIds::GRAVEL,
					BlockTypeIds::ANVIL,
					BlockTypeIds::AIR,
					BlockTypeIds::TORCH,
					BlockTypeIds::ACACIA_SIGN,
					BlockTypeIds::ACACIA_WALL_SIGN,
					BlockTypeIds::REDSTONE_TORCH,
					BlockTypeIds::REDSTONE_WIRE,
					BlockTypeIds::SEA_PICKLE,
					BlockTypeIds::REDSTONE_REPEATER,
					BlockTypeIds::LANTERN,
					BlockTypeIds::REDSTONE_COMPARATOR,
					BlockTypeIds::BIRCH_WALL_SIGN,
					BlockTypeIds::DARK_OAK_WALL_SIGN,
					BlockTypeIds::JUNGLE_WALL_SIGN,
					BlockTypeIds::OAK_WALL_SIGN,
					BlockTypeIds::SPRUCE_WALL_SIGN,
					BlockTypeIds::MANGROVE_WALL_SIGN,
					BlockTypeIds::CRIMSON_WALL_SIGN,
					BlockTypeIds::WARPED_WALL_SIGN,
					BlockTypeIds::CHERRY_WALL_SIGN,
					BlockTypeIds::ACACIA_SIGN,
					BlockTypeIds::ACACIA_WALL_SIGN,
					BlockTypeIds::BIRCH_SIGN,
					BlockTypeIds::BIRCH_WALL_SIGN,
					BlockTypeIds::DARK_OAK_SIGN,
					BlockTypeIds::DARK_OAK_WALL_SIGN,
					BlockTypeIds::JUNGLE_SIGN,
					BlockTypeIds::JUNGLE_WALL_SIGN,
					BlockTypeIds::OAK_SIGN,
					BlockTypeIds::OAK_WALL_SIGN,
					BlockTypeIds::SPRUCE_SIGN,
					BlockTypeIds::SPRUCE_WALL_SIGN,
					BlockTypeIds::MANGROVE_SIGN,
					BlockTypeIds::CRIMSON_SIGN,
					BlockTypeIds::WARPED_SIGN,
					BlockTypeIds::CHERRY_SIGN,
					BlockTypeIds::CHERRY_WALL_SIGN,
					BlockTypeIds::GLASS_PANE,
					BlockTypeIds::HARDENED_GLASS_PANE,
					BlockTypeIds::STAINED_GLASS_PANE,
					BlockTypeIds::STAINED_HARDENED_GLASS_PANE,
					BlockTypeIds::COBWEB,
					BlockTypeIds::BED,
					BlockTypeIds::BELL,
					BlockTypeIds::CACTUS,
					BlockTypeIds::CARPET,
					BlockTypeIds::COBBLESTONE_WALL,
					BlockTypeIds::ACACIA_FENCE,
					BlockTypeIds::OAK_FENCE,
					BlockTypeIds::BIRCH_FENCE,
					BlockTypeIds::DARK_OAK_FENCE,
					BlockTypeIds::JUNGLE_FENCE,
					BlockTypeIds::NETHER_BRICK_FENCE,
					BlockTypeIds::SPRUCE_FENCE,
					BlockTypeIds::WARPED_FENCE,
					BlockTypeIds::MANGROVE_FENCE,
					BlockTypeIds::CRIMSON_FENCE,
					BlockTypeIds::CHERRY_FENCE,
					BlockTypeIds::ACACIA_FENCE_GATE,
					BlockTypeIds::OAK_FENCE_GATE,
					BlockTypeIds::BIRCH_FENCE_GATE,
					BlockTypeIds::DARK_OAK_FENCE_GATE,
					BlockTypeIds::JUNGLE_FENCE_GATE,
					BlockTypeIds::SPRUCE_FENCE_GATE,
					BlockTypeIds::WARPED_FENCE_GATE,
					BlockTypeIds::MANGROVE_FENCE_GATE,
					BlockTypeIds::CRIMSON_FENCE_GATE,
					BlockTypeIds::CHERRY_FENCE_GATE
				];
				if (($d = MathUtil::XZDistanceSquared($event->getFrom(), $event->getTo())) > 0.1) { // this will fix player when it get stuck at the bottom
					if ($player->isSurvival() && !$playerAPI->isOnCarpet() && !$playerAPI->isOnPlate() && !$playerAPI->isOnDoor() && !$playerAPI->isOnSnow() && !$playerAPI->isOnPlant() && !$playerAPI->isOnAdhesion() && !$playerAPI->isOnStairs() && !$playerAPI->isInLiquid() && !$playerAPI->isInWeb() && !in_array($id, $skip, true) && !BlockUtil::isUnderBlock($event->getTo(), $skip, 0) && !in_array($id, $skip, true)) {
						$this->failed($playerAPI);
					}
				}
			}
		}
	}
}