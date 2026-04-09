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

namespace ReinfyTeam\Zuri\checks\modules\moving\noslow;

use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\Event;
use pocketmine\event\player\PlayerMoveEvent;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\config\CacheData;
use ReinfyTeam\Zuri\config\CheckConstants;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use ReinfyTeam\Zuri\utils\MathUtil;
use function is_numeric;
use function max;

class NoSlowA extends Check {
	private const BUFFER_KEY = CacheData::NOSLOW_A_BUFFER;

	public function getName() : string {
		return "NoSlow";
	}

	public function getSubType() : string {
		return "A";
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if (!$event instanceof PlayerMoveEvent) {
			return;
		}

		$player = $playerAPI->getPlayer();
		if (
			!$player->isSurvival() ||
			!$playerAPI->isCurrentChunkIsLoaded() ||
			$player->getAllowFlight() ||
			$player->isFlying() ||
			$player->hasNoClientPredictions() ||
			$playerAPI->isInLiquid() ||
			$playerAPI->isOnIce() ||
			$playerAPI->isOnStairs() ||
			$playerAPI->isOnAdhesion() ||
			$playerAPI->isInWeb() ||
			$playerAPI->isRecentlyCancelledEvent()
		) {
			$this->setBuffer($playerAPI, 0);
			return;
		}

		if (!$player->isUsingItem() || $player->getEffects()->has(VanillaEffects::SPEED())) {
			$this->setBuffer($playerAPI, max(0, $this->getBuffer($playerAPI) - 1));
			return;
		}

		$maxPingRaw = $this->getConstant(CheckConstants::NOSLOWA_MAX_PING);
		$maxPing = is_numeric($maxPingRaw) ? (int) $maxPingRaw : 0;
		if ((int) $playerAPI->getPing() > $maxPing) {
			return;
		}

		$moveXZ = MathUtil::XZDistanceSquared($event->getFrom(), $event->getTo());
		$buffer = $this->getBuffer($playerAPI);
		$maxDistanceRaw = $this->getConstant(CheckConstants::NOSLOWA_MAX_XZ_DISTANCE_SQUARED);
		$maxDistance = is_numeric($maxDistanceRaw) ? (float) $maxDistanceRaw : 0.0;
		if ($moveXZ > $maxDistance) {
			$buffer++;
		} else {
			$buffer = max(0, $buffer - 1);
		}

		$this->setBuffer($playerAPI, $buffer);
		$this->debug($playerAPI, "moveXZ={$moveXZ}, buffer={$buffer}, ping=" . (int) $playerAPI->getPing());

		$bufferLimitRaw = $this->getConstant(CheckConstants::NOSLOWA_BUFFER_LIMIT);
		$bufferLimit = is_numeric($bufferLimitRaw) ? (int) $bufferLimitRaw : 0;
		if ($buffer >= $bufferLimit) {
			$this->setBuffer($playerAPI, 0);
			$this->dispatchAsyncDecision($playerAPI, true);
		}
	}

	private function getBuffer(PlayerAPI $playerAPI) : int {
		$bufferRaw = $playerAPI->getExternalData(self::BUFFER_KEY, 0);
		return is_numeric($bufferRaw) ? (int) $bufferRaw : 0;
	}

	private function setBuffer(PlayerAPI $playerAPI, int $buffer) : void {
		$playerAPI->setExternalData(self::BUFFER_KEY, $buffer);
	}
}
