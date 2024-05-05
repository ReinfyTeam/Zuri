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
 * @author ReinfyTeam
 * @link https://github.com/ReinfyTeam/
 *
 *
 */

declare(strict_types=1);

namespace ReinfyTeam\Zuri\checks\network;

use pocketmine\event\Event;
use pocketmine\event\player\PlayerPreLoginEvent;
use ReinfyTeam\Zuri\checks\Check;
use function curl_exec;
use function curl_getinfo;
use function curl_init;
use function curl_setopt_array;
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
			$api_url = "https://proxycheck.io/v2/" . $ip;
			$curl = curl_init($api_url);
			curl_setopt_array($curl, [
				CURLOPT_POST => true,
				CURLOPT_HEADER => false,
				CURLINFO_HEADER_OUT => true,
				CURLOPT_TIMEOUT => 120,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_SSL_VERIFYPEER => false
			]);
			$data = curl_exec($curl);
			$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
			if ($data === false) {
				return;
			} // if server is offline or server request problems...
			$result = json_decode($data, true);
			if ($status === 200 && $result["status"] !== "error" && isset($result[$ip])) {
				$proxy = $result[$ip]["proxy"] === "yes";
				if ($proxy) {
					$this->warn($event->getPlayerInfo()->getUsername());
					$event->setKickFlag(0, self::getData(self::ANTIBOT_MESSAGE));
				}
			}
		}
	}
}