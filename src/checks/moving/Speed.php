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

namespace ReinfyTeam\Zuri\checks\moving;

use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\types\PlayerAuthInputFlags;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\BlockUtil;
use ReinfyTeam\Zuri\utils\MathUtil;
use function abs;
use function max;
use function min;

class Speed extends Check {
	public function getName() : string {
		return "Speed";
	}

	public function getSubType() : string {
		return "A";
	}

	public function maxViolations() : int {
		return 4;
	}

	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		$player = $playerAPI->getPlayer();
		if ($packet instanceof PlayerAuthInputPacket) {
			if (
				$playerAPI->getAttackTicks() < 40 ||
				$playerAPI->getOnlineTime() <= 30 ||
				!$playerAPI->isOnGround() ||
				$playerAPI->isOnAdhesion() ||
				$player->getAllowFlight() ||
				$player->isFlying() ||
				$player->hasNoClientPredictions() ||
				!$player->isSurvival() ||
				!$playerAPI->isCurrentChunkIsLoaded()
			) {
				return;
			}

			$previous = new Vector3($player->getPosition()->getX(), 0, $player->getPosition()->getZ());
			$next = new Vector3($packet->getPosition()->getX(), 0, $packet->getPosition()->getZ());

			$frictionBlock = $player->getWorld()->getBlock($player->getPosition()->getSide(Facing::DOWN));
			$friction = $playerAPI->isOnGround() ? $frictionBlock->getFrictionFactor() : $this->getConstant("friction-factor");
			$lastDistance = $playerAPI->getExternalData("lastDistanceXZ", $this->getConstant("xz-distance"));
			$momentum = MathUtil::getMomentum($lastDistance, $friction);
			$movement = MathUtil::getMovement($player, new Vector3(max(-1, min(1, $packet->getMoveVecZ())), 0, max(-1, min(1, $packet->getMoveVecX()))));
			$effects = MathUtil::getEffectsMultiplier($player);
			$acceleration = MathUtil::getAcceleration($movement, $effects, $friction, $player->isOnGround());

			$expected = $momentum + $acceleration;

			if (abs($playerAPI->getMotion()->getX()) > 0 || abs($playerAPI->getMotion()->getZ()) > 0) {
				$motionX = abs($playerAPI->getMotion()->getX());
				$motionZ = abs($playerAPI->getMotion()->getZ());
				$knockback = $motionX * $motionX + $motionZ * $motionZ;

				$knockback *= $this->getConstant("knockback-factor");
				$expected += $knockback;

				$playerAPI->getMotion()->x = 0;
				$playerAPI->getMotion()->z = 0;
			}

			$expected += ($playerAPI->getJumpTicks() < 5 && BlockUtil::getBlockAbove($player)->isSolid()) ? $this->getConstant("jump-factor") : 0;
			$expected += $playerAPI->getLastMoveTick() < 5 ? $this->getConstant("lastmove-factor") : 0;

			$playerAPI->setExternalData("lastDistanceXZ", $expected);

			$expected += ($player->isOnGround()) ? $this->getConstant("ground-factor") : 0;
			$expected += ($packet->hasFlag(PlayerAuthInputFlags::START_JUMPING) && $playerAPI->getLastMoveTick() > 5) ? $this->getConstant("lastjump-factor") : 0;
			$expected += ($playerAPI->getJumpTicks() <= 20 && $playerAPI->isOnIce()) ? $this->getConstant("ice-factor") : 0;

			$dist = $previous->distance($next);
			$distDiff = abs($dist - $expected);

			$this->debug($playerAPI, "expected=$expected, distance=$distDiff");

			if ($dist > $expected && $distDiff > $this->getConstant("threshold")) {
				$this->failed($playerAPI);
			}
		}
	}
}
