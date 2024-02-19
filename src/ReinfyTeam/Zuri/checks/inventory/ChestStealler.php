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

namespace ReinfyTeam\Zuri\checks\inventory;

use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use function microtime;

class ChestStealler extends Check {
	public function getName() : string {
		return "ChestStealler";
	}

	public function getSubType() : string {
		return "N";
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
		$ticks = $playerAPI->getExternalData("ticksN");
		$lastTime = $playerAPI->getExternalData("lastTimeN");
		if ($packet instanceof InventoryTransactionPacket) {
			if ($packet->trData->getTypeId() === 0) {
				if ($ticks !== null && $lastTime !== null) {
					$diff = microtime(true) - $lastTime;
					if ($diff > 0.1) {
						if ($ticks > 1) {
							$this->failed($playerAPI);
						}
						$playerAPI->unsetExternalData("ticksN");
						$playerAPI->unsetExternalData("lastTimeN");
					} else {
						$playerAPI->setExternalData("ticksN", $ticks + 1);
					}
				} else {
					$playerAPI->setExternalData("ticksN", 0);
					$playerAPI->setExternalData("lastTimeN", microtime(true));
				}
			}
		}
	}
}