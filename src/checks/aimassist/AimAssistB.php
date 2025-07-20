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
use ReinfyTeam\Zuri\ZuriAC;
use function abs;
use function fmod;

class AimAssistB extends Check {
	public function getName() : string {
		return "AimAssist";
	}

	public function getSubType() : string {
		return "B";
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		if ($packet instanceof PlayerAuthInputPacket) {
			$nLocation = $playerAPI->getNLocation();
			ZuriAC::checkAsync(function () use ($playerAPI) {
				if (!empty($nLocation)) {
					$toYaw = $nLocation["to"]->getYaw();
					$fromYaw = $nLocation["from"]->getYaw();
					$abs = abs($toYaw - $fromYaw);
					if ($abs >= 1 && fmod($abs, 0.1) == 0) {
						if (fmod($abs, 1.0) == 0 || fmod($abs, 10.0) == 0 || fmod($abs, 30.0) == 0) {
							$this->failed($playerAPI);
							$this->debug($playerAPI, "toYaw=$toYaw, fromYaw=$fromYaw, abs=$abs");
						}
					}
					$toPitch = $nLocation["to"]->getPitch();
					$fromPitch = $nLocation["from"]->getPitch();
					$abs2 = abs($toPitch - $fromPitch);
					if ($abs2 >= 1 && fmod($abs2, 0.1) == 0) {
						if (fmod($abs2, 1.0) == 0 || fmod($abs2, 10.0) == 0 || fmod($abs2, 30.0) == 0) {
							$this->failed($playerAPI);
							$this->debug($playerAPI, "toYaw=$toYaw, fromYaw=$fromYaw, abs=$abs, toPitch=$toPitch, fromPitch=$fromPitch, abs2=$abs2");
						}
					}
				}
			});
		}
	}
}