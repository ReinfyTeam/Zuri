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
use pocketmine\network\mcpe\protocol\types\DeviceOS;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\lang\Lang;
use ReinfyTeam\Zuri\lang\LangKeys;
use function is_string;
use function preg_match;
use function strlen;
use function trim;

class AntiBotA extends Check {
	public function getName() : string {
		return "AntiBot";
	}

	public function getSubType() : string {
		return "A";
	}

	public function checkJustEvent(Event $event) : void {
		if ($event instanceof PlayerPreLoginEvent) {
			$extraData = $event->getPlayerInfo()->getExtraData();
			if (($extraData["DeviceOS"] ?? null) === DeviceOS::ANDROID) {
				$modelRaw = $extraData["DeviceModel"] ?? "";
				$model = trim(is_string($modelRaw) ? $modelRaw : "");
				$deviceIdRaw = $extraData["DeviceId"] ?? "";
				$deviceId = is_string($deviceIdRaw) ? $deviceIdRaw : "";
				if ($model === "" || strlen($deviceId) < 8 || preg_match('/[^\x20-\x7E]/', $model) === 1) {
					$this->warn($event->getPlayerInfo()->getUsername());
					$event->setKickFlag(0, Lang::get(LangKeys::ANTIBOT_MESSAGE));
				}
			}
		}
	}

	/** @param array<string,mixed> $payload
	 *  @return array<string,mixed>
	 */
	public static function evaluateAsync(array $payload) : array {
		return [];
	}
}
