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

namespace ReinfyTeam\Zuri\checks\modules\network\editionfaker;

use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\network\mcpe\protocol\types\DeviceOS;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\config\CheckConstants;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use ReinfyTeam\Zuri\utils\Utils;
use function is_string;

/**
 * Validates title ID and reported device OS consistency.
 */
class EditionFakerB extends Check {
	/**
	 * Gets the check name.
	 */
	public function getName() : string {
		return "EditionFaker";
	}

	/**
	 * Gets the check subtype identifier.
	 */
	public function getSubType() : string {
		return "B";
	}

	/**
	 * Gets the maximum violations before action is taken.
	 */
	public function maxViolations() : int {
		return 0; // Instant fail
	}

	// From Esoteric Code

	/**
	 * Processes login packets for edition spoofing checks.
	 *
	 * @param DataPacket $packet Incoming network packet.
	 * @param PlayerAPI $playerAPI Player state wrapper.
	 *
	 * @throws DiscordWebhookException
	 */
	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		if ( $packet instanceof LoginPacket ) {
			$authData = Utils::fetchAuthData($packet->authInfoJson);
			$titleId = is_string($authData["titleId"] ?? null) ? $authData["titleId"] : "";
			$givenOS = $playerAPI->getDeviceOS();

			$expectedOS = match ($titleId) {
				$this->getConstant(CheckConstants::EDITIONFAKERB_WINDOWS_10) => DeviceOS::WINDOWS_10,
				$this->getConstant(CheckConstants::EDITIONFAKERB_NINTENDO) => DeviceOS::NINTENDO,
				$this->getConstant(CheckConstants::EDITIONFAKERB_ANDROID) => DeviceOS::ANDROID,
				$this->getConstant(CheckConstants::EDITIONFAKERB_PLAYSTATION) => DeviceOS::PLAYSTATION,
				$this->getConstant(CheckConstants::EDITIONFAKERB_XBOX) => DeviceOS::XBOX,
				$this->getConstant(CheckConstants::EDITIONFAKERB_APPLE) => DeviceOS::IOS,
				default => null
			};

			if ( $expectedOS !== null && $expectedOS !== $givenOS ) {
				$this->debug($playerAPI, "titleId=$titleId");
				$this->dispatchAsyncDecision($playerAPI, true);
			}
		}
	}

	/**
	 * Evaluates async payload for EditionFakerB checks.
	 *
	 * @param array<string,mixed> $payload Serialized check context.
	 *
	 * @return array<string,mixed>
	 */
	public static function evaluateAsync(array $payload) : array {
		return [];
	}
}
