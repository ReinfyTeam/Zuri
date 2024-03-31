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
use function round;

class TimerC extends Check {
	public function getName() : string {
		return "Timer";
	}

	public function getSubType() : string {
		return "C";
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
			$time = $playerAPI->getExternalData("timeC");
			$balance = $playerAPI->getExternalData("balanceC");
			$currentTime = microtime(true) * 1000;
			if ($time === null) {
				$playerAPI->setExternalData("timeC", $currentTime);
			}
			$timeDiff = round(($currentTime - $time) / 50, 2);
			$playerAPI->setExternalData("balanceC", $balance - 1);
			$playerAPI->setExternalData("balanceC", $balance - $timeDiff);
			if ($balance <= -5) {
				$this->debug($playerAPI, "balance=$balance, timeDiff=$timeDiff");
				$this->failed($playerAPI);
				$playerAPI->setExternalData("balanceC", 0);
			}
			$playerAPI->setExternalData("timeC", $currentTime);
		}
	}
}