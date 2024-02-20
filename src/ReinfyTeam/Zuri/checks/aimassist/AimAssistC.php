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

namespace ReinfyTeam\Zuri\checks\aimassist;

use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use function abs;

class AimAssistC extends Check {
	public function getName() : string {
		return "AimAssist";
	}

	public function getSubType() : string {
		return "C";
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
		return 10;
	}

	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		if ($packet instanceof PlayerAuthInputPacket) {
			$player = $playerAPI->getPlayer();
			if (
				!$player->isSurvival() ||
				$playerAPI->getAttackTicks() > 20 ||
				$playerAPI->getTeleportTicks() < 100 ||
				$player->isFlying() ||
				$player->getAllowFlight()
			) {
				return;
			}
			$nLocation = $playerAPI->getNLocation();
			if (!empty($nLocation)) {
				$abs = abs($nLocation["from"]->getYaw() - $nLocation["to"]->getYaw());
				$abs2 = abs($nLocation["from"]->getPitch() - $nLocation["to"]->getPitch());
				if ($abs > 0 && $abs < 0.01 && $abs2 > 0.2) {
					$this->failed($playerAPI);
				}
				$this->debug($playerAPI, "abs=$abs, abs2=$abs2");
			}
		}
	}
}