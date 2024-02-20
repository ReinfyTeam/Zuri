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
		return 1;
	}

	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		if (
			$playerAPI->isDigging() ||
			$playerAPI->getPlacingTicks() < 100 ||
			$playerAPI->getAttackTicks() < 20 ||
			!$playerAPI->getPlayer()->isSurvival()
		) {
			return;
		}
		if ($packet instanceof AnimatePacket) {
			if (
				$packet->action !== AnimatePacket::ACTION_SWING_ARM &&
				$playerAPI->getAttackTicks() > 40
			) {
				$this->failed($playerAPI);
			}
			$this->debug($playerAPI, "isDigging=" . $playerAPI->isDigging() . ", placingTicks=" . $playerAPI->getPlacingTicks() . ", attackTicks=" . $playerAPI->getAttackTicks() . ", isSurvival=" . $playerAPI->getPlayer()->isSurvival());
		}
	}
}