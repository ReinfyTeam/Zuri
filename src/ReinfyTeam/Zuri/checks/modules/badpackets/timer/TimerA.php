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

namespace ReinfyTeam\Zuri\checks\modules\badpackets\timer;

use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\Server;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\config\CacheData;
use ReinfyTeam\Zuri\config\CheckConstants;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function abs;
use function is_numeric;
use function max;
use function microtime;

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
			$maxDiffRaw = $this->getConstant(CheckConstants::TIMERA_MAX_DIFF);
			$this->dispatchAsyncCheck($playerAPI->getPlayer()->getName(), [
				"type" => "TimerA",
				"tps" => Server::getInstance()->getTicksPerSecond(),
				"ping" => $playerAPI->getPing(),
				"packetTick" => $packet->getTick(),
				"lastTick" => $playerAPI->getExternalData(CacheData::TIMER_A_TICK),
				"lastTime" => $playerAPI->getExternalData(CacheData::TIMER_A_LAST_TIME),
				"balance" => $playerAPI->getExternalData(CacheData::TIMER_A_BALANCE, 0.0),
				"laggingPing" => self::getData(self::PING_LAGGING),
				"maxDiff" => is_numeric($maxDiffRaw) ? (float) $maxDiffRaw : 0.0,
				"now" => microtime(true),
			]);
		}
	}

	public static function evaluateAsync(array $payload) : array {
		if (($payload["type"] ?? null) !== "TimerA") {
			return [];
		}

		$tpsRaw = $payload["tps"] ?? 20.0;
		$pingRaw = $payload["ping"] ?? 0;
		$packetTickRaw = $payload["packetTick"] ?? 0;
		$tps = is_numeric($tpsRaw) ? (float) $tpsRaw : 20.0;
		$ping = is_numeric($pingRaw) ? (int) $pingRaw : 0;
		$packetTick = is_numeric($packetTickRaw) ? (int) $packetTickRaw : 0;
		$lastTick = $payload["lastTick"] ?? null;
		$lastTime = $payload["lastTime"] ?? null;
		$balanceRaw = $payload["balance"] ?? 0.0;
		$laggingPingRaw = $payload["laggingPing"] ?? 0;
		$maxDiffRaw = $payload["maxDiff"] ?? 0.0;
		$nowRaw = $payload["now"] ?? microtime(true);
		$balance = is_numeric($balanceRaw) ? (float) $balanceRaw : 0.0;
		$laggingPing = is_numeric($laggingPingRaw) ? (int) $laggingPingRaw : 0;
		$maxDiff = is_numeric($maxDiffRaw) ? (float) $maxDiffRaw : 0.0;
		$now = is_numeric($nowRaw) ? (float) $nowRaw : microtime(true);

		if ($packetTick <= 0) {
			return [];
		}

		if ($ping >= $laggingPing || $tps < 18.0) {
			return [
				"set" => [
					CacheData::TIMER_A_TICK => $packetTick,
					CacheData::TIMER_A_LAST_TIME => $now,
					CacheData::TIMER_A_BALANCE => 0.0,
				],
			];
		}

		if ($lastTick === null || $lastTime === null) {
			return [
				"set" => [
					CacheData::TIMER_A_TICK => $packetTick,
					CacheData::TIMER_A_LAST_TIME => $now,
					CacheData::TIMER_A_BALANCE => 0.0,
				],
			];
		}

		$lastTickValue = is_numeric($lastTick) ? (int) $lastTick : 0;
		$tickJump = $packetTick - $lastTickValue;
		if ($tickJump <= 0 || $tickJump > 20) {
			return [
				"set" => [
					CacheData::TIMER_A_TICK => $packetTick,
					CacheData::TIMER_A_LAST_TIME => $now,
					CacheData::TIMER_A_BALANCE => 0.0,
				],
			];
		}

		$lastTimeValue = is_numeric($lastTime) ? (float) $lastTime : $now;
		$elapsedMs = max(1.0, ($now - $lastTimeValue) * 1000.0);
		$expectedMs = $tickJump * 50.0;
		$driftMs = $expectedMs - $elapsedMs;
		$newBalance = max(0.0, $balance + $driftMs);
		$tolerance = abs(20.0 - $tps) * 4.0;
		$result = [
			"set" => [
				CacheData::TIMER_A_TICK => $packetTick,
				CacheData::TIMER_A_LAST_TIME => $now,
				CacheData::TIMER_A_BALANCE => $newBalance,
			],
			"debug" => "tickJump={$tickJump}, elapsedMs={$elapsedMs}, expectedMs={$expectedMs}, driftMs={$driftMs}, balance={$newBalance}",
		];

		if ($newBalance > ($maxDiff + $tolerance)) {
			$result["failed"] = true;
			$result["set"][CacheData::TIMER_A_BALANCE] = $newBalance * 0.35;
		}

		return $result;
	}
}