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

namespace ReinfyTeam\Zuri\checks\combat\killaura;

use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\network\mcpe\protocol\DataPacket;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;

class KillAuraD extends Check {
	public function getName() : string {
		return "KillAura";
	}

	public function getSubType() : string {
		return "D";
	}

	public function maxViolations() : int {
		return 1;
	}

	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		if (($player = $playerAPI->getPlayer()) === null) {
			return;
		}
		if (
			$playerAPI->isDigging() ||
			$playerAPI->getPlacingTicks() < 100 ||
			$playerAPI->getAttackTicks() < 20 ||
			!$player->isSurvival()
		) {
			return;
		}
		if ($packet instanceof AnimatePacket) {
			$this->debug($playerAPI, "isDigging=" . $playerAPI->isDigging() . ", placingTicks=" . $playerAPI->getPlacingTicks() . ", attackTicks=" . $playerAPI->getAttackTicks() . ", isSurvival=" . $playerAPI->getPlayer()->isSurvival());
			if (
				$packet->action !== AnimatePacket::ACTION_SWING_ARM &&
				$playerAPI->getAttackTicks() > 40
			) {
				$this->failed($playerAPI);
			}
		}
	}
}