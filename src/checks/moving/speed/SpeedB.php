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
				$playerAPI->isOnAdhesion() ||
				(!$player->isOnGround() && $player->getInAirTicks() > 5) ||
				$player->isFlying() ||
				$player->getAllowFlight() ||
				$player->hasNoClientPredictions() ||
				!$playerAPI->isCurrentChunkIsLoaded() ||
				BlockUtil::isGroundSolid($player) ||
				$playerAPI->isGliding()
			) {
				return;
			}

			$time = $playerAPI->getExternalData("moveTimeA");
			if ($time !== null) {
				$distance = round(BlockUtil::distance($event->getFrom(), $event->getTo()), 5); // Round precision of 5
				$timeDiff = abs($time - microtime(true));
				$speed = round($distance / $timeDiff, 5); // Round precision of 5; s = d/t

				// Calculate the possible speed limit
				$speedLimit = $this->getConstant("walking-speed-limit"); // Walking
				$speedLimit += $player->isSprinting() ? $this->getConstant("sprinting-speed-limit") : 0; // Sprinting
				$speedLimit += $playerAPI->getJumpTicks() < 40 ? $this->getConstant("jump-speed-limit") : 0; // Jumping
				$speedLimit += $playerAPI->isOnIce() ? $this->getConstant("ice-walking-speed-limit") : 0; // Ice walking limit
				$speedLimit += $playerAPI->isTopBlock() ? $this->getConstant("top-block-limit") : 0; // Ice walking limit

				$timeLimit = $this->getConstant("time-limit");

				// Calculate max distance must be the limit of blocks travelled.
				$distanceLimit = $this->getConstant("wakling-distance-limit"); // Walking
				$distanceLimit += $player->isSprinting() ? $this->getConstant("sprinting-distance-limit") : 0; // Sprinting
				$distanceLimit += $playerAPI->getJumpTicks() < 40 ? $this->getConstant("jump-distance-limit") : 0; // Jumping
				$distanceLimit += $playerAPI->isOnIce() ? $this->getConstant("ice-walking-distance-limit") : 0; // Ice walking limit

				// Calculate speed potion deviation..
				if (($effect = $player->getEffects()->get(VanillaEffects::SPEED())) !== null) {
					$speedLimit += $this->getConstant("speed-effect-limit") * $effect->getEffectLevel();
					$timeLimit += $this->getConstant("time-effect-limit") * $effect->getEffectLevel();
					$distanceLimit += $this->getConstant("speed-effect-distance-limit") * $effect->getEffectLevel();
				}

				$this->debug($playerAPI, "timeDiff=$timeDiff, speed=$speed, distance=$distance, speedLimit=$speedLimit, distanceLimit=$distanceLimit, timeLimit=$timeLimit");

				// If the time travelled is greater than the calculated time limit, fail immediately. Lag back? (is player is laggy?)
				// If speed is on limit and the distance travelled limit is high.
				if ($time > $timeLimit && $speed > $speedLimit && $distance > $distanceLimit && $playerAPI->getPing() < self::getData(self::PING_LAGGING)) {
					$this->failed($playerAPI);
				}
			}

			$playerAPI->setExternalData("moveTimeA", microtime(true));
		}
	}
}