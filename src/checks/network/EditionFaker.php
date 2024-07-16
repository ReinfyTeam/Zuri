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

namespace ReinfyTeam\Zuri\checks\network;

use pocketmine\event\Event;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\network\mcpe\protocol\types\DeviceOS;
use ReinfyTeam\Zuri\checks\Check;
use function in_array;
use function strtoupper;

class EditionFaker extends Check {
	public function getName() : string {
		return "EditionFaker";
	}

	public function getSubType() : string {
		return "A";
	}

	public function maxViolations() : int {
		return 0;
	}

	public const NULL_MODELS = [
		DeviceOS::ANDROID,
		DeviceOS::OSX,
		DeviceOS::WINDOWS_10,
		DeviceOS::WIN32,
		DeviceOS::DEDICATED,
	];

	public const DEVICE_OS_LIST = [
		DeviceOS::ANDROID,
		DeviceOS::IOS,
		DeviceOS::AMAZON,
		DeviceOS::WINDOWS_10,
		DeviceOS::WIN32,
		DeviceOS::PLAYSTATION,
		DeviceOS::NINTENDO,
		DeviceOS::XBOX
	];

	public function checkJustEvent(Event $event) : void {
		if ($event instanceof PlayerPreLoginEvent) {
			$playerInfo = $event->getPlayerInfo();
			$extraData = $playerInfo->getExtraData();
			$nickname = $playerInfo->getUsername();

			if (!(in_array($extraData["DeviceOS"], EditionFaker::DEVICE_OS_LIST, true))) {
				$this->warn($nickname);
				$event->setKickFlag(0, self::getData(self::EDITIONFAKER_MESSAGE));
				return;
			}

			if (!(in_array($extraData["DeviceOS"], EditionFaker::NULL_MODELS, true)) && $extraData["DeviceModel"] === "") {
				$this->warn($nickname);
				$event->setKickFlag(0, self::getData(self::EDITIONFAKER_MESSAGE));
				return;
			}

			if ($extraData["DeviceOS"] === DeviceOS::IOS) {
				if ($extraData["DeviceId"] !== strtoupper($extraData["DeviceId"])) {
					$this->warn($nickname);
					$event->setKickFlag(0, self::getData(self::EDITIONFAKER_MESSAGE));
				}
			}
		}
	}
}
