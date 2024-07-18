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
use pocketmine\utils\Internet;
use ReinfyTeam\Zuri\checks\Check;
use function json_decode;

class ProxyBot extends Check {
	public function getName() : string {
		return "ProxyBot";
	}

	public function getSubType() : string {
		return "A";
	}

	public function maxViolations() : int {
		return 0;
	}

	public function checkJustEvent(Event $event) : void {
		if ($event instanceof PlayerPreLoginEvent) {
			$ip = $event->getIp();
			$request = Internet::getUrl("https://proxycheck.io/v2/" . $ip, 10, ["Content-Type: application/json"], $err);

			if ( $err === null ) {
				$data = json_decode($request->getBody(), true, 16, JSON_PARTIAL_OUTPUT_ON_ERROR);

				if (($data["status"] ?? null) !== "error" && isset($data[$ip])) {
					$proxy = ($result[$ip]["proxy"] ?? null) === "yes";
					if ($proxy) {
						$this->warn($event->getPlayerInfo()->getUsername());
						$event->setKickFlag(0, self::getData(self::ANTIBOT_MESSAGE));
					}
				}
			}
		}
	}
}