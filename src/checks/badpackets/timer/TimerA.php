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

namespace ReinfyTeam\Zuri\checks\badpackets\timer;

use ReinfyTeam\Zuri\config\CacheData;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\Server;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function abs;

class TimerA extends Check {
	public function getName() : string {
		return "Timer";
	}

	public function getSubType() : string {
		return "A";
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		if ($packet instanceof PlayerAuthInputPacket) {
			$this->dispatchAsyncCheck($playerAPI->getPlayer()->getName(), [
				"type" => "TimerA",
				"tps" => Server::getInstance()->getTicksPerSecond(),
				"ping" => $playerAPI->getPing(),
				"packetTick" => $packet->getTick(),
				"timerATick" => $playerAPI->getExternalData(CacheData::TIMER_A_TICK),
				"laggingPing" => self::getData(self::PING_LAGGING),
				"maxDiff" => (float) $this->getConstant("max-diff"),
			]);
		}
	}

	public static function evaluateAsync(array $payload) : array {
		$tps = (float) ($payload["tps"] ?? 20.0);
		$ping = (int) ($payload["ping"] ?? 0);
		$packetTick = (int) ($payload["packetTick"] ?? 0);
		$delayTicks = $payload["timerATick"] ?? null;
		$laggingPing = (int) ($payload["laggingPing"] ?? 0);
		$maxDiff = (float) ($payload["maxDiff"] ?? 0.0);

		$set = [];
		if ($tps < 19 && $ping < $laggingPing) {
			$set[CacheData::TIMER_A_TICK] = $tps - $packetTick;
		}

		if ($delayTicks !== null) {
			$tickDiff = $delayTicks - $packetTick;
			if ($tickDiff >= ($maxDiff + (abs(20 - $tps) * 2))) {
				return ["set" => $set, "failed" => true];
			}
		}

		return ["set" => $set];
	}
}