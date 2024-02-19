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

class InventoryCleaner extends Check {
	public function getName() : string {
		return "InventoryCleaner";
	}

	public function getSubType() : string {
		return "I";
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
		$ticks = $playerAPI->getExternalData("ticksTransaction");
		$transaction = $playerAPI->getExternalData("transaction");
		if ($packet instanceof InventoryTransactionPacket) {
			if ($packet->trData->getTypeId() === 0) {
				if ($ticks !== null && $transaction !== null) {
					$diff = microtime(true) - $ticks;
					if ($diff > 2) {
						if ($transaction > 20) {
							$this->failed($playerAPI);
						}
						$playerAPI->unsetExternalData("ticksTransaction");
						$playerAPI->unsetExternalData("transaction");
					} else {
						$playerAPI->setExternalData("transaction", $transaction + 1);
					}
				} else {
					$playerAPI->setExternalData("ticksTransaction", microtime(true));
					$playerAPI->setExternalData("transaction", 0);
				}
			}
		}
	}
}