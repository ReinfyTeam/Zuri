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

class ClickTP extends Check {
	public function getName() : string {
		return "ClickTP";
	}

	public function getSubType() : string {
		return "A";
	}

	/**
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
			$snapshot->addCachedData("maxDistance", (float) $this->getConstant(CheckConstants::CLICKTP_MAX_DISTANCE));

			$snapshot->validate();

			// Dispatch async check with snapshot payload
			$payload = $snapshot->build();
			$this->dispatchAsyncCheck($player->getName(), $payload);
		}
	}

	public static function evaluateAsync(array $payload) : array {
		if (
			($payload["type"] ?? null) !== "ClickTP" ||
			(int) ($payload["schemaVersion"] ?? 0) !== \ReinfyTeam\Zuri\checks\snapshots\MovementSnapshot::SCHEMA_VERSION
		) {
			return [];
		}

		$cachedData = (array) ($payload["cachedData"] ?? []);
		$distance = (float) ($cachedData["distance"] ?? 0);
		$oldYaw = (float) ($cachedData["oldYaw"] ?? 0);
		$newYaw = (float) ($cachedData["newYaw"] ?? 0);
		$oldPitch = (float) ($cachedData["oldPitch"] ?? 0);
		$newPitch = (float) ($cachedData["newPitch"] ?? 0);
		$maxDistance = (float) ($cachedData["maxDistance"] ?? 0);

		if ($distance > $maxDistance && $oldYaw === $newYaw && $oldPitch === $newPitch) {
			return ["failed" => true];
		}

		return [];
	}
}