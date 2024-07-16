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

namespace ReinfyTeam\Zuri\checks\blockbreak;

use pocketmine\network\mcpe\protocol\Packet;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;

class WrongMining extends Check {
	public function getName() : string {
		return "WrongMining";
	}

	public function getSubType() : string {
		return "A";
	}

	public function maxViolations() : int {
		return 1;
	}

	public function check(Packet $packet, PlayerAPI $playerAPI) : void {
		if ($playerAPI->getPlayer() === null) {
			return;
		}
		$isCreative = $playerAPI->getPlayer()->isCreative() ? 10 : 0;
		if ($playerAPI->actionBreakingSpecial() && (($playerAPI->getNumberBlocksAllowBreak() + $isCreative) < $playerAPI->getBlocksBrokeASec())) {
			$this->failed($playerAPI);
			if (!$playerAPI->getPlayer()->spawned && !$playerAPI->getPlayer()->isConnected()) {
				return;
			}
			$playerAPI->setActionBreakingSpecial(false);
			$playerAPI->setBlocksBrokeASec(0);
			$playerAPI->setFlagged(true);
			$this->debug($playerAPI, "allowedBreak=" . $playerAPI->getNumberBlocksAllowBreak() . ", isCreative=$isCreative, blocksBrokeASec=" . $playerAPI->getBlocksBrokeASec());
		}
	}
}