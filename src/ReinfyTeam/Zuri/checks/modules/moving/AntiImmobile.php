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
use function is_numeric;

/**
 * Detects movement updates from clients that should be immobile.
 */
class AntiImmobile extends Check {
	/**
	 * Returns the check name.
	 *
	 * @return string Check identifier.
	 */
	public function getName() : string {
		return "AntiImmobile";
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
	 * Captures move events for anti-immobile evaluation.
	 *
	 * @param Event $event Triggered event.
	 * @param PlayerAPI $playerAPI Player context.
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

				$snapshot->validate();

				// Dispatch async check with snapshot payload
				$payload = $snapshot->build();
				$this->dispatchAsyncCheck($player->getName(), $payload);
			}
		}
	}

	/**
	 * Evaluates an async payload for anti-immobile violations.
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
			"AntiImmobile",
			MovementSnapshot::SCHEMA_VERSION,
			["type", "schemaVersion", "cachedData"]
		)) {
			return [];
		}

		$cachedData = (array) ($payload["cachedData"] ?? []);
		$fromXRaw = $cachedData["fromX"] ?? 0;
		$toXRaw = $cachedData["toX"] ?? 0;
		$fromYRaw = $cachedData["fromY"] ?? 0;
		$toYRaw = $cachedData["toY"] ?? 0;
		$fromZRaw = $cachedData["fromZ"] ?? 0;
		$toZRaw = $cachedData["toZ"] ?? 0;
		$fromX = is_numeric($fromXRaw) ? (float) $fromXRaw : 0.0;
		$toX = is_numeric($toXRaw) ? (float) $toXRaw : 0.0;
		$fromY = is_numeric($fromYRaw) ? (float) $fromYRaw : 0.0;
		$toY = is_numeric($toYRaw) ? (float) $toYRaw : 0.0;
		$fromZ = is_numeric($fromZRaw) ? (float) $fromZRaw : 0.0;
		$toZ = is_numeric($toZRaw) ? (float) $toZRaw : 0.0;
		if ($fromX !== $toX || $fromY !== $toY || $fromZ !== $toZ) {
			return ["failed" => true];
		}

		return [];
	}
}

