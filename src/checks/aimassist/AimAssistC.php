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

namespace ReinfyTeam\Zuri\checks\aimassist;

use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function abs;

class AimAssistC extends Check {
	public function getName() : string {
		return "AimAssist";
	}

	public function getSubType() : string {
		return "C";
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		if ($packet instanceof PlayerAuthInputPacket) {
			$player = $playerAPI->getPlayer();
			if (
				!$player->isSurvival() ||
				$playerAPI->getAttackTicks() > 20 ||
				$playerAPI->getTeleportTicks() < 100 ||
				$player->isFlying() ||
				$player->getAllowFlight()
			) {
				return;
			}
			$nLocation = $playerAPI->getNLocation();
			if (!empty($nLocation)) {
				$abs = abs($nLocation["from"]->getYaw() - $nLocation["to"]->getYaw());
				$abs2 = abs($nLocation["from"]->getPitch() - $nLocation["to"]->getPitch());
				if ($abs > $this->getConstant("min-yaw") && $abs < $this->getConstant("min-pitch") && $abs2 > $this->getConstant("max-pitch")) {
					$this->failed($playerAPI);
				}
				$this->debug($playerAPI, "abs=$abs, abs2=$abs2");
			}
		}
	}
}