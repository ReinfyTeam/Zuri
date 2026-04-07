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
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\BlockUtil;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;

class AntiImmobile extends Check {
	public function getName() : string {
		return "AntiImmobile";
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
			if ($player->hasNoClientPredictions()) {
				if ($playerAPI->getTeleportCommandTicks() < 40 || $playerAPI->getOnlineTime() < 2 || $playerAPI->isRecentlyCancelledEvent()) {
					return;
				}

				$snapshot = new MovementSnapshot("AntiImmobile", $player, $playerAPI);
				$snapshot->setEnvironmentState(
					BlockUtil::isGroundSolid($player),
					$playerAPI->isCurrentChunkIsLoaded(),
					$playerAPI->isRecentlyCancelledEvent()
				);

				// Add AntiImmobile-specific cached data
				$snapshot->addCachedData("fromX", $event->getFrom()->getX());
				$snapshot->addCachedData("fromY", $event->getFrom()->getY());
				$snapshot->addCachedData("fromZ", $event->getFrom()->getZ());
				$snapshot->addCachedData("toX", $event->getTo()->getX());
				$snapshot->addCachedData("toY", $event->getTo()->getY());
				$snapshot->addCachedData("toZ", $event->getTo()->getZ());

				// Dispatch async check with snapshot payload
				$payload = $snapshot->build();
				$this->dispatchAsyncCheck($player->getName(), $payload);
			}
		}
	}

	public static function evaluateAsync(array $payload) : array {
		if (($payload["type"] ?? null) !== "AntiImmobile") {
			return [];
		}

		$cachedData = (array) ($payload["cachedData"] ?? []);
		if ((float) ($cachedData["fromX"] ?? 0) !== (float) ($cachedData["toX"] ?? 0) ||
			(float) ($cachedData["fromY"] ?? 0) !== (float) ($cachedData["toY"] ?? 0) ||
			(float) ($cachedData["fromZ"] ?? 0) !== (float) ($cachedData["toZ"] ?? 0)) {
			return ["failed" => true];
		}

		return [];
	}
}