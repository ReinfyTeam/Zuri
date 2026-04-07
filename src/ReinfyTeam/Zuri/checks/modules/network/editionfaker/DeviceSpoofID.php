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

use pocketmine\event\Event;
use pocketmine\event\player\PlayerPreLoginEvent;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\config\CheckConstants;
use function count_chars;
use function in_array;
use function preg_match;
use function str_replace;
use function strlen;
use function strtolower;
use function trim;

class DeviceSpoofID extends Check {
	private const array INVALID_DEVICE_IDS = [
		"",
		"0",
		"1",
		"unknown",
		"null",
		"none",
		"undefined",
		"ffffffffffffffff",
		"0000000000000000",
		"00000000-0000-0000-0000-000000000000",
		"ffffffff-ffff-ffff-ffff-ffffffffffff",
	];

	public function getName() : string {
		return "DeviceSpoofID";
	}

	public function getSubType() : string {
		return "A";
	}

	public function checkJustEvent(Event $event) : void {
		if (!$event instanceof PlayerPreLoginEvent) {
			return;
		}

		$extraData = $event->getPlayerInfo()->getExtraData();
		$deviceId = trim((string) ($extraData["DeviceId"] ?? ""));
		$normalized = strtolower(str_replace(["-", "_", ":", " "], "", $deviceId));
		$minLength = (int) $this->getConstant(CheckConstants::DEVICESPOOFID_MIN_LENGTH);
		$maxLength = (int) $this->getConstant(CheckConstants::DEVICESPOOFID_MAX_LENGTH);

		if ($deviceId === "" || strlen($deviceId) < $minLength || strlen($deviceId) > $maxLength) {
			$this->kick($event);
			return;
		}

		if (in_array(strtolower($deviceId), self::INVALID_DEVICE_IDS, true) || in_array($normalized, self::INVALID_DEVICE_IDS, true)) {
			$this->kick($event);
			return;
		}

		if ($normalized === "" || preg_match('/^(.)\1+$/', $normalized) === 1) {
			$this->kick($event);
			return;
		}

		if (strlen(count_chars($normalized, 3)) < (int) $this->getConstant(CheckConstants::DEVICESPOOFID_MIN_UNIQUE_CHARS)) {
			$this->kick($event);
			return;
		}

		if (preg_match('/^[0-9a-f]+$/', $normalized) !== 1 && preg_match('/^[a-z0-9]+$/', $normalized) !== 1) {
			$this->kick($event);
		}
	}

	private function kick(PlayerPreLoginEvent $event) : void {
		$this->warn($event->getPlayerInfo()->getUsername());
		$event->setKickFlag(0, self::getData(self::EDITIONFAKER_MESSAGE));
	}

	public static function evaluateAsync(array $payload) : array {
		return [];
	}
}
