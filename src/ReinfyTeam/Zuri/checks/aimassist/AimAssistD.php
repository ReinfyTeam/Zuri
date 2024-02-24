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

class AimAssistD extends Check {
	public function getName() : string {
		return "AimAssist";
	}

	public function getSubType() : string {
		return "D";
	}

	public function ban() : bool {
		return false;
	}

	public function kick() : bool {
		return false;
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
			$player = $playerAPI->getPlayer();
			if (
				!$player->isSurvival() ||
				$playerAPI->getAttackTicks() > 100 ||
				$playerAPI->getTeleportTicks() < 100 ||
				$player->isFlying() ||
				$player->getAllowFlight()
			) {
				return;
			}
			$nLocation = $playerAPI->getNLocation();
			if (!empty($nLocation)) {
				$abs = abs($nLocation["to"]->getYaw() - $nLocation["from"]->getYaw());
				$abs2 = abs($nLocation["to"]->getPitch() - $nLocation["from"]->getPitch());
				if ($abs > 0.0 && $abs < 0.8 && $abs2 > 0.279 && $abs2 < 0.28090858) {
					$this->failed($playerAPI);
				}
				$this->debug($playerAPI, "abs=$abs, abs2=$abs2");
			}
		}
	}
}