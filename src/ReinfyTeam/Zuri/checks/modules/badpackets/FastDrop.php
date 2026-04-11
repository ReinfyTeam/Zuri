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

namespace ReinfyTeam\Zuri\checks\modules\badpackets;

use pocketmine\event\Event;
use pocketmine\event\player\PlayerDropItemEvent;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\config\CacheData;
use ReinfyTeam\Zuri\config\CheckConstants;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function is_numeric;
use function microtime;

/**
 * Detects item drops occurring at impossible rates.
 */
class FastDrop extends Check {
	/**
	 * Gets the check name.
	 */
	public function getName() : string {
		return "FastDrop";
	}

	/**
	 * Gets the check subtype identifier.
	 */
	public function getSubType() : string {
		return "A";
	}

	/**
	 * Gets the maximum violations before action is taken.
	 */
	public function maxViolations() : int {
		return 5;
	}

	/**
	 * Handles item drop events for FastDrop detection.
	 *
	 * @param Event $event Triggered event instance.
	 * @param PlayerAPI $playerAPI Player state wrapper.
	 *
	 * @throws DiscordWebhookException
	 */
	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if ($event instanceof PlayerDropItemEvent) {
			$lastTickRaw = $playerAPI->getExternalData(CacheData::FASTDROP_LAST_TICK);
			$lastTick = is_numeric($lastTickRaw) ? (float) $lastTickRaw : null;
			$currentTick = microtime(true);
			if ($lastTick !== null) {
				$diff = $currentTick - $lastTick;
				$ping = $playerAPI->getPing();
				$timeLimitRaw = $this->getConstant(CheckConstants::FASTDROP_TIME_LIMIT);
				$timeLimit = is_numeric($timeLimitRaw) ? (float) $timeLimitRaw : 0.0;
				if ($diff < $timeLimit && $ping < self::getData(self::PING_LAGGING)) { // Wtf same as fastthrow?
					$event->cancel();
					$this->dispatchAsyncDecision($playerAPI, true);
				}
				$this->debug($playerAPI, "lastTick=$lastTick, diff=$diff");
			}
			$playerAPI->setExternalData(CacheData::FASTDROP_LAST_TICK, $currentTick);
		}
	}
}
