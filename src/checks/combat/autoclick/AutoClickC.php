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

use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Event;
use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\network\mcpe\protocol\DataPacket;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use function microtime;

class AutoClickC extends Check {
	private bool $canDamagable = false;

	public function getName() : string {
		return "AutoClick";
	}

	public function getSubType() : string {
		return "C";
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

	public function checkJustEvent(Event $event) : void {
		if ($event instanceof EntityDamageEvent) {
			$this->canDamagable = $event->isCancelled();
		}
	}

	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		if ($playerAPI->getPlayer() === null) {
			return;
		}
		if (
			$playerAPI->isDigging() ||
			$playerAPI->getPlacingTicks() < 100 ||
			$playerAPI->getAttackTicks() < 40 ||
			!$playerAPI->getPlayer()->isSurvival() ||
			!$this->canDamagable
		) {
			return;
		}
		$ticks = $playerAPI->getExternalData("clicksTicks3");
		$lastClick = $playerAPI->getExternalData("lastClick3");
		if ($packet instanceof AnimatePacket) {
			if ($packet->action === AnimatePacket::ACTION_SWING_ARM) {
				if ($ticks !== null && $lastClick !== null) {
					$diff = microtime(true) - $lastClick;
					if ($diff > 2) {
						if ($ticks > 15) {
							$this->failed($playerAPI);
						}
						$playerAPI->unsetExternalData("clicksTicks3");
						$playerAPI->unsetExternalData("lastClick3");
					} else {
						$playerAPI->setExternalData("clicksTicks3", $ticks + 1);
					}
				} else {
					$playerAPI->setExternalData("clicksTicks3", 0);
					$playerAPI->setExternalData("lastClick3", microtime(true));
				}
			}
			$this->debug($playerAPI, "ticks=$ticks, lastClick=$lastClick");
		}
	}
}