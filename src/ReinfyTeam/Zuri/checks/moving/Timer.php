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

namespace ReinfyTeam\Zuri\checks\moving;

use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use function microtime;

class Timer extends Check {
	public function getName() : string {
		return "Timer";
	}

	public function getSubType() : string {
		return "A";
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
			if ($playerAPI->getOnlineTime() < 10 || $playerAPI->getDeathTicks() < 40) {
				return;
			}
			$point = $playerAPI->getExternalData("pointQ");
			$lastTime = $playerAPI->getExternalData("lastTimeQ");
			if ($lastTime === null && $point === null) {
				$playerAPI->setExternalData("lastTimeQ", microtime(true));
				$playerAPI->setExternalData("pointQ", 1);
				return;
			}
			$timeDiff = microtime(true) - $lastTime;
			if ($timeDiff > 0.5) { // ticks < 0.7 sec too slow
				if ($point < 6) {
					$this->failed($playerAPI);
					$this->debug($playerAPI, "timeDiff=$timeDiff, point=$point, lastTime=$lastTime");
				}
				$playerAPI->unsetExternalData("pointQ");
				$playerAPI->unsetExternalData("lastTimeQ");
			} elseif ($timeDiff <= 0.001) { // ticks > 1 too fast
				if ($point < 6) {
					$this->failed($playerAPI);
					$this->debug($playerAPI, "timeDiff=$timeDiff, point=$point, lastTime=$lastTime");
					$playerAPI->unsetExternalData("pointQ");
					$playerAPI->unsetExternalData("lastTimeQ");
				}
			} else {
				$playerAPI->setExternalData("pointQ", $point + 1);
			}
		}
	}
}