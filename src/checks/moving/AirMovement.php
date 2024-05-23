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

namespace ReinfyTeam\Zuri\checks\moving;

use pocketmine\network\mcpe\protocol\DataPacket;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use function pow;

class AirMovement extends Check {
	public function getName() : string {
		return "AirMovement";
	}

	public function getSubType() : string {
		return "A";
	}

	public function maxViolations() : int {
		return 5;
	}

	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		$effects = [];
		$player = $playerAPI->getPlayer();
		if ($player === null) {
			return;
		}
		if (!$player->spawned && !$player->isConnected()) {
			return;
		} // Effect::$effectInstance bug fix
		foreach ($player->getEffects()->all() as $index => $effect) {
			$transtable = $effect->getType()->getName()->getText();
			$effects[$transtable] = $effect->getEffectLevel() + 1;
		}
		$nLocation = $playerAPI->getNLocation();
		if (!empty($nLocation)) {
			if (
				$playerAPI->getAttackTicks() > 100 &&
				$playerAPI->getTeleportTicks() > 100 &&
				$playerAPI->getSlimeBlockTicks() > 200 &&
				!$player->getAllowFlight() &&
				!$playerAPI->isInLiquid() &&
				!$playerAPI->isInWeb() &&
				!$playerAPI->isOnGround() &&
				!$playerAPI->isOnAdhesion() &&
				$player->isSurvival() &&
				$playerAPI->getLastGroundY() !== 0.0 &&
				$nLocation["to"]->getY() > $playerAPI->getLastGroundY() &&
				$nLocation["to"]->getY() > $nLocation["from"]->getY() &&
				$playerAPI->getOnlineTime() >= 30 &&
				$playerAPI->getPing() < self::getData(self::PING_LAGGING)
			) {
				$distance = $nLocation["to"]->getY() - $playerAPI->getLastGroundY();
				$limit = $this->getConstant("air-limit");
				$limit += isset($effects["potion.jump"]) ? (pow($effects["potion.jump"] + 1.4, 2) / 16) : 0;
				if ($distance > $limit) {
					$this->failed($playerAPI);
				}
				$this->debug($playerAPI, "distance=$distance, limit=$limit");
			}
		}
	}
}