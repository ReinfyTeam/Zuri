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

namespace ReinfyTeam\Zuri\checks\modules\blockplace\scaffold;

use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\Event;
use pocketmine\math\Vector3;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\config\CacheData;
use ReinfyTeam\Zuri\config\CheckConstants;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use ReinfyTeam\Zuri\utils\MathUtil;
use function is_array;
use function is_numeric;
use function max;
use function microtime;

class ScaffoldE extends Check {
	private const string BUFFER_KEY = CacheData::SCAFFOLD_E_BUFFER;
	private const string LAST_BLOCK_KEY = CacheData::SCAFFOLD_E_LAST_BLOCK;
	private const string LAST_PLACE_AT_KEY = CacheData::SCAFFOLD_E_LAST_PLACE_AT;

	public function getName() : string {
		return "Scaffold";
	}

	public function getSubType() : string {
		return "E";
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if (!$event instanceof BlockPlaceEvent) {
			return;
		}

		$player = $event->getPlayer();
		$maxPingRaw = $this->getConstant(CheckConstants::SCAFFOLDE_EXPANSION_MAX_PING);
		$maxPing = is_numeric($maxPingRaw) ? (int) $maxPingRaw : 0;
		if (
			!$player->isSurvival() ||
			$player->getAllowFlight() ||
			$player->isFlying() ||
			$player->hasNoClientPredictions() ||
			$playerAPI->isOnAdhesion() ||
			$playerAPI->isInWeb() ||
			$playerAPI->isInLiquid() ||
			$playerAPI->isRecentlyCancelledEvent() ||
			(int) $playerAPI->getPing() > $maxPing
		) {
			$this->setBuffer($playerAPI, 0);
			return;
		}

		$blockPos = $event->getBlockAgainst()->getPosition();
		$now = microtime(true);
		$lastPlaceAtRaw = $playerAPI->getExternalData(self::LAST_PLACE_AT_KEY, 0.0);
		$lastPlaceAt = is_numeric($lastPlaceAtRaw) ? (float) $lastPlaceAtRaw : 0.0;
		$interval = $lastPlaceAt > 0 ? $now - $lastPlaceAt : 999.0;

		$horizontalDistanceSquared = MathUtil::XZDistanceSquared($player->getPosition(), $blockPos);
		$maxPlaceIntervalRaw = $this->getConstant(CheckConstants::SCAFFOLDE_MAX_PLACE_INTERVAL);
		$maxHorizontalDistanceSquaredRaw = $this->getConstant(CheckConstants::SCAFFOLDE_MAX_HORIZONTAL_DISTANCE_SQUARED);
		$maxPlaceInterval = is_numeric($maxPlaceIntervalRaw) ? (float) $maxPlaceIntervalRaw : 0.0;
		$maxHorizontalDistanceSquared = is_numeric($maxHorizontalDistanceSquaredRaw) ? (float) $maxHorizontalDistanceSquaredRaw : 0.0;
		$suspicious = $interval <= $maxPlaceInterval &&
			$horizontalDistanceSquared > $maxHorizontalDistanceSquared;

		$sequentialDistance = 0.0;
		$lastBlock = $playerAPI->getExternalData(self::LAST_BLOCK_KEY);
		if (is_array($lastBlock)) {
			$previousBlockXRaw = $lastBlock["x"] ?? 0.0;
			$previousBlockYRaw = $lastBlock["y"] ?? 0.0;
			$previousBlockZRaw = $lastBlock["z"] ?? 0.0;
			$previousBlock = new Vector3(
				is_numeric($previousBlockXRaw) ? (float) $previousBlockXRaw : 0.0,
				is_numeric($previousBlockYRaw) ? (float) $previousBlockYRaw : 0.0,
				is_numeric($previousBlockZRaw) ? (float) $previousBlockZRaw : 0.0
			);
			$sequentialDistance = MathUtil::distance($previousBlock, $blockPos->asVector3());
			$maxSequentialDistanceRaw = $this->getConstant(CheckConstants::SCAFFOLDE_MAX_SEQUENTIAL_DISTANCE);
			$maxSequentialDistance = is_numeric($maxSequentialDistanceRaw) ? (float) $maxSequentialDistanceRaw : 0.0;
			if ($interval <= $maxPlaceInterval && $sequentialDistance > $maxSequentialDistance) {
				$suspicious = true;
			}
		}

		$buffer = $this->getBuffer($playerAPI);
		if ($suspicious) {
			$buffer++;
		} else {
			$buffer = max(0, $buffer - 1);
		}

		$this->setBuffer($playerAPI, $buffer);
		$playerAPI->setExternalData(self::LAST_PLACE_AT_KEY, $now);
		$playerAPI->setExternalData(self::LAST_BLOCK_KEY, ["x" => $blockPos->getX(), "y" => $blockPos->getY(), "z" => $blockPos->getZ()]);
		$this->debug($playerAPI, "horizontalDistanceSquared={$horizontalDistanceSquared}, sequentialDistance={$sequentialDistance}, interval={$interval}, buffer={$buffer}");

		$bufferLimitRaw = $this->getConstant(CheckConstants::SCAFFOLDE_EXPANSION_BUFFER_LIMIT);
		$bufferLimit = is_numeric($bufferLimitRaw) ? (int) $bufferLimitRaw : 0;
		if ($buffer >= $bufferLimit) {
			$this->setBuffer($playerAPI, 0);
			$this->dispatchAsyncDecision($playerAPI, true);
		}
	}

	private function getBuffer(PlayerAPI $playerAPI) : int {
		$raw = $playerAPI->getExternalData(self::BUFFER_KEY, 0);
		return is_numeric($raw) ? (int) $raw : 0;
	}

	private function setBuffer(PlayerAPI $playerAPI, int $buffer) : void {
		$playerAPI->setExternalData(self::BUFFER_KEY, $buffer);
	}
}
