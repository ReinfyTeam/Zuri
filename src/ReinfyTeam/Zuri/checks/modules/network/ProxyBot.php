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

namespace ReinfyTeam\Zuri\checks\modules\network;

use pocketmine\event\Event;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\utils\Internet;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\lang\Lang;
use ReinfyTeam\Zuri\lang\LangKeys;
use function is_array;
use function is_string;
use function json_decode;
use function strtolower;

/**
 * Detects proxy or VPN connections during player login.
 */
class ProxyBot extends Check {
	/**
	 * Gets the check name.
	 */
	public function getName() : string {
		return "ProxyBot";
	}

	/**
	 * Gets the check subtype identifier.
	 */
	public function getSubType() : string {
		return "A";
	}

	/**
	 * Handles pre-login events for proxy checks.
	 *
	 * @param Event $event Triggered event instance.
	 */
	public function checkJustEvent(Event $event) : void {
		if ($event instanceof PlayerPreLoginEvent) {
			$ip = $event->getIp();
			$err = null;
			$request = Internet::getUrl("https://proxycheck.io/v2/" . $ip, 5, ["Content-Type: application/json"], $err);

			if ($request !== null && $err === null) {
				$data = json_decode($request->getBody(), true, 16, JSON_PARTIAL_OUTPUT_ON_ERROR);
				if (!is_array($data)) {
					return;
				}

				$status = $data["status"] ?? null;
				$ipData = $data[$ip] ?? null;
				if ($status !== "error" && is_array($ipData)) {
					$proxyRaw = $ipData["proxy"] ?? "no";
					$proxy = is_string($proxyRaw) && strtolower($proxyRaw) === "yes";
					if ($proxy) {
						$this->warn($event->getPlayerInfo()->getUsername());
						$event->setKickFlag(0, Lang::get(LangKeys::ANTIBOT_MESSAGE));
					}
				}
			}
		}
	}

	/**
	 * Evaluates async payload for ProxyBot checks.
	 *
	 * @param array<string,mixed> $payload Serialized check context.
	 *
	 * @return array<string,mixed>
	 */
	// Thread-safe: Only use payload, never access main-thread state or Player objects
public static function evaluateAsync(array $payload) : array {
    // Thread-safe: execute in async worker thread only; use only $payload (no Player objects)
    if (\pocketmine\thread\Thread::getCurrentThreadId() === 0) {
        throw new \RuntimeException("evaluateAsync must not be called on the main thread");
    }
    \ReinfyTeam\Zuri\checks\snapshots\AsyncSnapshot::validatePayloadOrThrow($payload);

		return [];
	}
}

