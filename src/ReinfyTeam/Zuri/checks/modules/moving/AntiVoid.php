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

namespace ReinfyTeam\Zuri\checks\modules\moving\speed;

use pocketmine\network\mcpe\protocol\DataPacket;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\checks\snapshots\MovementSnapshot;
use ReinfyTeam\Zuri\config\CacheData;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\BlockUtil;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;

/**
 * Detects upward corrections used to avoid falling into the void.
 */
class AntiVoid extends Check {
	/**
	 * Returns the check name.
	 *
	 * @return string Check identifier.
	 */
	public function getName() : string {
		return "AntiVoid";
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
	 * Captures movement state for anti-void evaluation.
	 *
	 * @param DataPacket $packet Incoming packet.
	 * @param PlayerAPI $playerAPI Player context.
	 * @throws DiscordWebhookException
	 */
	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		if (
			$playerAPI->isOnAdhesion() ||
			$playerAPI->isInLiquid() ||
			$playerAPI->isInWeb() ||
			$playerAPI->getDeathTicks() < 100 ||
			$playerAPI->getJumpTicks() < 60 ||
			$playerAPI->getTeleportCommandTicks() < 100 ||
			$playerAPI->isRecentlyCancelledEvent()
		) {
			return;
		}

		$player = $playerAPI->getPlayer();
		$snapshot = new MovementSnapshot("AntiVoid", $player, $playerAPI);
		$snapshot->setEnvironmentState(
			BlockUtil::isGroundSolid($player),
			$playerAPI->isCurrentChunkIsLoaded(),
			$playerAPI->isRecentlyCancelledEvent()
		);

		// Add AntiVoid-specific cached data
		$lastY = $playerAPI->getExternalData(CacheData::ANTIVOID_LAST_Y);
		$currentY = $player->getLocation()->getY();
		$snapshot->addCachedData("lastY", $lastY);
		$snapshot->addCachedData("currentY", $currentY);

		$playerAPI->setExternalData(CacheData::ANTIVOID_LAST_Y, $currentY);

		$snapshot->validate();

		// Dispatch async check with snapshot payload
		$payload = $snapshot->build();
		$this->dispatchAsyncCheck($player->getName(), $payload);
	}

	/**
	 * Evaluates an async payload for anti-void violations.
	 *
	 * @param array<string,mixed> $payload Snapshot payload.
	 * @return array<string,mixed> Async decision data.
	 */
	public static function evaluateAsync(array $payload) : array {
		if (!MovementSnapshot::validatePayload(
			$payload,
			"AntiVoid",
			MovementSnapshot::SCHEMA_VERSION,
			["type", "schemaVersion", "onGround", "cachedData"]
		)) {
			return [];
		}

		$cachedData = (array) ($payload["cachedData"] ?? []);
		$lastY = $cachedData["lastY"] ?? null;
		if ($lastY !== null && (bool) ($payload["onGround"] ?? false)) {
			if ($lastY < (float) ($cachedData["currentY"] ?? 0)) {
				return ["failed" => true];
			}
		}

		return [];
	}
}
