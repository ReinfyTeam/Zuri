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

namespace ReinfyTeam\Zuri\checks\fly;

use pocketmine\block\BlockTypeIds;
use pocketmine\event\Event;
use pocketmine\event\player\PlayerMoveEvent;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\BlockUtil;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function array_intersect;
use function count;
use function intval;
use function is_array;

class FlyC extends Check {
	public function getName() : string {
		return "Fly";
	}

	public function getSubType() : string {
		return "C";
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if ($event instanceof PlayerMoveEvent) {
			$player = $playerAPI->getPlayer();
			$oldPos = $event->getFrom();
			$newPos = $event->getTo();
			$surroundingBlocks = BlockUtil::getSurroundingBlocks($player);
			if (
				$playerAPI->getAttackTicks() < 40 ||
				$playerAPI->isInWeb() ||
				$playerAPI->isOnGround() ||
				$playerAPI->isOnAdhesion() ||
				$player->getAllowFlight() ||
				$player->hasNoClientPredictions() ||
				!$player->isSurvival() ||
				!$playerAPI->isCurrentChunkIsLoaded() ||
				BlockUtil::isGroundSolid($player) ||
				$playerAPI->isGliding()
			) { // additional checks
				return;
			}
			if (!$player->isCreative() && !$player->isSpectator() && !$player->getAllowFlight()) {
				if ($oldPos->getY() <= $newPos->getY()) {
					if ($player->getInAirTicks() > $this->getConstant("max-air-ticks")) {
						$maxY = $player->getWorld()->getHighestBlockAt(intval($newPos->getX()), intval($newPos->getZ()));
						$this->debug($playerAPI, "oldY=" . $oldPos->getY() . ", newY=" . $newPos->getY() . ", airTicks=" . $player->getInAirTicks() . ", surroundingBlocks=" . count($surroundingBlocks));
						if ($newPos->getY() - 1 > $maxY) {
							if (!is_array($surroundingBlocks) || count(array_intersect($surroundingBlocks, [
								BlockTypeIds::OAK_FENCE,
								BlockTypeIds::COBBLESTONE_WALL,
								BlockTypeIds::ACACIA_FENCE,
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
								BlockTypeIds::CHERRY_FENCE_GATE,
								BlockTypeIds::GLASS_PANE,
								BlockTypeIds::HARDENED_GLASS_PANE,
								BlockTypeIds::STAINED_GLASS_PANE,
								BlockTypeIds::STAINED_HARDENED_GLASS_PANE
							])) === 0) {
								$this->failed($playerAPI);
							}
						}
					}
				}
			}
		}
	}
}