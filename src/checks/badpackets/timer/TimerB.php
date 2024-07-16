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

namespace ReinfyTeam\Zuri\checks\badpackets\timer;

use pocketmine\network\mcpe\protocol\Packet;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use function microtime;

class TimerB extends Check {
	public function getName() : string {
		return "Timer";
	}

	public function getSubType() : string {
		return "B";
	}

	public function maxViolations() : int {
		return 3;
	}

	public function check(Packet $packet, PlayerAPI $playerAPI) : void {
		if ($packet instanceof PlayerAuthInputPacket) {
			$time = $playerAPI->getExternalData("lastTimeB");
			$ticks = $playerAPI->getExternalData("lastTicksB");
			$timer = $playerAPI->getExternalData("timerB");
			if (microtime(true) - $time > 1) {
				$this->debug($playerAPI, "time=" . $time . ", ticks=" . $ticks . ", timer=" . $timer);

				if ($ticks > 20) {
					$playerAPI->setExternalData("timerB", $timer + 1);
					if ($timer % $this->getConstant("time-percentage") === 0) {
						$this->failed($playerAPI);
					}
				} else {
					$playerAPI->setExternalData("timerB", 0);
				}
				$playerAPI->setExternalData("lastTimeB", microtime(true));
				$playerAPI->setExternalData("lastTicksB", $timer + 1);
			}
		}
	}
}