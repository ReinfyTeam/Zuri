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

namespace ReinfyTeam\Zuri\checks\modules\fly;

use pocketmine\block\BlockTypeIds;
use pocketmine\event\Event;
use pocketmine\event\player\PlayerMoveEvent;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\checks\snapshots\MovementSnapshot;
use ReinfyTeam\Zuri\config\CheckConstants;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\BlockUtil;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function array_intersect;
use function count;
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

			$snapshot = new MovementSnapshot("FlyC", $player, $playerAPI);
			$snapshot->setEnvironmentState(
				BlockUtil::isGroundSolid($player),
				$playerAPI->isCurrentChunkIsLoaded(),
				$playerAPI->isRecentlyCancelledEvent()
			);

			// Add FlyC-specific cached data
			$snapshot->addCachedData("inWeb", $playerAPI->isInWeb());
			$snapshot->addCachedData("allowFlight", $player->getAllowFlight());
			$snapshot->addCachedData("noClientPredictions", $player->hasNoClientPredictions());
			$snapshot->addCachedData("creative", $player->isCreative());
			$snapshot->addCachedData("spectator", $player->isSpectator());
			$snapshot->addCachedData("inAirTicks", $player->getInAirTicks());
			$snapshot->addCachedData("oldY", $oldPos->getY());
			$snapshot->addCachedData("newY", $newPos->getY());
			$snapshot->addCachedData("maxAirTicks", (int) $this->getConstant(CheckConstants::FLYC_MAX_AIR_TICKS));
			$snapshot->addCachedData("maxY", $player->getWorld()->getHighestBlockAt((int) $newPos->getX(), (int) $newPos->getZ()));
			$snapshot->addCachedData("surroundingBlocks", BlockUtil::getSurroundingBlocks($player));

			$snapshot->validate();

			// Dispatch async check with snapshot payload
			$payload = $snapshot->build();
			$this->dispatchAsyncCheck($player->getName(), $payload);
		}
	}

	public static function evaluateAsync(array $payload) : array {
		if (!MovementSnapshot::validatePayload(
			$payload,
			"FlyC",
			MovementSnapshot::SCHEMA_VERSION,
			["type", "schemaVersion", "cachedData"]
		)) {
			return [];
		}

		if (
			(int) ($payload["attackTicks"] ?? 0) < 40 ||
			(int) ($payload["teleportTicks"] ?? 0) < 60 ||
			(int) ($payload["teleportCommandTicks"] ?? 0) < 60 ||
			(int) ($payload["hurtTicks"] ?? 0) < 20 ||
			(bool) ($payload["inWeb"] ?? false) ||
			(bool) ($payload["onGround"] ?? false) ||
			(bool) ($payload["onAdhesion"] ?? false) ||
			(bool) ($payload["recentlyCancelled"] ?? false)
		) {
			return [];
		}

		$cachedData = (array) ($payload["cachedData"] ?? []);
		if (
			(bool) ($cachedData["allowFlight"] ?? false) ||
			(bool) ($cachedData["noClientPredictions"] ?? false) ||
			!(bool) ($payload["survival"] ?? false) ||
			!(bool) ($payload["chunkLoaded"] ?? false) ||
			(bool) ($payload["groundSolid"] ?? false) ||
			(bool) ($payload["gliding"] ?? false)
		) {
			return [];
		}

		if (!(bool) ($cachedData["creative"] ?? false) && !(bool) ($cachedData["spectator"] ?? false) && !(bool) ($cachedData["allowFlight"] ?? false)) {
			if ((float) ($cachedData["oldY"] ?? 0) <= (float) ($cachedData["newY"] ?? 0)) {
				if ((int) ($cachedData["inAirTicks"] ?? 0) > (int) ($cachedData["maxAirTicks"] ?? 0)) {
					$surroundingBlocks = $cachedData["surroundingBlocks"] ?? [];
					$maxY = (int) ($cachedData["maxY"] ?? 0);
					$newY = (float) ($cachedData["newY"] ?? 0);
					if ($newY - 1 > $maxY) {
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
							return ["failed" => true];
						}
					}
				}
			}
		}

		return [];
	}
}