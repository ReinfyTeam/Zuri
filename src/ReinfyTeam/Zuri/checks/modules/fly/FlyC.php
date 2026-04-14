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
use function is_numeric;

/**
 * Detects prolonged airborne movement inconsistent with vanilla physics.
 */
class FlyC extends Check {
	/**
	 * Gets the check name.
	 */
	public function getName() : string {
		return "Fly";
	}

	/**
	 * Gets the check subtype identifier.
	 */
	public function getSubType() : string {
		return "C";
	}

	/**
	 * Handles movement events for FlyC detection.
	 *
	 * @param Event $event Triggered event instance.
	 * @param PlayerAPI $playerAPI Player state wrapper.
	 *
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
			$maxAirTicksRaw = $this->getConstant(CheckConstants::FLYC_MAX_AIR_TICKS);
			$snapshot->addCachedData("maxAirTicks", is_numeric($maxAirTicksRaw) ? (int) $maxAirTicksRaw : 0);
			$snapshot->addCachedData("maxY", $player->getWorld()->getHighestBlockAt((int) $newPos->getX(), (int) $newPos->getZ()));
			$snapshot->addCachedData("surroundingBlocks", BlockUtil::getSurroundingBlocks($player));
			$snapshot->addCachedData("ignoredCollisionBlockIds", [
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
				BlockTypeIds::STAINED_HARDENED_GLASS_PANE,
			]);

			$snapshot->validate();

			// Dispatch async check with snapshot payload
			$payload = $snapshot->build();
			$this->dispatchAsyncCheck($player->getName(), $payload);
		}
	}

	/**
	 * Evaluates the async payload for FlyC violations.
	 *
	 * @param array<string,mixed> $payload Serialized check context.
	 *
	 * @return array<string,mixed>
	 */
	public static function evaluateAsync(array $payload) : array {
    // Thread-safe: execute in async worker thread only; use only $payload (no Player objects)
    if (\pocketmine\thread\Thread::getCurrentThreadId() === 0) {
        throw new \RuntimeException("evaluateAsync must not be called on the main thread");
    }
		if (!MovementSnapshot::validatePayload(
			$payload,
			"FlyC",
			MovementSnapshot::SCHEMA_VERSION,
			["type", "schemaVersion", "cachedData"]
		)) {
			return [];
		}

		if (
			(is_numeric($payload["attackTicks"] ?? 0) ? (int) ($payload["attackTicks"] ?? 0) : 0) < 40 ||
			(is_numeric($payload["teleportTicks"] ?? 0) ? (int) ($payload["teleportTicks"] ?? 0) : 0) < 60 ||
			(is_numeric($payload["teleportCommandTicks"] ?? 0) ? (int) ($payload["teleportCommandTicks"] ?? 0) : 0) < 60 ||
			(is_numeric($payload["hurtTicks"] ?? 0) ? (int) ($payload["hurtTicks"] ?? 0) : 0) < 20 ||
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
			$oldYRaw = $cachedData["oldY"] ?? 0;
			$newYRaw = $cachedData["newY"] ?? 0;
			$oldY = is_numeric($oldYRaw) ? (float) $oldYRaw : 0.0;
			$newY = is_numeric($newYRaw) ? (float) $newYRaw : 0.0;
			if ($oldY <= $newY) {
				$inAirTicksRaw = $cachedData["inAirTicks"] ?? 0;
				$inAirTicks = is_numeric($inAirTicksRaw) ? (int) $inAirTicksRaw : 0;
				$maxAirTicksRaw = $cachedData["maxAirTicks"] ?? 0;
				$maxAirTicks = is_numeric($maxAirTicksRaw) ? (int) $maxAirTicksRaw : 0;
				if ($inAirTicks > $maxAirTicks) {
					$surroundingBlocks = $cachedData["surroundingBlocks"] ?? [];
					$ignoredCollisionBlockIds = is_array($cachedData["ignoredCollisionBlockIds"] ?? null) ? $cachedData["ignoredCollisionBlockIds"] : [];
					$maxYRaw = $cachedData["maxY"] ?? 0;
					$maxY = is_numeric($maxYRaw) ? (int) $maxYRaw : 0;
					$newYRaw = $cachedData["newY"] ?? 0;
					$newY = is_numeric($newYRaw) ? (float) $newYRaw : 0.0;
					if ($newY - 1 > $maxY) {
						if (!is_array($surroundingBlocks) || count(array_intersect($surroundingBlocks, $ignoredCollisionBlockIds)) === 0) {
							return ["failed" => true];
						}
					}
				}
			}
		}

		return [];
	}
}

