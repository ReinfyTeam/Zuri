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

namespace ReinfyTeam\Zuri\checks\network\editionfaker;

use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\network\mcpe\protocol\types\DeviceOS;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\Utils;

class EditionFakerB extends Check {
	public function getName() : string {
		return "EditionFaker";
	}

	public function getSubType() : string {
		return "B";
	}

	public function maxViolations() : int {
		return 0; // Instant fail
	}

	// From Esoteric Code
	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		if ( $packet instanceof LoginPacket ) {
			$authData = Utils::fetchAuthData($packet->chainDataJwt);
			$titleId = $authData->titleId;
			$givenOS = $playerAPI->getDeviceOS();

			$expectedOS = match ($titleId) {
				$this->getConstant("windows-10") => DeviceOS::WINDOWS_10,
				$this->getConstant("nintendo") => DeviceOS::NINTENDO,
				$this->getConstant("android") => DeviceOS::ANDROID,
				$this->getConstant("playstation") => DeviceOS::PLAYSTATION,
				$this->getConstant("xbox") => DeviceOS::XBOX,
				$this->getConstant("apple") => DeviceOS::IOS,
				default => null
			};

			if ( $expectedOS !== null && $expectedOS !== $givenOS ) {
				$this->debug($playerAPI, "titleId=$titleId");
				$this->failed($playerAPI);
			}
		}
	}
}