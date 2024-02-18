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

namespace ReinfyTeam\Zuri\checks\fly;

use pocketmine\block\BlockTypeIds;
use pocketmine\event\Event;
use pocketmine\event\player\PlayerMoveEvent;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\BlockUtil;
use function in_array;
use function intval;

class FlyC extends Check {
	public function getName() : string {
		return "Fly";
	}

	public function getSubType() : string {
		return "C";
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

	public function checkJustEvent(Event $event, PlayerAPI $playerAPI) : void {
		if ($event instanceof PlayerMoveEvent) {
			$player = $playerAPI->getPlayer();
			$oldPos = $event->getFrom();
			$newPos = $event->getTo();
			$surroundingBlocks = BlockUtil::getSurroundingBlocks($player);
			if (
				$playerAPI->getAttackTicks() < 40 ||
				$playerAPI->getOnlineTime() <= 30 ||
				$playerAPI->getJumpTicks() < 40 ||
				$playerAPI->isInWeb() ||
				$playerAPI->isOnGround() ||
				$playerAPI->isOnAdhesion() ||
				$player->getAllowFlight() ||
				!$player->isSurvival()
			) { // additional checks
				return;
			}
			if (!$player->isCreative() && !$player->isSpectator() && !$player->getAllowFlight()) {
				if ($oldPos->getY() <= $newPos->getY()) {
					if ($player->getInAirTicks() > 40) {
						$maxY = $player->getWorld()->getHighestBlockAt(intval($newPos->getX()), intval($newPos->getZ()));
						if ($newPos->getY() - 2 > $maxY) {
							if (
								!in_array(BlockTypeIds::OAK_FENCE, $surroundingBlocks, true)
								|| !in_array(BlockTypeIds::COBBLESTONE_WALL, $surroundingBlocks, true)
								|| !in_array(BlockTypeIds::ACACIA_FENCE, $surroundingBlocks, true)
								|| !in_array(BlockTypeIds::OAK_FENCE, $surroundingBlocks, true)
								|| !in_array(BlockTypeIds::BIRCH_FENCE, $surroundingBlocks, true)
								|| !in_array(BlockTypeIds::DARK_OAK_FENCE, $surroundingBlocks, true)
								|| !in_array(BlockTypeIds::JUNGLE_FENCE, $surroundingBlocks, true)
								|| !in_array(BlockTypeIds::NETHER_BRICK_FENCE, $surroundingBlocks, true)
								|| !in_array(BlockTypeIds::SPRUCE_FENCE, $surroundingBlocks, true)
								|| !in_array(BlockTypeIds::WARPED_FENCE, $surroundingBlocks, true)
								|| !in_array(BlockTypeIds::MANGROVE_FENCE, $surroundingBlocks, true)
								|| !in_array(BlockTypeIds::CRIMSON_FENCE, $surroundingBlocks, true)
								|| !in_array(BlockTypeIds::CHERRY_FENCE, $surroundingBlocks, true)
								|| !in_array(BlockTypeIds::ACACIA_FENCE_GATE, $surroundingBlocks, true)
								|| !in_array(BlockTypeIds::OAK_FENCE_GATE, $surroundingBlocks, true)
								|| !in_array(BlockTypeIds::BIRCH_FENCE_GATE, $surroundingBlocks, true)
								|| !in_array(BlockTypeIds::DARK_OAK_FENCE_GATE, $surroundingBlocks, true)
								|| !in_array(BlockTypeIds::JUNGLE_FENCE_GATE, $surroundingBlocks, true)
								|| !in_array(BlockTypeIds::SPRUCE_FENCE_GATE, $surroundingBlocks, true)
								|| !in_array(BlockTypeIds::WARPED_FENCE_GATE, $surroundingBlocks, true)
								|| !in_array(BlockTypeIds::MANGROVE_FENCE_GATE, $surroundingBlocks, true)
								|| !in_array(BlockTypeIds::CRIMSON_FENCE_GATE, $surroundingBlocks, true)
								|| !in_array(BlockTypeIds::CHERRY_FENCE_GATE, $surroundingBlocks, true)
								|| !in_array(BlockTypeIds::GLASS_PANE, $surroundingBlocks, true)
								|| !in_array(BlockTypeIds::HARDENED_GLASS_PANE, $surroundingBlocks, true)
								|| !in_array(BlockTypeIds::STAINED_GLASS_PANE, $surroundingBlocks, true)
								|| !in_array(BlockTypeIds::STAINED_HARDENED_GLASS_PANE, $surroundingBlocks, true)
							) {
								$this->failed($playerAPI);
							}
						}
					}
				}
			}
		}
	}
}