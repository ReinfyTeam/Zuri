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
use function microtime;

class AutoClickB extends Check {
	public function getName() : string {
		return "AutoClick";
	}

	public function getSubType() : string {
		return "B";
	}

	public function maxViolations() : int {
		return 1;
	}

	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		if ($playerAPI->getPlacingTicks() < 100) {
			return;
		}
		$ticks = $playerAPI->getExternalData("clicksTicks2");
		$lastClick = $playerAPI->getExternalData("lastClick");
		if ($packet instanceof LevelSoundEventPacket) {
			if ($packet->sound === LevelSoundEvent::ATTACK_NODAMAGE) {
				if ($ticks !== null && $lastClick !== null) {
					$diff = microtime(true) - $lastClick;
					if ($diff > $this->getConstant("diff-time")) {
						if ($ticks >= $this->getConstant("diff-ticks")) {
							$this->failed($playerAPI);
						}
						$this->debug($playerAPI, "diff=$diff, lastClick=$lastClick, ticks=$ticks");
						$playerAPI->unsetExternalData("clicksTicks2");
						$playerAPI->unsetExternalData("lastClick");
					} else {
						$playerAPI->setExternalData("clicksTicks2", $ticks + 1);
					}
					$this->debug($playerAPI, "lastClick=$lastClick, ticks=$ticks");
				} else {
					$playerAPI->setExternalData("clicksTicks2", 0);
					$playerAPI->setExternalData("lastClick", microtime(true));
				}
			}
		}
	}
}