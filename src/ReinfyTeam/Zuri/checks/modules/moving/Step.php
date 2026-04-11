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
use ReinfyTeam\Zuri\config\CacheData;
use ReinfyTeam\Zuri\config\CheckConstants;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function is_numeric;

/**
 * Detects step-height increases that exceed legitimate movement.
 */
class Step extends Check {
	/**
	 * Returns the check name.
	 *
	 * @return string Check identifier.
	 */
	public function getName() : string {
		return "Step";
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
	 * Processes move events for step detection.
	 *
	 * @param Event $event Triggered event.
	 * @param PlayerAPI $playerAPI Player context.
	 * @throws DiscordWebhookException
	 */
	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if ($event instanceof PlayerMoveEvent) {
			$player = $event->getPlayer();
			$pingLaggingRaw = self::getData(self::PING_LAGGING);
			$pingLagging = is_numeric($pingLaggingRaw) ? (int) $pingLaggingRaw : 0;
			if (
				$playerAPI->getPing() > $pingLagging ||
				!$playerAPI->isOnGround() ||
				!$player->isSurvival() ||
				$player->getAllowFlight() ||
				$player->isFlying() ||
				$playerAPI->isInLiquid() ||
				$playerAPI->isOnAdhesion() ||
				$playerAPI->getTeleportTicks() < 40 ||
				$playerAPI->getAttackTicks() < 40 ||
				$playerAPI->getDeathTicks() < 40 ||
				$playerAPI->getPlacingTicks() < 40 ||
				$event->isCancelled()
			) {
				return;
			}
			$lastY = $playerAPI->getExternalData(CacheData::STEP_LAST_Y);
			$locationPlayer = $player->getLocation();
			$limitRaw = $this->getConstant(CheckConstants::STEP_Y_LIMIT);
			$limit = is_numeric($limitRaw) ? (float) $limitRaw : 0.0;
			if (is_numeric($lastY)) {
				$lastYFloat = (float) $lastY;
				$diff = $locationPlayer->getY() - $lastYFloat;
				$stairsLimitRaw = $this->getConstant(CheckConstants::STEP_STAIRS_LIMIT);
				$stairsLimit = is_numeric($stairsLimitRaw) ? (float) $stairsLimitRaw : 0.0;
				$jumpLimitRaw = $this->getConstant(CheckConstants::STEP_JUMP_LIMIT);
				$jumpLimit = is_numeric($jumpLimitRaw) ? (float) $jumpLimitRaw : 0.0;
				$limit += $playerAPI->isOnStairs() ? $stairsLimit : 0;
				$limit += $playerAPI->getJumpTicks() < 40 ? $jumpLimit : 0;
				if ($diff > $limit) {
					$this->dispatchAsyncDecision($playerAPI, true);
				}
				$playerAPI->unsetExternalData(CacheData::STEP_LAST_Y);
				$this->debug($playerAPI, "lastY={$lastYFloat}, limit={$limit}, diff={$diff}");
			} else {
				$playerAPI->setExternalData(CacheData::STEP_LAST_Y, $locationPlayer->getY());
			}
		}
	}
}
