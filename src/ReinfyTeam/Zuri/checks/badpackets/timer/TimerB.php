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
 * @author ReinfyTeam
 * @link https://github.com/ReinfyTeam/
 *
 *
 */

declare(strict_types=1);

namespace ReinfyTeam\Zuri\checks\badpackets\timer;

use pocketmine\network\mcpe\protocol\DataPacket;
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

	public function ban() : bool {
		return false;
	}

	public function kick() : bool {
		return true;
	}

	public function flag() : bool {
		return false;
	}

	public function captcha() : bool {
		return false;
	}

	public function maxViolations() : int {
		return 3;
	}

	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		if ($packet instanceof PlayerAuthInputPacket) {
			$time = $playerAPI->getExternalData("lastTimeB");
			$ticks = $playerAPI->getExternalData("lastTicksB");
			$timer = $playerAPI->getExternalData("timerB");
			if (microtime(true) - $time > 1) {
				$this->debug($playerAPI, "time=" . $time . ", ticks=" . $ticks . ", timer=" . $timer);

				if ($ticks > 20) {
					$playerAPI->setExternalData("timerB", $timer + 1);
					if ($timer % 10 === 0) {
						$this->failed($playerAPI);
					}
				} else {
					$playerAPI->setExternalData("timerB", 0);
				}
				$playerAPI->setExternalData("lastTimeB", 0);
			}
		}
	}
}