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
use function is_numeric;
use function microtime;

/**
 * Detects abnormal upward movement patterns associated with fly cheats.
 */
class FlyA extends Check {
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
		return "A";
	}

	/**
	 * Processes input packets for FlyA detection.
	 *
	 * @param DataPacket $packet Incoming network packet.
	 * @param PlayerAPI $playerAPI Player state wrapper.
	 *
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
		$maxGroundDiffRaw = $this->getConstant(CheckConstants::FLYA_MAX_GROUND_DIFF);
		$snapshot->addCachedData("maxGroundDiff", is_numeric($maxGroundDiffRaw) ? (float) $maxGroundDiffRaw : 0.0);

		$snapshot->validate();

		// Dispatch async check with snapshot payload
		$payload = $snapshot->build();
		$this->dispatchAsyncCheck($player->getName(), $payload);
	}

	/**
	 * Evaluates the async payload for FlyA violations.
	 *
	 * @param array<string,mixed> $payload Serialized check context.
	 *
	 * @return array<string,mixed>
	 */
	public static function evaluateAsync(array $payload) : array {
		if (!MovementSnapshot::validatePayload(
			$payload,
			"FlyA",
			MovementSnapshot::SCHEMA_VERSION,
			["type", "schemaVersion", "posY", "absMotionX", "absMotionZ", "cachedData"],
			[
				"posY" => [-2048.0, 2048.0],
				"absMotionX" => [0.0, 20.0],
				"absMotionZ" => [0.0, 20.0],
			]
		)) {
			return [];
		}

		// Extract movement snapshot fields
		$attackTicksRaw = $payload["attackTicks"] ?? 0;
		$onlineTimeRaw = $payload["onlineTime"] ?? 0;
		$jumpTicksRaw = $payload["jumpTicks"] ?? 0;
		$teleportTicksRaw = $payload["teleportTicks"] ?? 0;
		$teleportCommandTicksRaw = $payload["teleportCommandTicks"] ?? 0;
		$hurtTicksRaw = $payload["hurtTicks"] ?? 0;
		$absMotionXRaw = $payload["absMotionX"] ?? 0.0;
		$absMotionZRaw = $payload["absMotionZ"] ?? 0.0;
		$attackTicks = is_numeric($attackTicksRaw) ? (int) $attackTicksRaw : 0;
		$onlineTime = is_numeric($onlineTimeRaw) ? (int) $onlineTimeRaw : 0;
		$jumpTicks = is_numeric($jumpTicksRaw) ? (int) $jumpTicksRaw : 0;
		$teleportTicks = is_numeric($teleportTicksRaw) ? (int) $teleportTicksRaw : 0;
		$teleportCommandTicks = is_numeric($teleportCommandTicksRaw) ? (int) $teleportCommandTicksRaw : 0;
		$hurtTicks = is_numeric($hurtTicksRaw) ? (int) $hurtTicksRaw : 0;
		$absMotionX = is_numeric($absMotionXRaw) ? (float) $absMotionXRaw : 0.0;
		$absMotionZ = is_numeric($absMotionZRaw) ? (float) $absMotionZRaw : 0.0;

		if (
			$attackTicks < 40 ||
			$onlineTime <= 30 ||
			$jumpTicks < 40 ||
			$teleportTicks < 60 ||
			$teleportCommandTicks < 60 ||
			$hurtTicks < 20 ||
			(bool) ($payload["inWeb"] ?? false) ||
			(bool) ($payload["onGround"] ?? false) ||
			(bool) ($payload["onAdhesion"] ?? false) ||
			!(bool) ($payload["survival"] ?? false) ||
			!(bool) ($payload["chunkLoaded"] ?? false) ||
			(bool) ($payload["gliding"] ?? false) ||
			$absMotionX > 0.11 ||
			$absMotionZ > 0.11 ||
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
			$nowRaw = $cachedData["now"] ?? microtime(true);
			$lastTimeRaw = $lastTime;
			$lastYNoGroundRaw = $lastYNoGround;
			$now = is_numeric($nowRaw) ? (float) $nowRaw : microtime(true);
			$lastTimeValue = is_numeric($lastTimeRaw) ? (float) $lastTimeRaw : 0.0;
			$lastYNoGroundValue = is_numeric($lastYNoGroundRaw) ? (float) $lastYNoGroundRaw : 0.0;
			$diff = $now - $lastTimeValue;
			$result = ["debug" => "diff={$diff}, lastTime={$lastTime}, lastYNoGround={$lastYNoGround}"];
			$currentYRaw = $payload["posY"] ?? 0.0;
			$maxGroundDiffRaw = $cachedData["maxGroundDiff"] ?? 0.0;
			$currentY = is_numeric($currentYRaw) ? (float) $currentYRaw : 0.0;
			$maxGroundDiff = is_numeric($maxGroundDiffRaw) ? (float) $maxGroundDiffRaw : 0.0;
			if ($diff > $maxGroundDiff && abs($currentY - $lastYNoGroundValue) <= 0.001) {
				$result["failed"] = true;
			}
			$result["unset"] = [CacheData::FLY_A_LAST_Y_NO_GROUND, CacheData::FLY_A_LAST_TIME];
			return $result;
		}

		$posYRaw = $payload["posY"] ?? 0.0;
		$nowRaw = $cachedData["now"] ?? microtime(true);
		$posY = is_numeric($posYRaw) ? (float) $posYRaw : 0.0;
		$now = is_numeric($nowRaw) ? (float) $nowRaw : microtime(true);
		return ["set" => [CacheData::FLY_A_LAST_Y_NO_GROUND => $posY, CacheData::FLY_A_LAST_TIME => $now]];
	}
}
