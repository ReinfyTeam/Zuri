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

namespace ReinfyTeam\Zuri\checks\moving;

use pocketmine\network\mcpe\protocol\DataPacket;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use function spl_object_id;

class SpeedA extends Check {
	public function getName() : string {
		return "OmniSprint";
	}

	public function getSubType() : string {
		return "A";
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

	private array $check = [];

	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		$player = $playerAPI->getPlayer();
		if ($packet instanceof PlayerAuthInputPacket) {
			if ($packet->getInputMode() === InputMode::MOUSE_KEYBOARD) {
				if ($packet->getInputFlag() |= (1 << PlayerAuthInputFlags::LEFT) || $packet->getInputFlag() |= (1 << PlayerAuthInputFlags::RIGHT) || $packet->getInputFlag() |= (1 << PlayerAuthInputFlags::DOWN)) {
					if (!$player->isSprinting() && isset($this->check[spl_object_id($playerAPI)])) {
						$this->failed($playerAPI);
					}
				}
			}
			$this->debug($playerAPI, "inputFlag=" . $packet->getInputFlag() . ", inputMode=" . $packet->getInputMode());
		}
	}

	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if ($event instanceof PlayerMoveEvent) {
			if (MathUtil::XZDistanceSquared($event->getFrom(), $event->getTo()) > 0.8) {
				$this->check[spl_object_id($playerAPI)] = true; // moving too fast?
			} else {
				if (isset($this->check[spl_object_id($playerAPI)])) {
					unset($this->check[spl_object_id($playerAPI)]);
				}
			}
			$this->debug($playerAPI, "speed=" . MathUtil::XZDistanceSquared($event->getFrom(), $event->getTo()) . ", isSprinting=" . $player->isSprinting());
		}
	}
}