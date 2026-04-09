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
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\item\ConsumableItem;
use pocketmine\item\Food;
use pocketmine\network\mcpe\protocol\ActorEventPacket;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\types\ActorEvent;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\config\CacheData;
use ReinfyTeam\Zuri\config\CheckConstants;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function is_numeric;
use function microtime;

class FastEat extends Check {
	public function getName() : string {
		return "FastEat";
	}

	public function getSubType() : string {
		return "A";
	}

	public function maxViolations() : int {
		return 5;
	}

	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		if ($packet instanceof ActorEventPacket) {
			if ($packet->eventId === ActorEvent::EATING_ITEM) {
				$lastTickRaw = $playerAPI->getExternalData(CacheData::FASTEAT_LAST_TICK);
				$lastTick = is_numeric($lastTickRaw) ? (float) $lastTickRaw : null;
				if ($lastTick === null) {
					$playerAPI->setExternalData(CacheData::FASTEAT_LAST_TICK, microtime(true));
				}
				$this->debug($playerAPI, "lastTick=$lastTick");
			}
		}
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if ($event instanceof PlayerItemConsumeEvent) {
			if ($event->getItem() instanceof ConsumableItem && $event->getItem() instanceof Food) {
				$lastTickRaw = $playerAPI->getExternalData(CacheData::FASTEAT_LAST_TICK);
				$lastTick = is_numeric($lastTickRaw) ? (float) $lastTickRaw : null;
				if ($lastTick !== null) {
					$diff = microtime(true) - $lastTick;
					$ping = $playerAPI->getPing();
					$timeDiffLimitRaw = $this->getConstant(CheckConstants::FASTEAT_TIMEDIFF_LIMIT);
					$timeDiffLimit = is_numeric($timeDiffLimitRaw) ? (float) $timeDiffLimitRaw : 0.0;
					if ($diff < $timeDiffLimit && $ping < self::getData(self::PING_LAGGING)) {
						$event->cancel();
						$this->dispatchAsyncDecision($playerAPI, true);
						$playerAPI->unsetExternalData(CacheData::FASTEAT_LAST_TICK);
					}
					$this->debug($playerAPI, "lastTick=$lastTick, diff=$diff");
				}
			}
		}
	}
}