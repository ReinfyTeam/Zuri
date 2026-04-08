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

use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\checks\snapshots\MovementSnapshot;
use ReinfyTeam\Zuri\config\CacheData;
use ReinfyTeam\Zuri\config\CheckConstants;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\BlockUtil;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function abs;
use function microtime;

class FlyA extends Check {
	public function getName() : string {
		return "Fly";
	}

	public function getSubType() : string {
		return "A";
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		if (!$packet instanceof PlayerAuthInputPacket) {
			return;
		}

		$player = $playerAPI->getPlayer();

		// Build movement snapshot with FlyA-specific data
		$snapshot = new MovementSnapshot("FlyA", $player, $playerAPI);
		$snapshot->setEnvironmentState(
			BlockUtil::isGroundSolid($player),
			$playerAPI->isCurrentChunkIsLoaded(),
			$playerAPI->isRecentlyCancelledEvent()
		);

		// Add FlyA-specific cached data
		$snapshot->addCachedData("allowFlight", $player->getAllowFlight());
		$snapshot->addCachedData("noClientPredictions", $player->hasNoClientPredictions());
		$snapshot->addCachedData("lastYNoGround", $playerAPI->getExternalData(CacheData::FLY_A_LAST_Y_NO_GROUND));
		$snapshot->addCachedData("lastTime", $playerAPI->getExternalData(CacheData::FLY_A_LAST_TIME));
		$snapshot->addCachedData("now", microtime(true));
		$snapshot->addCachedData("maxGroundDiff", (float) $this->getConstant(CheckConstants::FLYA_MAX_GROUND_DIFF));

		$snapshot->validate();

		// Dispatch async check with snapshot payload
		$payload = $snapshot->build();
		$this->dispatchAsyncCheck($player->getName(), $payload);
	}

	public static function evaluateAsync(array $payload) : array {
		if (
			($payload["type"] ?? null) !== "FlyA" ||
			(int) ($payload["schemaVersion"] ?? 0) !== \ReinfyTeam\Zuri\checks\snapshots\MovementSnapshot::SCHEMA_VERSION
		) {
			return [];
		}

		// Extract movement snapshot fields
		if (
			(int) ($payload["attackTicks"] ?? 0) < 40 ||
			(int) ($payload["onlineTime"] ?? 0) <= 30 ||
			(int) ($payload["jumpTicks"] ?? 0) < 40 ||
			(int) ($payload["teleportTicks"] ?? 0) < 60 ||
			(int) ($payload["teleportCommandTicks"] ?? 0) < 60 ||
			(int) ($payload["hurtTicks"] ?? 0) < 20 ||
			(bool) ($payload["inWeb"] ?? false) ||
			(bool) ($payload["onGround"] ?? false) ||
			(bool) ($payload["onAdhesion"] ?? false) ||
			!(bool) ($payload["survival"] ?? false) ||
			!(bool) ($payload["chunkLoaded"] ?? false) ||
			(bool) ($payload["gliding"] ?? false) ||
			(float) ($payload["absMotionX"] ?? 0.0) > 0.11 ||
			(float) ($payload["absMotionZ"] ?? 0.0) > 0.11 ||
			(bool) ($payload["recentlyCancelled"] ?? false)
		) {
			return ["unset" => [CacheData::FLY_A_LAST_Y_NO_GROUND, CacheData::FLY_A_LAST_TIME]];
		}

		// Extract FlyA-specific cached data
		$cachedData = (array) ($payload["cachedData"] ?? []);
		$allowFlight = (bool) ($cachedData["allowFlight"] ?? false);
		$noClientPredictions = (bool) ($cachedData["noClientPredictions"] ?? false);

		if ($allowFlight || $noClientPredictions) {
			return ["unset" => [CacheData::FLY_A_LAST_Y_NO_GROUND, CacheData::FLY_A_LAST_TIME]];
		}

		$lastYNoGround = $cachedData["lastYNoGround"] ?? null;
		$lastTime = $cachedData["lastTime"] ?? null;
		if ($lastYNoGround !== null && $lastTime !== null) {
			$now = (float) ($cachedData["now"] ?? microtime(true));
			$diff = $now - (float) $lastTime;
			$result = ["debug" => "diff={$diff}, lastTime={$lastTime}, lastYNoGround={$lastYNoGround}"];
			$currentY = (float) ($payload["posY"] ?? 0.0);
			$maxGroundDiff = (float) ($cachedData["maxGroundDiff"] ?? 0.0);
			if ($diff > $maxGroundDiff && abs($currentY - (float) $lastYNoGround) <= 0.001) {
				$result["failed"] = true;
			}
			$result["unset"] = [CacheData::FLY_A_LAST_Y_NO_GROUND, CacheData::FLY_A_LAST_TIME];
			return $result;
		}

		return ["set" => [CacheData::FLY_A_LAST_Y_NO_GROUND => (float) ($payload["posY"] ?? 0.0), CacheData::FLY_A_LAST_TIME => (float) ($cachedData["now"] ?? microtime(true))]];
	}
}