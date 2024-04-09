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

namespace ReinfyTeam\Zuri\checks\fly;

use pocketmine\network\mcpe\protocol\DataPacket;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use function microtime;

class FlyA extends Check {
	public function getName() : string {
		return "Fly";
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
		return 1;
	}

	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		$player = $playerAPI->getPlayer();
		if ($player === null) {
			return;
		}
		if (
			$playerAPI->getAttackTicks() < 40 ||
			$playerAPI->getOnlineTime() <= 30 ||
			$playerAPI->getJumpTicks() < 40 ||
			$playerAPI->isInWeb() ||
			$playerAPI->isOnGround() ||
			$playerAPI->isOnAdhesion() ||
			$player->getAllowFlight() ||
			$player->hasNoClientPredictions() ||
			!$player->isSurvival() ||
			!$playerAPI->isCurrentChunkIsLoaded()
		) {
			$playerAPI->unsetExternalData("lastYNoGroundF");
			$playerAPI->unsetExternalData("lastTimeF");
			return;
		}
		$lastYNoGround = $playerAPI->getExternalData("lastYNoGroundF");
		$lastTime = $playerAPI->getExternalData("lastTimeF");
		if ($lastYNoGround !== null && $lastTime !== null) {
			$diff = microtime(true) - $lastTime;
			if ($diff > 1) {
				if ((int) $player->getLocation()->getY() == $lastYNoGround) {
					$this->failed($playerAPI);
				}
				$playerAPI->unsetExternalData("lastYNoGroundF");
				$playerAPI->unsetExternalData("lastTimeF");
			}
			$this->debug($playerAPI, "diff=$diff, lastTime=$lastTime, lastYNoGround=$lastYNoGround");
		} else {
			$playerAPI->setExternalData("lastYNoGroundF", (int) $player->getLocation()->getY());
			$playerAPI->setExternalData("lastTimeF", microtime(true));
		}
	}
}