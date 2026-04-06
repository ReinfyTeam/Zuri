<?php

declare(strict_types=1);

namespace ReinfyTeam\Zuri\checks\badpackets\timer;

use ReinfyTeam\Zuri\config\CacheData;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
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
			"expectedMsPerTick" => (float) $this->getConstant("drift-expected-ms-per-tick"),
			"maxTickJump" => (int) $this->getConstant("drift-max-tick-jump"),
			"maxNegativeDrift" => (float) $this->getConstant("drift-max-negative"),
			"warmupSamples" => (int) $this->getConstant("drift-warmup-samples"),
			"bufferLimit" => (int) $this->getConstant("drift-buffer-limit"),
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
