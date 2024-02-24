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

namespace ReinfyTeam\Zuri\checks\moving\speed;

use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\Event;
use pocketmine\event\player\PlayerMoveEvent;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\MathUtil;

class SpeedC extends Check {
	public function getName() : string {
		return "Speed";
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
		return 8;
	}

	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if ($event instanceof PlayerMoveEvent) {
			$player = $playerAPI->getPlayer();
			if (
				$playerAPI->getAttackTicks() < 40 ||
				$playerAPI->isInWeb() ||
				!$playerAPI->isOnGround() ||
				$player->getAllowFlight() ||
				$player->isFlying() ||
				!$player->isSurvival()
			) {
				return;
			}
			if (($d = MathUtil::XZDistanceSquared($event->getFrom(), $event->getTo())) > ($player->getEffects()->has(VanillaEffects::SPEED()) ? 0.9 * ($player->getEffects()->get(VanillaEffects::SPEED())->getAmplifier() + 1) : 0.9)) {
				$this->failed($playerAPI);
			}
			$this->debug($playerAPI, "distance=" . $d . ", limit=" . ($player->getEffects()->has(VanillaEffects::SPEED()) ? 0.9 * ($player->getEffects()->get(VanillaEffects::SPEED())->getAmplifier() + 1) : 0.9));
		}
	}
}