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

use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\event\Event;
use pocketmine\Server;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;

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

	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if ($event instanceof EntityRegainHealthEvent) {
			if ($event->getRegainReason() != EntityDamageEvent::CAUSE_MAGIC && $event->getRegainReason() != EntityDamageEvent::CAUSE_CUSTOM) {
				$tick = (double) Server::getInstance()->getTick();
				$tps = (double) Server::getInstance()->getTicksPerSecond();
				$lastHealthTick = $playerAPI->getExternalData("lastHealthTickB") ?? 0;
				$healAmount = $event->getAmount();
				$this->debug($playerAPI, "tick=$tick, tps=$tps, lastHealthTick=$lastHealthTick, healAmount=$healAmount");
				if ($tps > 0.0 && $lastHealthTick != -1.0) {
					$diffTicks = (double) ($tick - $lastHealthTick); // server ticks since last health regain
					$delta = (double) $diffTicks / (double) $tps; // seconds since last health regain
					$healCount = $playerAPI->getExternalData("healCountB") ?? 0;
					$healTime = $playerAPI->getExternalData("healTimeB") ?? 0;
					$this->debug($playerAPI, "diffTicks=$diffTicks, delta=$delta, healCount=$healCount, healTime=$healTime");
					if ($delta < 10) {
						$playerAPI->setExternalData("healCountB", $healCount + $healAmount);
						$playerAPI->setExternalData("healTimeB", $healTime + $delta);
						$healCount = $playerAPI->getExternalData("healCountB");
						if ($healCount >= $this->getConstant("max-healcount")) {
							if ($healTime !== 0) {
								$healRate = (float) $healCount / (float) $healTime;

								$this->debug($playerAPI, "healRate=$healRate");

								if ($healRate > $this->getConstant("max-healrate")) {
									$this->failed($playerAPI);
								}
							}

							$playerAPI->setExternalData("healCountB", 0);
							$playerAPI->setExternalData("healTimeB", 0);
						}
					}
				}

				$playerAPI->setExternalData("lastHealthTickB", $tick);
			}
		}
	}
}