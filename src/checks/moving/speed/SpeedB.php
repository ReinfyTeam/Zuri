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

namespace ReinfyTeam\Zuri\checks\moving\speed;

use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\Event;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\math\Vector3;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\BlockUtil;
use function abs;
use function microtime;
use function round;

class SpeedB extends Check {
	public function getName() : string {
		return "Speed";
	}

	public function getSubType() : string {
		return "B";
	}

	public function maxViolations() : int {
		return 4;
	}

	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		$player = $playerAPI->getPlayer();
		if ($event instanceof PlayerMoveEvent) {
			if (
				!$player->isSurvival() ||
				$playerAPI->getAttackTicks() < 40 ||
				$playerAPI->getProjectileAttackTicks() < 20 ||
				$playerAPI->getBowShotTicks() < 20 ||
				$playerAPI->getHurtTicks() < 10 ||
				$playerAPI->getSlimeBlockTicks() < 20 ||
				$playerAPI->getTeleportCommandTicks() < 40 ||
				$playerAPI->getOnlineTime() < 2 ||
				$playerAPI->isOnAdhesion() ||
				!$player->isOnGround() ||
				$player->isFlying() ||
				$player->getAllowFlight() ||
				$player->hasNoClientPredictions() ||
				!$playerAPI->isCurrentChunkIsLoaded() ||
				BlockUtil::isGroundSolid($player) ||
				$playerAPI->isGliding() ||
				$playerAPI->isRecentlyCancelledEvent()
			) {
				return;
			}

			$now = microtime(true);
			$time = $playerAPI->getExternalData("moveTimeA");
			$playerAPI->setExternalData("moveTimeA", $now);
			if ($time === null) {
				return;
			}

			$this->dispatchAsyncCheck($playerAPI->getPlayer()->getName(), [
				"type" => "SpeedB",
				"fromX" => $event->getFrom()->getX(),
				"fromY" => $event->getFrom()->getY(),
				"fromZ" => $event->getFrom()->getZ(),
				"toX" => $event->getTo()->getX(),
				"toY" => $event->getTo()->getY(),
				"toZ" => $event->getTo()->getZ(),
				"time" => $time,
				"now" => $now,
				"attackTicks" => $playerAPI->getAttackTicks(),
				"projectileAttackTicks" => $playerAPI->getProjectileAttackTicks(),
				"bowShotTicks" => $playerAPI->getBowShotTicks(),
				"hurtTicks" => $playerAPI->getHurtTicks(),
				"slimeTicks" => $playerAPI->getSlimeBlockTicks(),
				"teleportCommandTicks" => $playerAPI->getTeleportCommandTicks(),
				"onlineTime" => $playerAPI->getOnlineTime(),
				"onAdhesion" => $playerAPI->isOnAdhesion(),
				"onGround" => $player->isOnGround(),
				"flying" => $player->isFlying(),
				"allowFlight" => $player->getAllowFlight(),
				"noClientPredictions" => $player->hasNoClientPredictions(),
				"survival" => $player->isSurvival(),
				"chunkLoaded" => $playerAPI->isCurrentChunkIsLoaded(),
				"groundSolid" => BlockUtil::isGroundSolid($player),
				"gliding" => $playerAPI->isGliding(),
				"recentlyCancelled" => $playerAPI->isRecentlyCancelledEvent(),
				"sprinting" => $player->isSprinting(),
				"jumpTicks" => $playerAPI->getJumpTicks(),
				"onIce" => $playerAPI->isOnIce(),
				"topBlock" => $playerAPI->isTopBlock(),
				"onStairs" => $playerAPI->isOnStairs(),
				"speedEffectLevel" => ($effect = $player->getEffects()->get(VanillaEffects::SPEED())) !== null ? $effect->getEffectLevel() : 0,
				"ping" => $playerAPI->getPing(),
				"constants" => [
					"walking-speed-limit" => $this->getConstant("walking-speed-limit"),
					"sprinting-speed-limit" => $this->getConstant("sprinting-speed-limit"),
					"jump-speed-limit" => $this->getConstant("jump-speed-limit"),
					"ice-walking-speed-limit" => $this->getConstant("ice-walking-speed-limit"),
					"top-block-limit" => $this->getConstant("top-block-limit"),
					"stairs-speed-limit" => $this->getConstant("stairs-speed-limit"),
					"time-limit" => $this->getConstant("time-limit"),
					"wakling-distance-limit" => $this->getConstant("wakling-distance-limit"),
					"sprinting-distance-limit" => $this->getConstant("sprinting-distance-limit"),
					"jump-distance-limit" => $this->getConstant("jump-distance-limit"),
					"ice-walking-distance-limit" => $this->getConstant("ice-walking-distance-limit"),
					"stairs-walking-distance-limit" => $this->getConstant("stairs-walking-distance-limit"),
					"speed-effect-limit" => $this->getConstant("speed-effect-limit"),
					"time-effect-limit" => $this->getConstant("time-effect-limit"),
					"speed-effect-distance-limit" => $this->getConstant("speed-effect-distance-limit"),
					"pingLagging" => self::getData(self::PING_LAGGING),
				]
			]);
		}
	}

	public static function evaluateAsync(array $payload) : array {
		if (($payload["type"] ?? null) !== "SpeedB") {
			return [];
		}

		if (!(bool) ($payload["survival"] ?? false) || !(bool) ($payload["onGround"] ?? false) || !(bool) ($payload["chunkLoaded"] ?? false)) {
			return [];
		}

		if (
			(int) ($payload["attackTicks"] ?? 0) < 40 ||
			(int) ($payload["projectileAttackTicks"] ?? 0) < 20 ||
			(int) ($payload["bowShotTicks"] ?? 0) < 20 ||
			(int) ($payload["hurtTicks"] ?? 0) < 10 ||
			(int) ($payload["slimeTicks"] ?? 0) < 20 ||
			(int) ($payload["teleportCommandTicks"] ?? 0) < 40 ||
			(float) ($payload["onlineTime"] ?? 0) < 2 ||
			(bool) ($payload["onAdhesion"] ?? false) ||
			(bool) ($payload["flying"] ?? false) ||
			(bool) ($payload["allowFlight"] ?? false) ||
			(bool) ($payload["noClientPredictions"] ?? false) ||
			(bool) ($payload["groundSolid"] ?? false) ||
			(bool) ($payload["gliding"] ?? false) ||
			(bool) ($payload["recentlyCancelled"] ?? false)
		) {
			return [];
		}

		$time = (float) ($payload["time"] ?? 0.0);
		$now = (float) ($payload["now"] ?? microtime(true));
		$timeDiff = abs($time - $now);
		$from = new Vector3((float) ($payload["fromX"] ?? 0.0), (float) ($payload["fromY"] ?? 0.0), (float) ($payload["fromZ"] ?? 0.0));
		$to = new Vector3((float) ($payload["toX"] ?? 0.0), (float) ($payload["toY"] ?? 0.0), (float) ($payload["toZ"] ?? 0.0));
		$distance = round(BlockUtil::distance($from, $to), 5);
		$speed = round($distance / max($timeDiff, 0.00001), 5);

		$constants = $payload["constants"] ?? [];
		$speedLimit = (float) ($constants["walking-speed-limit"] ?? 0);
		$speedLimit += (bool) ($payload["sprinting"] ?? false) ? (float) ($constants["sprinting-speed-limit"] ?? 0) : 0;
		$speedLimit += (int) ($payload["jumpTicks"] ?? 0) < 40 ? (float) ($constants["jump-speed-limit"] ?? 0) : 0;
		$speedLimit += (bool) ($payload["onIce"] ?? false) ? (float) ($constants["ice-walking-speed-limit"] ?? 0) : 0;
		$speedLimit += (bool) ($payload["topBlock"] ?? false) ? (float) ($constants["top-block-limit"] ?? 0) : 0;
		$speedLimit += (bool) ($payload["onStairs"] ?? false) ? (float) ($constants["stairs-speed-limit"] ?? 0) : 0;
		$timeLimit = (float) ($constants["time-limit"] ?? 0);
		$distanceLimit = (float) ($constants["wakling-distance-limit"] ?? 0);
		$distanceLimit += (bool) ($payload["sprinting"] ?? false) ? (float) ($constants["sprinting-distance-limit"] ?? 0) : 0;
		$distanceLimit += (int) ($payload["jumpTicks"] ?? 0) < 40 ? (float) ($constants["jump-distance-limit"] ?? 0) : 0;
		$distanceLimit += (bool) ($payload["onIce"] ?? false) ? (float) ($constants["ice-walking-distance-limit"] ?? 0) : 0;
		$distanceLimit += (bool) ($payload["onStairs"] ?? false) ? (float) ($constants["stairs-walking-distance-limit"] ?? 0) : 0;
		$effectLevel = (int) ($payload["speedEffectLevel"] ?? 0);
		if ($effectLevel > 0) {
			$speedLimit += (float) ($constants["speed-effect-limit"] ?? 0) * $effectLevel;
			$timeLimit += (float) ($constants["time-effect-limit"] ?? 0) * $effectLevel;
			$distanceLimit += (float) ($constants["speed-effect-distance-limit"] ?? 0) * $effectLevel;
		}

		$debug = "timeDiff={$timeDiff}, speed={$speed}, distance={$distance}, speedLimit={$speedLimit}, distanceLimit={$distanceLimit}, timeLimit={$timeLimit}";
		if ($timeDiff > $timeLimit && $speed > $speedLimit && $distance > $distanceLimit && (int) ($payload["ping"] ?? 0) < (int) ($constants["pingLagging"] ?? 0)) {
			return ["failed" => true, "debug" => $debug];
		}

		return ["debug" => $debug];
	}
}