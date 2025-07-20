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
use pocketmine\math\Vector3;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\BlockUtil;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function array_flip;
use function array_keys;
use function intval;

class Phase extends Check {
	public function getName() : string {
		return "Phase";
	}

	public function getSubType() : string {
		return "A";
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if ($event instanceof PlayerMoveEvent) {
			$player = $event->getPlayer();
			$world = $player->getWorld();
			if (!$player->isConnected() || !$player->spawned) {
				return;
			}
			$id = $world->getBlock($player->getLocation()->add(0, -1, 0))->getTypeId();
			if ($world->getBlock($player->getLocation()->add(0, 1, 0))->isSolid() && $world->getBlock($player->getLocation()->add(0, -1, 0))->isSolid()) {
				$skipFlipped = array_flip([
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
					BlockTypeIds::BIRCH_SIGN,
					BlockTypeIds::DARK_OAK_SIGN,
					BlockTypeIds::JUNGLE_SIGN,
					BlockTypeIds::OAK_SIGN,
					BlockTypeIds::SPRUCE_SIGN,
					BlockTypeIds::MANGROVE_SIGN,
					BlockTypeIds::CRIMSON_SIGN,
					BlockTypeIds::WARPED_SIGN,
					BlockTypeIds::CHERRY_SIGN,
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
					BlockTypeIds::CHERRY_FENCE_GATE,
					BlockTypeIds::BEETROOTS,
					BlockTypeIds::CAKE,
					BlockTypeIds::CARROTS,
					BlockTypeIds::FIRE,
					BlockTypeIds::BAMBOO,
					BlockTypeIds::BAMBOO_SAPLING,
				]);

				if (
					$player->isSurvival()
					&& !$playerAPI->isOnCarpet()
					&& !$playerAPI->isOnPlate()
					&& !$playerAPI->isOnDoor()
					&& !$playerAPI->isOnSnow()
					&& !$playerAPI->isOnPlant()
					&& !$playerAPI->isOnAdhesion()
					&& !$playerAPI->isOnStairs()
					&& !$playerAPI->isInLiquid()
					&& !$playerAPI->isInWeb()
					&& !isset($skipFlipped[$id])
					&& $playerAPI->recentlyCancelledEvent() > 40 // fix cancelled events false-flagging.
					&& !BlockUtil::isUnderBlock($event->getTo(), array_keys($skipFlipped), 0)
				) {
					$this->failed($playerAPI);
					$x = intval($player->getLocation()->getX());
					$z = intval($player->getLocation()->getZ());
					$oldZ = $event->getFrom()->getZ();
					$oldX = $event->getFrom()->getX();
					$newZ = $event->getTo()->getZ();
					$newX = $event->getTo()->getX();
					if (($y = intval($player->getWorld()->getHighestBlockAt($x, $z))) > intval($player->getLocation()->getY()) && $oldZ === $newZ && $oldX === $newX) {
						$world->loadChunk(intval($newX), intval($newZ)); // the best hack thing to do before player teleports at the bottom of the block.
						$world->loadChunk(intval($oldX), intval($oldZ)); // the best hack thing to do before player teleports at the bottom of the block.
						$player->teleport(new Vector3($x, $y + 1, $z));
					}
					$event->cancel();
					$this->debug($playerAPI, "x=$x, y=" . intval($player->getLocation()->getY()) . ", z=$z, teleportY=$y");
				}
			}
		}
	}
}