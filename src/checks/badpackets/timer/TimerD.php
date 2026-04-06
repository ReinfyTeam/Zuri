<?php

declare(strict_types=1);

namespace ReinfyTeam\Zuri\checks\badpackets\timer;

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
			"lastAuthTick" => $playerAPI->getExternalData("TimerD.lastAuthTick"),
			"lastAtMs" => $playerAPI->getExternalData("TimerD.lastAtMs"),
			"drift" => $playerAPI->getExternalData("TimerD.drift", 0.0),
			"samples" => $playerAPI->getExternalData("TimerD.samples", 0),
			"buffer" => $playerAPI->getExternalData("TimerD.buffer", 0),
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
					"TimerD.lastAuthTick" => $authTick,
					"TimerD.lastAtMs" => $nowMs,
					"TimerD.drift" => 0.0,
					"TimerD.samples" => 0,
					"TimerD.buffer" => 0,
				],
			];
		}

		$tickDelta = $authTick - (int) $lastAuthTick;
		$maxTickJump = (int) ($payload["maxTickJump"] ?? 4);
		if ($tickDelta <= 0 || $tickDelta > $maxTickJump) {
			return [
				"set" => [
					"TimerD.lastAuthTick" => $authTick,
					"TimerD.lastAtMs" => $nowMs,
					"TimerD.drift" => 0.0,
					"TimerD.samples" => 0,
					"TimerD.buffer" => 0,
				],
			];
		}

		$realDelta = $nowMs - (float) $lastAtMs;
		if ($realDelta <= 0) {
			return [
				"set" => [
					"TimerD.lastAuthTick" => $authTick,
					"TimerD.lastAtMs" => $nowMs,
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
				"TimerD.lastAuthTick" => $authTick,
				"TimerD.lastAtMs" => $nowMs,
				"TimerD.drift" => $drift,
				"TimerD.samples" => $samples,
				"TimerD.buffer" => $buffer,
			],
			"debug" => "tickDelta={$tickDelta}, realDelta={$realDelta}, expectedDelta={$expectedDelta}, drift={$drift}, buffer={$buffer}",
		];

		if ($buffer >= (int) ($payload["bufferLimit"] ?? 3)) {
			$result["set"]["TimerD.buffer"] = 0;
			$result["set"]["TimerD.drift"] = 0.0;
			$result["set"]["TimerD.samples"] = 0;
			$result["failed"] = true;
		}

		return $result;
	}
}
