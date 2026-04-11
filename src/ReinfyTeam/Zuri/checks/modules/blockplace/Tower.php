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

namespace ReinfyTeam\Zuri\checks\modules\blockplace;

use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\Event;
use pocketmine\math\Vector3;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\config\CheckConstants;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function abs;
use function cos;
use function deg2rad;
use function sin;

/**
 * Detects vertical tower-building movement anomalies.
 */
class Tower extends Check {
	/**
	 * Gets the check name.
	 */
	public function getName() : string {
		return "Tower";
	}

	/**
	 * Gets the check subtype identifier.
	 */
	public function getSubType() : string {
		return "A";
	}

	/**
	 * Gets the maximum violation threshold for this check.
	 */
	public function maxViolations() : int {
		return 5;
	}

	/**
	 * Handles block place and movement events for tower checks.
	 *
	 * @param Event $event Triggered event instance.
	 * @param PlayerAPI $playerAPI Player state wrapper.
	 *
	 * @throws DiscordWebhookException
	 */
	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if ($event instanceof BlockPlaceEvent) {
			foreach ($event->getTransaction()->getBlocks() as [$x, $y, $z, $block]) {
				$player = $event->getPlayer();
				$playerPos = $player->getPosition();
				$blockPos = $block->getPosition();

				// Calculate the direction vector from player to block
				$directionVector = $blockPos->subtract($playerPos->x, $playerPos->y, $playerPos->z)->normalize();

				// Get player's facing direction (yaw and pitch)
				$playerYaw = $player->getLocation()->getYaw();
				$playerPitch = $player->getLocation()->getPitch();

				// Convert yaw and pitch to a direction vector
				$yawRad = deg2rad($playerYaw);
				$pitchRad = deg2rad($playerPitch);
				$playerDirection = new Vector3(-sin($yawRad) * cos($pitchRad), -sin($pitchRad), cos($yawRad) * cos($pitchRad));

				// Calculate the dot product to determine if the block is placed in the player's facing direction
				$dotProduct = $playerDirection->dot($directionVector);
				$this->debug($playerAPI, "dotProduct=$dotProduct, playerPitch=$playerPitch");
				// Check if the player's direction is approximately towards the block being placed
				if ($playerPos->y > $blockPos->y) {
					if ($dotProduct < $this->getConstant(CheckConstants::TOWER_MARGIN_ERROR) && abs($playerPitch) < $this->getConstant(CheckConstants::TOWER_INVALID_PITCH)) {
						$event->cancel();
						$this->dispatchAsyncDecision($playerAPI, true);
					}
				}
			}
		}
	}
}
