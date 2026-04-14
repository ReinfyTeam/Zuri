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

namespace ReinfyTeam\Zuri\checks\modules\moving;

use pocketmine\event\Event;
use pocketmine\event\player\PlayerMoveEvent;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\checks\snapshots\MovementSnapshot;
use ReinfyTeam\Zuri\config\CheckConstants;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\BlockUtil;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function is_numeric;

/**
 * Detects teleport-like movement without corresponding rotation updates.
 */
class ClickTP extends Check {
	/**
	 * Returns the check name.
	 *
	 * @return string Check identifier.
	 */
	public function getName() : string {
		return "ClickTP";
	}

	/**
	 * Returns the check subtype.
	 *
	 * @return string Check subtype identifier.
	 */
	public function getSubType() : string {
		return "A";
	}

	/**
	 * Captures move data for click-teleport evaluation.
	 *
	 * @param Event $event Triggered event.
	 * @param PlayerAPI $playerAPI Player context.
	 * @throws DiscordWebhookException
	 */
	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if ($event instanceof PlayerMoveEvent) {
			$player = $playerAPI->getPlayer();
			$oldPos = $event->getFrom();
			$newPos = $event->getTo();
			$distance = $oldPos->distanceSquared($newPos);
			$oldYaw = $oldPos->getYaw();
			$newYaw = $newPos->getYaw();
			$oldPitch = $oldPos->getPitch();
			$newPitch = $newPos->getPitch();

			$snapshot = new MovementSnapshot("ClickTP", $player, $playerAPI);
			$snapshot->setEnvironmentState(
				BlockUtil::isGroundSolid($player),
				$playerAPI->isCurrentChunkIsLoaded(),
				$playerAPI->isRecentlyCancelledEvent()
			);

			// Add ClickTP-specific cached data
			$snapshot->addCachedData("distance", $distance);
			$snapshot->addCachedData("oldYaw", $oldYaw);
			$snapshot->addCachedData("newYaw", $newYaw);
			$snapshot->addCachedData("oldPitch", $oldPitch);
			$snapshot->addCachedData("newPitch", $newPitch);
			$maxDistanceRaw = $this->getConstant(CheckConstants::CLICKTP_MAX_DISTANCE);
			$snapshot->addCachedData("maxDistance", is_numeric($maxDistanceRaw) ? (float) $maxDistanceRaw : 0.0);

			$snapshot->validate();

			// Dispatch async check with snapshot payload
			$payload = $snapshot->build();
			$this->dispatchAsyncCheck($player->getName(), $payload);
		}
	}

	/**
	 * Evaluates an async payload for click-teleport violations.
	 *
	 * @param array<string,mixed> $payload Snapshot payload.
	 * @return array<string,mixed> Async decision data.
	 */
	public static function evaluateAsync(array $payload) : array {
    // Thread-safe: execute in async worker thread only; use only $payload (no Player objects)
    if (\pocketmine\thread\Thread::getCurrentThreadId() === 0) {
        throw new \RuntimeException("evaluateAsync must not be called on the main thread");
    }
		if (!MovementSnapshot::validatePayload(
			$payload,
			"ClickTP",
			MovementSnapshot::SCHEMA_VERSION,
			["type", "schemaVersion", "cachedData"]
		)) {
			return [];
		}

		$cachedData = (array) ($payload["cachedData"] ?? []);
		$distanceRaw = $cachedData["distance"] ?? 0;
		$oldYawRaw = $cachedData["oldYaw"] ?? 0;
		$newYawRaw = $cachedData["newYaw"] ?? 0;
		$oldPitchRaw = $cachedData["oldPitch"] ?? 0;
		$newPitchRaw = $cachedData["newPitch"] ?? 0;
		$maxDistanceRaw = $cachedData["maxDistance"] ?? 0;

		$distance = is_numeric($distanceRaw) ? (float) $distanceRaw : 0;
		$oldYaw = is_numeric($oldYawRaw) ? (float) $oldYawRaw : 0;
		$newYaw = is_numeric($newYawRaw) ? (float) $newYawRaw : 0;
		$oldPitch = is_numeric($oldPitchRaw) ? (float) $oldPitchRaw : 0;
		$newPitch = is_numeric($newPitchRaw) ? (float) $newPitchRaw : 0;
		$maxDistance = is_numeric($maxDistanceRaw) ? (float) $maxDistanceRaw : 0;

		if ($distance > $maxDistance && $oldYaw === $newYaw && $oldPitch === $newPitch) {
			return ["failed" => true];
		}

		return [];
	}
}

