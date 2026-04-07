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
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\config\CacheData;
use ReinfyTeam\Zuri\config\CheckConstants;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function is_float;
use function is_int;
use function max;
use function microtime;

class TimerD extends Check {
	public function getName() : string {
		return "Timer";
	}

	public function getSubType() : string {
		return "D";
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		if (!$packet instanceof PlayerAuthInputPacket) {
			return;
		}

		$this->dispatchAsyncCheck($playerAPI->getPlayer()->getName(), [
			"type" => "TimerD",
			"authTick" => $packet->getTick(),
			"nowMs" => microtime(true) * 1000,
			"lastAuthTick" => $playerAPI->getExternalData(CacheData::TIMER_D_LAST_AUTH_TICK),
			"lastAtMs" => $playerAPI->getExternalData(CacheData::TIMER_D_LAST_AT_MS),
			"drift" => $playerAPI->getExternalData(CacheData::TIMER_D_DRIFT, 0.0),
			"samples" => $playerAPI->getExternalData(CacheData::TIMER_D_SAMPLES, 0),
			"buffer" => $playerAPI->getExternalData(CacheData::TIMER_D_BUFFER, 0),
			"ping" => (int) $playerAPI->getPing(),
			"maxPing" => (int) self::getData(self::PING_LAGGING),
			"expectedMsPerTick" => (float) $this->getConstant(CheckConstants::TIMERD_DRIFT_EXPECTED_MS_PER_TICK),
			"maxTickJump" => (int) $this->getConstant(CheckConstants::TIMERD_DRIFT_MAX_TICK_JUMP),
			"maxNegativeDrift" => (float) $this->getConstant(CheckConstants::TIMERD_DRIFT_MAX_NEGATIVE),
			"warmupSamples" => (int) $this->getConstant(CheckConstants::TIMERD_DRIFT_WARMUP_SAMPLES),
			"bufferLimit" => (int) $this->getConstant(CheckConstants::TIMERD_DRIFT_BUFFER_LIMIT),
		]);
	}

	public static function evaluateAsync(array $payload) : array {
		if (($payload["type"] ?? null) !== "TimerD") {
			return [];
		}

		$authTick = (int) ($payload["authTick"] ?? 0);
		$nowMs = (float) ($payload["nowMs"] ?? 0.0);
		$lastAuthTick = $payload["lastAuthTick"];
		$lastAtMs = $payload["lastAtMs"];

		if ((!is_int($lastAuthTick) && !is_float($lastAuthTick)) || (!is_int($lastAtMs) && !is_float($lastAtMs))) {
			return [
				"set" => [
					CacheData::TIMER_D_LAST_AUTH_TICK => $authTick,
					CacheData::TIMER_D_LAST_AT_MS => $nowMs,
					CacheData::TIMER_D_DRIFT => 0.0,
					CacheData::TIMER_D_SAMPLES => 0,
					CacheData::TIMER_D_BUFFER => 0,
				],
			];
		}

		$tickDelta = $authTick - (int) $lastAuthTick;
		$maxTickJump = (int) ($payload["maxTickJump"] ?? 4);
		if ($tickDelta <= 0 || $tickDelta > $maxTickJump) {
			return [
				"set" => [
					CacheData::TIMER_D_LAST_AUTH_TICK => $authTick,
					CacheData::TIMER_D_LAST_AT_MS => $nowMs,
					CacheData::TIMER_D_DRIFT => 0.0,
					CacheData::TIMER_D_SAMPLES => 0,
					CacheData::TIMER_D_BUFFER => 0,
				],
			];
		}

		$realDelta = $nowMs - (float) $lastAtMs;
		if ($realDelta <= 0) {
			return [
				"set" => [
					CacheData::TIMER_D_LAST_AUTH_TICK => $authTick,
					CacheData::TIMER_D_LAST_AT_MS => $nowMs,
				],
			];
		}

		$expectedDelta = $tickDelta * (float) ($payload["expectedMsPerTick"] ?? 50.0);
		$sampleDrift = $realDelta - $expectedDelta;
		$drift = ((float) ($payload["drift"] ?? 0.0) * 0.8) + ($sampleDrift * 0.2);
		$samples = (int) ($payload["samples"] ?? 0) + 1;
		$buffer = (int) ($payload["buffer"] ?? 0);

		$warmupSamples = (int) ($payload["warmupSamples"] ?? 6);
		if ($samples >= $warmupSamples && (int) ($payload["ping"] ?? 0) < (int) ($payload["maxPing"] ?? 200)) {
			if ($drift < -((float) ($payload["maxNegativeDrift"] ?? 28.0))) {
				$buffer++;
			} else {
				$buffer = max(0, $buffer - 1);
			}
		}

		$result = [
			"set" => [
				CacheData::TIMER_D_LAST_AUTH_TICK => $authTick,
				CacheData::TIMER_D_LAST_AT_MS => $nowMs,
				CacheData::TIMER_D_DRIFT => $drift,
				CacheData::TIMER_D_SAMPLES => $samples,
				CacheData::TIMER_D_BUFFER => $buffer,
			],
			"debug" => "tickDelta={$tickDelta}, realDelta={$realDelta}, expectedDelta={$expectedDelta}, drift={$drift}, buffer={$buffer}",
		];

		if ($buffer >= (int) ($payload["bufferLimit"] ?? 3)) {
			$result["set"][CacheData::TIMER_D_BUFFER] = 0;
			$result["set"][CacheData::TIMER_D_DRIFT] = 0.0;
			$result["set"][CacheData::TIMER_D_SAMPLES] = 0;
			$result["failed"] = true;
		}

		return $result;
	}
}
