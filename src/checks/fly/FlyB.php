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

namespace ReinfyTeam\Zuri\checks\fly;

use pocketmine\network\mcpe\protocol\Packet;
use pocketmine\network\mcpe\protocol\UpdateAdventureSettingsPacket;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;

class FlyB extends Check {
	public function getName() : string {
		return "Fly";
	}

	public function getSubType() : string {
		return "B";
	}

	public function maxViolations() : int {
		return 1;
	}

	public function check(Packet $packet, PlayerAPI $playerAPI) : void {
		//TODO rewrite FlyB
		/*if ($packet instanceof UpdateAdventureSettingsPacket) {
			$player = $playerAPI->getPlayer();
			if (!$player->isCreative() && !$player->isSpectator() && !$player->getAllowFlight()) {
				switch ($packet->flags) {
					case 614:
					case 615:
					case 103:
					case 102:
					case 38:
					case 39:
						$this->failed($playerAPI);
						break;
				}
				if ((($packet->flags >> 9) & 0x01 === 1) || (($packet->flags >> 7) & 0x01 === 1) || (($packet->flags >> 6) & 0x01 === 1)) {
					$this->failed($playerAPI);
				}
				$this->debug($playerAPI, "packetFlags=" . $packet->flags);
			}
		}*/
	}
}
