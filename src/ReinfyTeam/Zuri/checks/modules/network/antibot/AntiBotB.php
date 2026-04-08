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

namespace ReinfyTeam\Zuri\checks\modules\network\antibot;

use pocketmine\event\Event;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\types\DeviceOS;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\lang\Lang;
use ReinfyTeam\Zuri\lang\LangKeys;
use ReinfyTeam\Zuri\player\PlayerAPI;
use function str_contains;
use function strtolower;

class AntiBotB extends Check {
	private const array SUSPICIOUS_CLIENT_SIGNATURES = [
		"toolbox",
		"horion",
		"zephyr",
		"fate",
		"cheat",
		"modmenu",
		"mod menu",
		"inject",
		"hacked",
	];

	public function getName() : string {
		return "AntiBot";
	}

	public function getSubType() : string {
		return "B";
	}

	public function checkJustEvent(Event $event) : void {
		if ($event instanceof PlayerPreLoginEvent) {
			$extraData = $event->getPlayerInfo()->getExtraData();
			if (($extraData["DeviceOS"] ?? null) === DeviceOS::ANDROID) {
				$model = strtolower((string) ($extraData["DeviceModel"] ?? ""));
				$thirdParty = strtolower((string) ($extraData["ThirdPartyName"] ?? ""));

				foreach (self::SUSPICIOUS_CLIENT_SIGNATURES as $signature) {
					if (str_contains($model, $signature) || str_contains($thirdParty, $signature)) {
						$this->warn($event->getPlayerInfo()->getUsername());
						$event->setKickFlag(0, Lang::get(LangKeys::ANTIBOT_MESSAGE));
						return;
					}
				}

				if (str_contains($thirdParty, "lunar") && ($extraData["DeviceOS"] ?? null) !== DeviceOS::WINDOWS_10 && ($extraData["DeviceOS"] ?? null) !== DeviceOS::WIN32) {
					$this->warn($event->getPlayerInfo()->getUsername());
					$event->setKickFlag(0, Lang::get(LangKeys::ANTIBOT_MESSAGE));
					return;
				}
			}
		}
	}

	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
	}

	public static function evaluateAsync(array $payload) : array {
		return [];
	}
}
