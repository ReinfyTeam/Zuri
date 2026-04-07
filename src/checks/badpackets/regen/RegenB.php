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

namespace ReinfyTeam\Zuri\checks\badpackets\regen;

use ReinfyTeam\Zuri\config\CheckConstants;
use ReinfyTeam\Zuri\config\CacheData;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\event\Event;
use pocketmine\Server;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function in_array;

class RegenB extends Check {
	public function getName() : string {
		return "Regen";
	}

	public function getSubType() : string {
		return "B";
	}

	public function maxViolations() : int {
		return 3;
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if ($event instanceof EntityRegainHealthEvent) {
			if (!in_array($event->getRegainReason(), [EntityDamageEvent::CAUSE_MAGIC, EntityDamageEvent::CAUSE_CUSTOM], true)) {
				$tick = (double) Server::getInstance()->getTick();
				$tps = Server::getInstance()->getTicksPerSecond();
				$lastHealthTick = $playerAPI->getExternalData(CacheData::REGEN_B_LAST_HEALTH_TICK) ?? 0;
				$healAmount = $event->getAmount();
				$this->debug($playerAPI, "tick=$tick, tps=$tps, lastHealthTick=$lastHealthTick, healAmount=$healAmount");
				if ($tps > 0.0 && $lastHealthTick != -1.0) {
					$diffTicks = $tick - $lastHealthTick; // server ticks since last health regain
					$delta = $diffTicks / $tps; // seconds since last health regain
					$healCount = $playerAPI->getExternalData(CacheData::REGEN_B_HEAL_COUNT) ?? 0;
					$healTime = $playerAPI->getExternalData(CacheData::REGEN_B_HEAL_TIME) ?? 0;
					$this->debug($playerAPI, "diffTicks=$diffTicks, delta=$delta, healCount=$healCount, healTime=$healTime");
					if ($delta < 10) {
						$playerAPI->setExternalData(CacheData::REGEN_B_HEAL_COUNT, $healCount + $healAmount);
						$playerAPI->setExternalData(CacheData::REGEN_B_HEAL_TIME, $healTime + $delta);
						$healCount = $playerAPI->getExternalData(CacheData::REGEN_B_HEAL_COUNT);
						if ($healCount >= $this->getConstant(CheckConstants::REGENB_MAX_HEALCOUNT)) {
							if ($healTime !== 0 && $healCount !== 0) {
								$healRate = (float) $healCount / (float) $healTime;

								$this->debug($playerAPI, "healRate=$healRate");

								if ($healRate > $this->getConstant(CheckConstants::REGENB_MAX_HEALRATE)) {
									$this->failed($playerAPI);
								}
							}

							$playerAPI->setExternalData(CacheData::REGEN_B_HEAL_COUNT, 0);
							$playerAPI->setExternalData(CacheData::REGEN_B_HEAL_TIME, 0);
						}
					}
				}

				$playerAPI->setExternalData(CacheData::REGEN_B_LAST_HEALTH_TICK, $tick);
			}
		}
	}
}