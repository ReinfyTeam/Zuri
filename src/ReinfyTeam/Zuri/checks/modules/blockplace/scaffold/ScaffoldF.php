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

class ScaffoldF extends Check {
	private const BUFFER_KEY = CacheData::SCAFFOLD_F_BUFFER;
	private const LAST_PLACE_AT_KEY = CacheData::SCAFFOLD_F_LAST_PLACE_AT;
	private const LAST_BLOCK_KEY = CacheData::SCAFFOLD_F_LAST_BLOCK;
	private const LAST_PLAYER_KEY = CacheData::SCAFFOLD_F_LAST_PLAYER;

	public function getName() : string {
		return "Scaffold";
	}

	public function getSubType() : string {
		return "F";
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if (!$event instanceof BlockPlaceEvent) {
			return;
		}

		$player = $event->getPlayer();
		$maxPingRaw = $this->getConstant(CheckConstants::SCAFFOLDF_GHOST_MAX_PING);
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
			$this->resetState($playerAPI);
			return;
		}

		$now = microtime(true);
		$blockPos = $event->getBlockAgainst()->getPosition();
		$playerPos = $player->getPosition();
		$lastPlaceAtRaw = $playerAPI->getExternalData(self::LAST_PLACE_AT_KEY, 0.0);
		$lastPlaceAt = is_numeric($lastPlaceAtRaw) ? (float) $lastPlaceAtRaw : 0.0;
		$interval = $lastPlaceAt > 0.0 ? $now - $lastPlaceAt : 999.0;
		$playerBlockDistanceSquared = MathUtil::XZDistanceSquared($playerPos, $blockPos);

		$suspicious = false;
		$blockStep = 0.0;
		$playerStepSquared = 0.0;
		$isBelow = $blockPos->getY() <= ($playerPos->getY() - 1.0);

		$lastBlock = $playerAPI->getExternalData(self::LAST_BLOCK_KEY);
		$lastPlayer = $playerAPI->getExternalData(self::LAST_PLAYER_KEY);
		if (is_array($lastBlock) && is_array($lastPlayer)) {
			$previousBlockXRaw = $lastBlock["x"] ?? 0.0;
			$previousBlockYRaw = $lastBlock["y"] ?? 0.0;
			$previousBlockZRaw = $lastBlock["z"] ?? 0.0;
			$previousPlayerXRaw = $lastPlayer["x"] ?? 0.0;
			$previousPlayerYRaw = $lastPlayer["y"] ?? 0.0;
			$previousPlayerZRaw = $lastPlayer["z"] ?? 0.0;
			$previousBlock = new Vector3(
				is_numeric($previousBlockXRaw) ? (float) $previousBlockXRaw : 0.0,
				is_numeric($previousBlockYRaw) ? (float) $previousBlockYRaw : 0.0,
				is_numeric($previousBlockZRaw) ? (float) $previousBlockZRaw : 0.0
			);
			$previousPlayer = new Vector3(
				is_numeric($previousPlayerXRaw) ? (float) $previousPlayerXRaw : 0.0,
				is_numeric($previousPlayerYRaw) ? (float) $previousPlayerYRaw : 0.0,
				is_numeric($previousPlayerZRaw) ? (float) $previousPlayerZRaw : 0.0
			);

			$maxPlaceIntervalRaw = $this->getConstant(CheckConstants::SCAFFOLDF_GHOST_MAX_PLACE_INTERVAL);
			$minBlockStepRaw = $this->getConstant(CheckConstants::SCAFFOLDF_GHOST_MIN_BLOCK_STEP);
			$maxPlayerStepSquaredRaw = $this->getConstant(CheckConstants::SCAFFOLDF_GHOST_MAX_PLAYER_STEP_SQUARED);
			$minPlayerBlockDistanceSquaredRaw = $this->getConstant(CheckConstants::SCAFFOLDF_GHOST_MIN_PLAYER_BLOCK_DISTANCE_SQUARED);
			$maxPlaceInterval = is_numeric($maxPlaceIntervalRaw) ? (float) $maxPlaceIntervalRaw : 0.0;
			$minBlockStep = is_numeric($minBlockStepRaw) ? (float) $minBlockStepRaw : 0.0;
			$maxPlayerStepSquared = is_numeric($maxPlayerStepSquaredRaw) ? (float) $maxPlayerStepSquaredRaw : 0.0;
			$minPlayerBlockDistanceSquared = is_numeric($minPlayerBlockDistanceSquaredRaw) ? (float) $minPlayerBlockDistanceSquaredRaw : 0.0;

			$blockStep = MathUtil::distance($previousBlock, $blockPos->asVector3());
			$playerStepSquared = MathUtil::XZDistanceSquared($previousPlayer, $playerPos);
			$suspicious =
				$interval <= $maxPlaceInterval &&
				$isBelow &&
				$blockStep >= $minBlockStep &&
				$playerStepSquared <= $maxPlayerStepSquared &&
				$playerBlockDistanceSquared >= $minPlayerBlockDistanceSquared;
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
		$playerAPI->setExternalData(self::LAST_PLAYER_KEY, ["x" => $playerPos->getX(), "y" => $playerPos->getY(), "z" => $playerPos->getZ()]);
		$this->debug($playerAPI, "interval={$interval}, blockStep={$blockStep}, playerStepSquared={$playerStepSquared}, playerBlockDistanceSquared={$playerBlockDistanceSquared}, below={$isBelow}, buffer={$buffer}");

		$bufferLimitRaw = $this->getConstant(CheckConstants::SCAFFOLDF_GHOST_BUFFER_LIMIT);
		$bufferLimit = is_numeric($bufferLimitRaw) ? (int) $bufferLimitRaw : 0;
		if ($buffer >= $bufferLimit) {
			$this->resetState($playerAPI);
			$this->dispatchAsyncDecision($playerAPI, true);
		}
	}

	private function resetState(PlayerAPI $playerAPI) : void {
		$playerAPI->setExternalData(self::BUFFER_KEY, 0);
		$playerAPI->unsetExternalData(self::LAST_PLACE_AT_KEY);
		$playerAPI->unsetExternalData(self::LAST_BLOCK_KEY);
		$playerAPI->unsetExternalData(self::LAST_PLAYER_KEY);
	}

	private function getBuffer(PlayerAPI $playerAPI) : int {
		$raw = $playerAPI->getExternalData(self::BUFFER_KEY, 0);
		return is_numeric($raw) ? (int) $raw : 0;
	}

	private function setBuffer(PlayerAPI $playerAPI, int $buffer) : void {
		$playerAPI->setExternalData(self::BUFFER_KEY, $buffer);
	}
}
