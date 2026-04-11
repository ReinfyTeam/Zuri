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

namespace ReinfyTeam\Zuri\checks\modules\badpackets\regen;

use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\event\Event;
use pocketmine\Server;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\config\CacheData;
use ReinfyTeam\Zuri\config\CheckConstants;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function in_array;
use function is_numeric;

/**
 * Detects excessive regeneration packet cadence.
 */
class RegenB extends Check {
	/**
	 * Gets the check name.
	 */
	public function getName() : string {
		return "Regen";
	}

	/**
	 * Gets the check subtype identifier.
	 */
	public function getSubType() : string {
		return "B";
	}

	/**
	 * Gets the maximum violations before action is taken.
	 */
	public function maxViolations() : int {
		return 3;
	}

	/**
	 * Handles regeneration-related events for RegenB detection.
	 *
	 * @param Event $event Triggered event instance.
	 * @param PlayerAPI $playerAPI Player state wrapper.
	 *
	 * @throws DiscordWebhookException
	 */
	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if ($event instanceof EntityRegainHealthEvent) {
			if (!in_array($event->getRegainReason(), [EntityDamageEvent::CAUSE_MAGIC, EntityDamageEvent::CAUSE_CUSTOM], true)) {
				$tick = Server::getInstance()->getTick();
				$tps = Server::getInstance()->getTicksPerSecond();
				$lastHealthTickRaw = $playerAPI->getExternalData(CacheData::REGEN_B_LAST_HEALTH_TICK) ?? 0;
				$lastHealthTick = is_numeric($lastHealthTickRaw) ? (int) $lastHealthTickRaw : 0;
				$healAmount = $event->getAmount();
				$this->debug($playerAPI, "tick=$tick, tps=$tps, lastHealthTick=$lastHealthTick, healAmount=$healAmount");
				if ($tps > 0.0 && $lastHealthTick !== -1) {
					$diffTicks = $tick - $lastHealthTick; // server ticks since last health regain
					$delta = $diffTicks / $tps; // seconds since last health regain
					$healCountRaw = $playerAPI->getExternalData(CacheData::REGEN_B_HEAL_COUNT) ?? 0;
					$healTimeRaw = $playerAPI->getExternalData(CacheData::REGEN_B_HEAL_TIME) ?? 0;
					$healCount = is_numeric($healCountRaw) ? (float) $healCountRaw : 0.0;
					$healTime = is_numeric($healTimeRaw) ? (float) $healTimeRaw : 0.0;
					$this->debug($playerAPI, "diffTicks=$diffTicks, delta=$delta, healCount=$healCount, healTime=$healTime");
					if ($delta < 10) {
						$playerAPI->setExternalData(CacheData::REGEN_B_HEAL_COUNT, $healCount + $healAmount);
						$playerAPI->setExternalData(CacheData::REGEN_B_HEAL_TIME, $healTime + $delta);
						$healCountRaw = $playerAPI->getExternalData(CacheData::REGEN_B_HEAL_COUNT);
						$healCount = is_numeric($healCountRaw) ? (float) $healCountRaw : 0.0;
						$maxHealCountRaw = $this->getConstant(CheckConstants::REGENB_MAX_HEALCOUNT);
						$maxHealCount = is_numeric($maxHealCountRaw) ? (float) $maxHealCountRaw : 0.0;
						if ($healCount >= $maxHealCount) {
							if ($healTime !== 0.0) {
								$healRate = $healCount / $healTime;

								$this->debug($playerAPI, "healRate=$healRate");

								$maxHealRateRaw = $this->getConstant(CheckConstants::REGENB_MAX_HEALRATE);
								$maxHealRate = is_numeric($maxHealRateRaw) ? (float) $maxHealRateRaw : 0.0;
								if ($healRate > $maxHealRate) {
									$this->dispatchAsyncDecision($playerAPI, true);
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
