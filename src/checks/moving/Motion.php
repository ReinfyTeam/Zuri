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

use pocketmine\block\BlockTypeIds;
use pocketmine\event\Event;
use pocketmine\event\player\PlayerMoveEvent;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\BlockUtil;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function exp;
use function in_array;
use function microtime;
use function round;

class Motion extends Check {
	public function getName() : string {
		return "Motion";
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
				$player->isFlying() ||
				$player->getAllowFlight() ||
				$player->hasNoClientPredictions() ||
				!$playerAPI->isCurrentChunkIsLoaded() ||
				$playerAPI->isGliding()
			) {
				return;
			}


			$currentTick = round(microtime(true) * 20);
			$playerAPI->setExternalData("motionTick", $currentTick);
			$tickDiff = $currentTick - $playerAPI->getExternalData("motionTick", 0);

			if ($tickDiff == 0) {
				$tickDiff = 1;
			}

			$location = $player->getLocation();
			$newPos = $event->getTo();
			$diffX = $location->getX() - $newPos->getX();
			$diffY = $location->getY() - $newPos->getY();
			$diffZ = $location->getZ() - $newPos->getZ();
			$diff = ($diffX ** 2 + $diffY ** 2 + $diffZ ** 2) / ($tickDiff ** 2);

			if ($diff > 0.0625) { // Possible using Timer, so the motion is randomly speeding or slowing...
				$this->failed($playerAPI);
			}

			$speed = $newPos->subtractVector($location)->divide($tickDiff);
			if ($player->isAlive() and !$player->isSpectator()) {
				if (
					$player->getInAirTicks() > 10 &&
					!$player->isSleeping() &&
					!$player->hasNoClientPredictions() &&
					!$player->getAllowFlight()
				) {
					$blockUnder = BlockUtil::getUnderBlock($location);

					if (in_array($blockUnder->getTypeId(), [ //Fences are handling incorrectly by PMMP
						BlockTypeIds::ACACIA_FENCE,
						BlockTypeIds::ACACIA_FENCE_GATE,
						BlockTypeIds::BIRCH_FENCE,
						BlockTypeIds::BIRCH_FENCE_GATE,
						BlockTypeIds::DARK_OAK_FENCE,
						BlockTypeIds::DARK_OAK_FENCE_GATE,
						BlockTypeIds::JUNGLE_FENCE,
						BlockTypeIds::JUNGLE_FENCE_GATE,
						BlockTypeIds::NETHER_BRICK_FENCE,
						BlockTypeIds::OAK_FENCE,
						BlockTypeIds::OAK_FENCE_GATE,
						BlockTypeIds::SPRUCE_FENCE,
						BlockTypeIds::SPRUCE_FENCE_GATE,
						BlockTypeIds::MANGROVE_FENCE,
						BlockTypeIds::CRIMSON_FENCE,
						BlockTypeIds::WARPED_FENCE,
						BlockTypeIds::MANGROVE_FENCE_GATE,
						BlockTypeIds::CRIMSON_FENCE_GATE,
						BlockTypeIds::WARPED_FENCE_GATE,
						BlockTypeIds::CHERRY_FENCE,
						BlockTypeIds::CHERRY_FENCE_GATE,
						BlockTypeIds::PALE_OAK_FENCE,
						BlockTypeIds::PALE_OAK_FENCE_GATE
					], true)) {
						return;
					}

					$expectedVelocity = -0.08 / 0.02 - (-0.08 / 0.02) * exp(-0.02 * ($player->getInAirTicks() - $player->getStartAirTicks()));
					$jumpVelocity = (0.42 + ($player->getEffects()->get(VanillaEffects::JUMP_BOOST()) ? ($player->getEffects()->get(VanillaEffects::JUMP_BOOST())->getEffectLevel() / 10) : 0)) / 0.42;
					$diff = (($speed->y - $expectedVelocity) ** 2) / $jumpVelocity;

					if ($diff > 0.6 and $expectedVelocity < $speed->y) {
						if ($player->getInAirTicks() < 100) {
							$player->setMotion(new Vector3(0, $expectedVelocity, 0)); // Correct the motion
						} else {
							$this->failed($playerAPI);
						}
					}
				}
			}
		}
	}
}