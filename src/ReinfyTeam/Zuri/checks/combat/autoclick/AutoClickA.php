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

namespace ReinfyTeam\Zuri\checks\combat\autoclick;

use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use function abs;

class AutoClickA extends Check {
	public function getName() : string {
		return "AutoClick";
	}

	public function getSubType() : string {
		return "A";
	}

	public function enable() : bool {
		return true;
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
		return 25;
	}

	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		$ticks = $playerAPI->getExternalData("ticksClick");
		$avgSpeed = $playerAPI->getExternalData("avgSpeed");
		$avgDeviation = $playerAPI->getExternalData("avgDeviation");
		if ($packet instanceof LevelSoundEventPacket) {
			if ($packet->sound === LevelSoundEvent::ATTACK_NODAMAGE) {
				if ($ticks !== null && $avgSpeed !== null && $avgDeviation !== null) {
					$playerAPI->setExternalData("ticksClick", 0);
					if ($playerAPI->isDigging() || $ticks > 5) {
						$playerAPI->unsetExternalData("ticksClick");
						$playerAPI->unsetExternalData("avgSpeed");
						$playerAPI->unsetExternalData("avgDeviation");
						return;
					} else {
						$playerAPI->setExternalData("ticksClick", $ticks + 1);
					}
					$speed = $ticks * 50;
					$playerAPI->setExternalData("avgSpeed", (($avgSpeed * 14) + $speed) / 15);
					$deviation = abs($speed - $playerAPI->getExternalData("avgSpeed"));
					$playerAPI->setExternalData("avgDeviation", (($avgDeviation * 9) + $deviation) / 10);
					if ($playerAPI->getExternalData("avgDeviation") < 5) {
						$this->failed($playerAPI);
					}
					$this->debug($playerAPI, "avgDeviation=$avgDeviation, speed=$speed, deviation=$deviation, ticksClick=$ticks, avgSpeed=$avgSpeed");
				} else {
					$playerAPI->setExternalData("ticksClick", 0);
					$playerAPI->setExternalData("avgSpeed", 0);
					$playerAPI->setExternalData("avgDeviation", 0);
				}
			}
		}
	}
}