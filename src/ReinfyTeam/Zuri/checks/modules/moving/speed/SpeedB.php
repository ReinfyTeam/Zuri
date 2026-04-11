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

use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\Event;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\math\Vector3;
use pocketmine\Server;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\checks\snapshots\MovementSnapshot;
use ReinfyTeam\Zuri\config\CacheData;
use ReinfyTeam\Zuri\config\CheckConstants;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\BlockUtil;
use function abs;
use function is_array;
use function is_int;
use function is_numeric;
use function max;
use function round;

/**
 * Detects excessive horizontal speed using tick-based movement snapshots.
 */
class SpeedB extends Check {
	/**
	 * Returns the check name.
	 *
	 * @return string Check identifier.
	 */
	public function getName() : string {
		return "Speed";
	}

	/**
	 * Returns the check subtype.
	 *
	 * @return string Check subtype identifier.
	 */
	public function getSubType() : string {
		return "B";
	}

	/**
	 * Returns the pre-violation cap for this check.
	 *
	 * @return int Maximum pre-violations.
	 */
	public function maxViolations() : int {
		return 4;
	}

	/**
	 * Captures move events for Speed B async evaluation.
	 *
	 * @param Event $event Triggered event.
	 * @param PlayerAPI $playerAPI Player context.
	 */
	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		$player = $playerAPI->getPlayer();
		if ($event instanceof PlayerMoveEvent) {
			if (
				abs($event->getTo()->getX() - $event->getFrom()->getX()) < 0.0001 &&
				abs($event->getTo()->getY() - $event->getFrom()->getY()) < 0.0001 &&
				abs($event->getTo()->getZ() - $event->getFrom()->getZ()) < 0.0001
			) {
				return;
			}

			$groundSolid = BlockUtil::isGroundSolid($player);
			if (
				!$player->isSurvival() ||
				$playerAPI->getAttackTicks() < 40 ||
				$playerAPI->getProjectileAttackTicks() < 20 ||
				$playerAPI->getBowShotTicks() < 20 ||
				$playerAPI->getHurtTicks() < 10 ||
				$playerAPI->getTeleportTicks() < 60 ||
				$playerAPI->getSlimeBlockTicks() < 20 ||
				$playerAPI->getTeleportCommandTicks() < 40 ||
				$playerAPI->getOnlineTime() < 2 ||
				$playerAPI->isOnAdhesion() ||
				!$player->isOnGround() ||
				$player->isFlying() ||
				$player->getAllowFlight() ||
				$player->hasNoClientPredictions() ||
				!$playerAPI->isCurrentChunkIsLoaded() ||
				$groundSolid ||
				$playerAPI->isGliding() ||
				$playerAPI->isRecentlyCancelledEvent()
			) {
				return;
			}

			$currentTick = Server::getInstance()->getTick();
			$lastTick = $playerAPI->getExternalData(CacheData::SPEED_B_LAST_SERVER_TICK);
			$playerAPI->setExternalData(CacheData::SPEED_B_LAST_SERVER_TICK, $currentTick);
			if (!is_int($lastTick)) {
				return;
			}

			$tickDiff = $currentTick - $lastTick;
			if ($tickDiff <= 0) {
				return;
			}

			$snapshot = new MovementSnapshot("SpeedB", $player, $playerAPI);
			$snapshot->setEnvironmentState(
				$groundSolid,
				$playerAPI->isCurrentChunkIsLoaded(),
				$playerAPI->isRecentlyCancelledEvent()
			);

			// Add SpeedB-specific cached data
			$snapshot->addCachedData("fromX", $event->getFrom()->getX());
			$snapshot->addCachedData("fromY", $event->getFrom()->getY());
			$snapshot->addCachedData("fromZ", $event->getFrom()->getZ());
			$snapshot->addCachedData("toX", $event->getTo()->getX());
			$snapshot->addCachedData("toY", $event->getTo()->getY());
			$snapshot->addCachedData("toZ", $event->getTo()->getZ());
			$snapshot->addCachedData("tickDiff", $tickDiff);
			$snapshot->addCachedData("sprinting", $player->isSprinting());
			$snapshot->addCachedData("jumpTicks", $playerAPI->getJumpTicks());
			$snapshot->addCachedData("onIce", $playerAPI->isOnIce());
			$snapshot->addCachedData("topBlock", $playerAPI->isTopBlock());
			$snapshot->addCachedData("onStairs", $playerAPI->isOnStairs());
			$snapshot->addCachedData("speedEffectLevel", ($effect = $player->getEffects()->get(VanillaEffects::SPEED())) !== null ? $effect->getEffectLevel() : 0);
			$snapshot->addCachedData("ping", $playerAPI->getPing());
			$snapshot->addCachedData("constants", [
				"walking-speed-limit" => $this->getConstant(CheckConstants::SPEEDB_WALKING_SPEED_LIMIT),
				"sprinting-speed-limit" => $this->getConstant(CheckConstants::SPEEDB_SPRINTING_SPEED_LIMIT),
				"jump-speed-limit" => $this->getConstant(CheckConstants::SPEEDB_JUMP_SPEED_LIMIT),
				"ice-walking-speed-limit" => $this->getConstant(CheckConstants::SPEEDB_ICE_WALKING_SPEED_LIMIT),
				"top-block-limit" => $this->getConstant(CheckConstants::SPEEDB_TOP_BLOCK_LIMIT),
				"stairs-speed-limit" => $this->getConstant(CheckConstants::SPEEDB_STAIRS_SPEED_LIMIT),
				"time-limit" => $this->getConstant(CheckConstants::SPEEDB_TIME_LIMIT),
				"wakling-distance-limit" => $this->getConstant(CheckConstants::SPEEDB_WAKLING_DISTANCE_LIMIT),
				"sprinting-distance-limit" => $this->getConstant(CheckConstants::SPEEDB_SPRINTING_DISTANCE_LIMIT),
				"jump-distance-limit" => $this->getConstant(CheckConstants::SPEEDB_JUMP_DISTANCE_LIMIT),
				"ice-walking-distance-limit" => $this->getConstant(CheckConstants::SPEEDB_ICE_WALKING_DISTANCE_LIMIT),
				"stairs-walking-distance-limit" => $this->getConstant(CheckConstants::SPEEDB_STAIRS_WALKING_DISTANCE_LIMIT),
				"speed-effect-limit" => $this->getConstant(CheckConstants::SPEEDB_SPEED_EFFECT_LIMIT),
				"time-effect-limit" => $this->getConstant(CheckConstants::SPEEDB_TIME_EFFECT_LIMIT),
				"speed-effect-distance-limit" => $this->getConstant(CheckConstants::SPEEDB_SPEED_EFFECT_DISTANCE_LIMIT),
				"max-lag-ticks" => 8,
				"pingLagging" => self::getData(self::PING_LAGGING),
			]);

			// Dispatch async check with snapshot payload
			$payload = $snapshot->build();
			$this->dispatchAsyncCheck($player->getName(), $payload);
		}
	}

	/**
	 * Evaluates an async payload for Speed B violations.
	 *
	 * @param array<string,mixed> $payload Snapshot payload.
	 * @return array<string,mixed> Async decision data.
	 */
	public static function evaluateAsync(array $payload) : array {
		if (!MovementSnapshot::validatePayload(
			$payload,
			"SpeedB",
			MovementSnapshot::SCHEMA_VERSION,
			["type", "schemaVersion", "survival", "onGround", "chunkLoaded", "cachedData"],
			[
				"attackTicks" => [0.0, 120000.0],
				"teleportTicks" => [0.0, 120000.0],
				"hurtTicks" => [0.0, 120000.0],
			]
		)) {
			return [];
		}

		if (!(bool) ($payload["survival"] ?? false) || !(bool) ($payload["onGround"] ?? false) || !(bool) ($payload["chunkLoaded"] ?? false)) {
			return [];
		}

		$attackTicksRaw = $payload["attackTicks"] ?? 0;
		$teleportTicksRaw = $payload["teleportTicks"] ?? 0;
		$hurtTicksRaw = $payload["hurtTicks"] ?? 0;
		$teleportCommandTicksRaw = $payload["teleportCommandTicks"] ?? 0;
		$attackTicks = is_numeric($attackTicksRaw) ? (int) $attackTicksRaw : 0;
		$teleportTicks = is_numeric($teleportTicksRaw) ? (int) $teleportTicksRaw : 0;
		$hurtTicks = is_numeric($hurtTicksRaw) ? (int) $hurtTicksRaw : 0;
		$teleportCommandTicks = is_numeric($teleportCommandTicksRaw) ? (int) $teleportCommandTicksRaw : 0;

		if (
			$attackTicks < 40 ||
			$teleportTicks < 60 ||
			$hurtTicks < 10 ||
			$teleportCommandTicks < 40 ||
			(bool) ($payload["onAdhesion"] ?? false) ||
			(bool) ($payload["gliding"] ?? false) ||
			(bool) ($payload["recentlyCancelled"] ?? false)
		) {
			return [];
		}

		$cachedData = (array) ($payload["cachedData"] ?? []);
		$jumpTicksRaw = $cachedData["jumpTicks"] ?? 0;
		$jumpTicks = is_numeric($jumpTicksRaw) ? (int) $jumpTicksRaw : 0;
		if ($jumpTicks >= 40) {
			return [];
		}

		// Additional checks for projectile attack and bow shot ticks
		$projectileAttackTicksRaw = $payload["projectileAttackTicks"] ?? 0;
		$projectileAttackTicks = is_numeric($projectileAttackTicksRaw) ? (int) $projectileAttackTicksRaw : 0;
		$bowShotTicksRaw = $payload["bowShotTicks"] ?? 0;
		$bowShotTicks = is_numeric($bowShotTicksRaw) ? (int) $bowShotTicksRaw : 0;
		if ($projectileAttackTicks < 20 || $bowShotTicks < 20) {
			return [];
		}

		// Check for other problematic states
		$slimeBlockTicksRaw = $payload["slimeBlockTicks"] ?? 0;
		$slimeBlockTicks = is_numeric($slimeBlockTicksRaw) ? (int) $slimeBlockTicksRaw : 0;
		$onlineTimeRaw = $payload["onlineTime"] ?? 0;
		$onlineTime = is_numeric($onlineTimeRaw) ? (float) $onlineTimeRaw : 0.0;
		if (
			$slimeBlockTicks < 20 ||
			$onlineTime < 2 ||
			(bool) ($payload["flying"] ?? false) ||
			(bool) ($payload["allowFlight"] ?? false) ||
			(bool) ($payload["noClientPredictions"] ?? false) ||
			(bool) ($payload["groundSolid"] ?? false)
		) {
			return [];
		}

		$tickDiffRaw = $cachedData["tickDiff"] ?? 0;
		$tickDiff = is_numeric($tickDiffRaw) ? (int) $tickDiffRaw : 0;
		if ($tickDiff <= 0) {
			return [];
		}

		$timeDiff = $tickDiff / 20;
		$from = new Vector3((float) ($cachedData["fromX"] ?? 0.0), (float) ($cachedData["fromY"] ?? 0.0), (float) ($cachedData["fromZ"] ?? 0.0));
		$to = new Vector3((float) ($cachedData["toX"] ?? 0.0), (float) ($cachedData["toY"] ?? 0.0), (float) ($cachedData["toZ"] ?? 0.0));
		$distance = round(BlockUtil::distance($from, $to), 5);
		$speed = round($distance / max($timeDiff, 0.00001), 5);

		$constants = is_array($cachedData["constants"] ?? null) ? $cachedData["constants"] : [];
		$speedLimit = (float) ($constants["walking-speed-limit"] ?? 0);
		$speedLimit += (bool) ($cachedData["sprinting"] ?? false) ? (float) ($constants["sprinting-speed-limit"] ?? 0) : 0;
		$speedLimit += (float) ($constants["jump-speed-limit"] ?? 0);
		$speedLimit += (bool) ($cachedData["onIce"] ?? false) ? (float) ($constants["ice-walking-speed-limit"] ?? 0) : 0;
		$speedLimit += (bool) ($cachedData["topBlock"] ?? false) ? (float) ($constants["top-block-limit"] ?? 0) : 0;
		$speedLimit += (bool) ($cachedData["onStairs"] ?? false) ? (float) ($constants["stairs-speed-limit"] ?? 0) : 0;
		$timeLimit = (float) ($constants["time-limit"] ?? 0);
		$distanceLimit = (float) ($constants["wakling-distance-limit"] ?? 0);
		$distanceLimit += (bool) ($cachedData["sprinting"] ?? false) ? (float) ($constants["sprinting-distance-limit"] ?? 0) : 0;
		$distanceLimit += (float) ($constants["jump-distance-limit"] ?? 0);
		$distanceLimit += (bool) ($cachedData["onIce"] ?? false) ? (float) ($constants["ice-walking-distance-limit"] ?? 0) : 0;
		$distanceLimit += (bool) ($cachedData["onStairs"] ?? false) ? (float) ($constants["stairs-walking-distance-limit"] ?? 0) : 0;
		$effectLevel = (int) ($cachedData["speedEffectLevel"] ?? 0);
		if ($effectLevel > 0) {
			$speedLimit += (float) ($constants["speed-effect-limit"] ?? 0) * $effectLevel;
			$timeLimit += (float) ($constants["time-effect-limit"] ?? 0) * $effectLevel;
			$distanceLimit += (float) ($constants["speed-effect-distance-limit"] ?? 0) * $effectLevel;
		}

		if ($tickDiff > (int) ($constants["max-lag-ticks"] ?? 8)) {
			return ["debug" => "tickDiff={$tickDiff}, lagSkipped=1"];
		}

		$debug = "tickDiff={$tickDiff}, timeDiff={$timeDiff}, speed={$speed}, distance={$distance}, speedLimit={$speedLimit}, distanceLimit={$distanceLimit}, timeLimit={$timeLimit}";
		if ($timeDiff <= $timeLimit && $speed > $speedLimit && $distance > $distanceLimit && (int) ($cachedData["ping"] ?? 0) < (int) ($constants["pingLagging"] ?? 0)) {
			return ["failed" => true, "debug" => $debug];
		}

		return ["debug" => $debug];
	}
}
