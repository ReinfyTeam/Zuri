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
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\Server;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use function abs;

class TimerA extends Check {
	public function getName() : string {
		return "Timer";
	}

	public function getSubType() : string {
		return "A";
	}

	public function maxViolations() : int {
		return 3;
	}

	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		if ($packet instanceof PlayerAuthInputPacket) {
			$tps = Server::getInstance()->getTicksPerSecond();
			$tick = Server::getInstance()->getTick();
			$tickDiff = $tps - $tick;

			if ($tps < 19 && $playerAPI->getPing() < self::getData(self::PING_LAGGING)) {
				$playerAPI->setExternalData("TimerATick", $tps - $packet->getTick());
			}

			$delayTicks = $playerAPI->getExternalData("TimerATick");

			if ($delayTicks !== null) {
				$tickDiff = $delayTicks - $packet->getTick();
				if ( $tickDiff >= ($this->getConstant("max-diff") + (abs(20 - $tps) * 2)) ) {
					$this->failed($playerAPI);
				}
			}
		}
	}
}