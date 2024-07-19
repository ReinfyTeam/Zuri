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

use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;

class TimerC extends Check {
	public function getName() : string {
		return "Timer";
	}

	public function getSubType() : string {
		return "C";
	}

	public function maxViolations() : int {
		return 5;
	}

	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		if ( $packet instanceof PlayerAuthInputPacket ) {
			// From Esoteric
			$delay = $playerAPI->getExternalData("DelayCounter");
			if ($delay === null) {
				$playerAPI->setExternalData("DelayCounter", 0);
				return;
			}
			$playerAPI->setExternalData("DelayCounter", $counter + 1);
		} elseif ( $packet instanceof MovePlayerPacket ) {
			$delay = $playerAPI->getExternalData("DelayCounter");
			if ( $delay < 2 && $playerAPI->getPlayer()->hasClientNoPredictions() && $playerAPI->getPlayer()->isAlive()) {
				$this->debug($playerAPI, "delay=$delay");
				$this->failed($playerAPI);
			}

			$playerAPI->setExternalData("DelayCounter", 0);
		}
	}
}