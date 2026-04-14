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
use function is_numeric;
use function max;
use function microtime;

/**
 * Detects timer drift by comparing auth tick cadence to wall-clock time.
 */
class TimerD extends Check {
	/**
	 * Gets the check name.
	 */
	public function getName() : string {
		return "Timer";
	}

	/**
	 * Gets the check subtype identifier.
	 */
	public function getSubType() : string {
		return "D";
	}

	/**
	 * Processes auth input packets and dispatches async TimerD checks.
	 *
	 * @param DataPacket $packet Incoming network packet.
	 * @param PlayerAPI $playerAPI Player state wrapper.
	 *
	 * @throws DiscordWebhookException
	 */
	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		if (!$packet instanceof PlayerAuthInputPacket) {
			return;
		}

		$this->dispatchAsyncCheck($playerAPI->getPlayer()->getName(), [
			"checkName" => $this->getName(),
			"checkSubType" => $this->getSubType(),
			"authTick" => $packet->getTick(),
			"nowMs" => microtime(true) * 1000,
			"lastAuthTick" => $playerAPI->getExternalData(CacheData::TIMER_D_LAST_AUTH_TICK),
			"lastAtMs" => $playerAPI->getExternalData(CacheData::TIMER_D_LAST_AT_MS),
			"drift" => $playerAPI->getExternalData(CacheData::TIMER_D_DRIFT, 0.0),
			"samples" => $playerAPI->getExternalData(CacheData::TIMER_D_SAMPLES, 0),
			"buffer" => $playerAPI->getExternalData(CacheData::TIMER_D_BUFFER, 0),
			"ping" => (int) $playerAPI->getPing(),
			"maxPing" => is_numeric(self::getData(self::PING_LAGGING)) ? (int) self::getData(self::PING_LAGGING) : 0,
			"expectedMsPerTick" => is_numeric($this->getConstant(CheckConstants::TIMERD_DRIFT_EXPECTED_MS_PER_TICK)) ? (float) $this->getConstant(CheckConstants::TIMERD_DRIFT_EXPECTED_MS_PER_TICK) : 50.0,
			"maxTickJump" => is_numeric($this->getConstant(CheckConstants::TIMERD_DRIFT_MAX_TICK_JUMP)) ? (int) $this->getConstant(CheckConstants::TIMERD_DRIFT_MAX_TICK_JUMP) : 4,
			"maxNegativeDrift" => is_numeric($this->getConstant(CheckConstants::TIMERD_DRIFT_MAX_NEGATIVE)) ? (float) $this->getConstant(CheckConstants::TIMERD_DRIFT_MAX_NEGATIVE) : 28.0,
			"warmupSamples" => is_numeric($this->getConstant(CheckConstants::TIMERD_DRIFT_WARMUP_SAMPLES)) ? (int) $this->getConstant(CheckConstants::TIMERD_DRIFT_WARMUP_SAMPLES) : 6,
			"bufferLimit" => is_numeric($this->getConstant(CheckConstants::TIMERD_DRIFT_BUFFER_LIMIT)) ? (int) $this->getConstant(CheckConstants::TIMERD_DRIFT_BUFFER_LIMIT) : 3,
		]);
	}

	/**
	 * Evaluates async payload for TimerD violations.
	 *
	 * @param array<string,mixed> $payload Serialized check context.
	 *
	 * @return array<string,mixed>
	 */
	public static function evaluateAsync(array $payload) : array {
    // Thread-safe: execute in async worker thread only; use only $payload (no Player objects)
    if (\pocketmine\thread\Thread::getCurrentThreadId() === 0) {
        throw new \RuntimeException("evaluateAsync must not be called on the main thread");
    }
		if (($payload["checkName"] ?? null) !== "Timer" || ($payload["checkSubType"] ?? null) !== "D") {
			return [];
		}

		$authTickRaw = $payload["authTick"] ?? 0;
		$nowMsRaw = $payload["nowMs"] ?? 0.0;
		$authTick = is_numeric($authTickRaw) ? (int) $authTickRaw : 0;
		$nowMs = is_numeric($nowMsRaw) ? (float) $nowMsRaw : 0.0;
		$lastAuthTick = $payload["lastAuthTick"] ?? null;
		$lastAtMs = $payload["lastAtMs"] ?? null;

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
		$maxTickJumpRaw = $payload["maxTickJump"] ?? 4;
		$maxTickJump = is_numeric($maxTickJumpRaw) ? (int) $maxTickJumpRaw : 4;
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

		$expectedMsPerTickRaw = $payload["expectedMsPerTick"] ?? 50.0;
		$expectedMsPerTick = is_numeric($expectedMsPerTickRaw) ? (float) $expectedMsPerTickRaw : 50.0;
		$expectedDelta = $tickDelta * $expectedMsPerTick;
		$sampleDrift = $realDelta - $expectedDelta;
		$driftRaw = $payload["drift"] ?? 0.0;
		$samplesRaw = $payload["samples"] ?? 0;
		$bufferRaw = $payload["buffer"] ?? 0;
		$drift = ((is_numeric($driftRaw) ? (float) $driftRaw : 0.0) * 0.8) + ($sampleDrift * 0.2);
		$samples = (is_numeric($samplesRaw) ? (int) $samplesRaw : 0) + 1;
		$buffer = is_numeric($bufferRaw) ? (int) $bufferRaw : 0;

		$warmupSamplesRaw = $payload["warmupSamples"] ?? 6;
		$pingRaw = $payload["ping"] ?? 0;
		$maxPingRaw = $payload["maxPing"] ?? 200;
		$maxNegativeDriftRaw = $payload["maxNegativeDrift"] ?? 28.0;
		$warmupSamples = is_numeric($warmupSamplesRaw) ? (int) $warmupSamplesRaw : 6;
		$ping = is_numeric($pingRaw) ? (int) $pingRaw : 0;
		$maxPing = is_numeric($maxPingRaw) ? (int) $maxPingRaw : 200;
		$maxNegativeDrift = is_numeric($maxNegativeDriftRaw) ? (float) $maxNegativeDriftRaw : 28.0;
		if ($samples >= $warmupSamples && $ping < $maxPing) {
			if ($drift < -$maxNegativeDrift) {
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

		$bufferLimitRaw = $payload["bufferLimit"] ?? 3;
		$bufferLimit = is_numeric($bufferLimitRaw) ? (int) $bufferLimitRaw : 3;
		if ($buffer >= $bufferLimit) {
			$result["set"][CacheData::TIMER_D_BUFFER] = 0;
			$result["set"][CacheData::TIMER_D_DRIFT] = 0.0;
			$result["set"][CacheData::TIMER_D_SAMPLES] = 0;
			$result["failed"] = true;
		}

		return $result;
	}
}

