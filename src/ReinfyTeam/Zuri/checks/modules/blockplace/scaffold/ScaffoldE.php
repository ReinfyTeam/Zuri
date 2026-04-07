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
		if (
			!$player->isSurvival() ||
			$player->getAllowFlight() ||
			$player->isFlying() ||
			$player->hasNoClientPredictions() ||
			$playerAPI->isOnAdhesion() ||
			$playerAPI->isInWeb() ||
			$playerAPI->isInLiquid() ||
			$playerAPI->isRecentlyCancelledEvent() ||
			(int) $playerAPI->getPing() > (int) $this->getConstant(CheckConstants::SCAFFOLDE_EXPANSION_MAX_PING)
		) {
			$this->setBuffer($playerAPI, 0);
			return;
		}

		$blockPos = $event->getBlock()->getPosition();
		$now = microtime(true);
		$lastPlaceAt = (float) $playerAPI->getExternalData(self::LAST_PLACE_AT_KEY, 0.0);
		$interval = $lastPlaceAt > 0 ? $now - $lastPlaceAt : 999.0;

		$horizontalDistanceSquared = MathUtil::XZDistanceSquared($player->getPosition(), $blockPos);
		$suspicious = $interval <= (float) $this->getConstant(CheckConstants::SCAFFOLDE_MAX_PLACE_INTERVAL) &&
			$horizontalDistanceSquared > (float) $this->getConstant(CheckConstants::SCAFFOLDE_MAX_HORIZONTAL_DISTANCE_SQUARED);

		$sequentialDistance = 0.0;
		$lastBlock = $playerAPI->getExternalData(self::LAST_BLOCK_KEY);
		if (is_array($lastBlock)) {
			$previousBlock = new Vector3((float) ($lastBlock["x"] ?? 0.0), (float) ($lastBlock["y"] ?? 0.0), (float) ($lastBlock["z"] ?? 0.0));
			$sequentialDistance = MathUtil::distance($previousBlock, $blockPos->asVector3());
			if ($interval <= (float) $this->getConstant(CheckConstants::SCAFFOLDE_MAX_PLACE_INTERVAL) && $sequentialDistance > (float) $this->getConstant(CheckConstants::SCAFFOLDE_MAX_SEQUENTIAL_DISTANCE)) {
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

		if ($buffer >= (int) $this->getConstant(CheckConstants::SCAFFOLDE_EXPANSION_BUFFER_LIMIT)) {
			$this->setBuffer($playerAPI, 0);
			$this->dispatchAsyncDecision($playerAPI, true);
		}
	}

	private function getBuffer(PlayerAPI $playerAPI) : int {
		return (int) $playerAPI->getExternalData(self::BUFFER_KEY, 0);
	}

	private function setBuffer(PlayerAPI $playerAPI, int $buffer) : void {
		$playerAPI->setExternalData(self::BUFFER_KEY, $buffer);
	}
}
