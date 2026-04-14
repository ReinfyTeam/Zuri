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

namespace ReinfyTeam\Zuri\checks\modules\combat\rotation;

use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\config\CacheData;
use ReinfyTeam\Zuri\config\CheckConstants;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function abs;
use function fmod;
use function is_float;
use function is_int;
use function is_numeric;
use function max;

/**
 * Detects repetitive rotation deltas characteristic of aim assistance.
 */
class RotationA extends Check {
	private const TYPE = "RotationA";
	private const LAST_YAW = CacheData::ROTATION_A_LAST_YAW;
	private const LAST_PITCH = CacheData::ROTATION_A_LAST_PITCH;
	private const LAST_DELTA_YAW = CacheData::ROTATION_A_LAST_DELTA_YAW;
	private const LAST_DELTA_PITCH = CacheData::ROTATION_A_LAST_DELTA_PITCH;
	private const BUFFER = CacheData::ROTATION_A_BUFFER;

	/**
	 * Gets the check name.
	 */
	public function getName() : string {
		return "Rotation";
	}

	/**
	 * Gets the check subtype identifier.
	 */
	public function getSubType() : string {
		return "A";
	}

	/**
	 * Processes input packets for RotationA detection.
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

		$player = $playerAPI->getPlayer();
		$maxPingRaw = $this->getConstant(CheckConstants::ROTATIONA_MAX_PING);
		$maxPing = is_numeric($maxPingRaw) ? (int) $maxPingRaw : 0;
		if (!$player->isSurvival() || (int) $playerAPI->getPing() > $maxPing) {
			$this->setBuffer($playerAPI, 0);
			$this->storeAngles($playerAPI, $packet->getYaw(), $packet->getPitch(), 0.0, 0.0);
			return;
		}

		$lastYaw = $playerAPI->getExternalData(self::LAST_YAW);
		$lastPitch = $playerAPI->getExternalData(self::LAST_PITCH);
		if (!is_float($lastYaw) && !is_int($lastYaw) || !is_float($lastPitch) && !is_int($lastPitch)) {
			$this->storeAngles($playerAPI, $packet->getYaw(), $packet->getPitch(), 0.0, 0.0);
			return;
		}

		$yaw = $packet->getYaw();
		$pitch = $packet->getPitch();
		$deltaYaw = $this->angleDelta((float) $lastYaw, $yaw);
		$deltaPitch = abs($pitch - (float) $lastPitch);
		$lastDeltaYawRaw = $playerAPI->getExternalData(self::LAST_DELTA_YAW, $deltaYaw);
		$lastDeltaPitchRaw = $playerAPI->getExternalData(self::LAST_DELTA_PITCH, $deltaPitch);
		$combatWindowTicksRaw = $this->getConstant(CheckConstants::ROTATIONA_COMBAT_WINDOW_TICKS);
		$minDeltaYawRaw = $this->getConstant(CheckConstants::ROTATIONA_MIN_DELTA_YAW);
		$maxDeltaYawRaw = $this->getConstant(CheckConstants::ROTATIONA_MAX_DELTA_YAW);
		$minDeltaPitchRaw = $this->getConstant(CheckConstants::ROTATIONA_MIN_DELTA_PITCH);
		$maxDeltaPitchRaw = $this->getConstant(CheckConstants::ROTATIONA_MAX_DELTA_PITCH);
		$yawStepEpsilonRaw = $this->getConstant(CheckConstants::ROTATIONA_YAW_STEP_EPSILON);
		$pitchStepEpsilonRaw = $this->getConstant(CheckConstants::ROTATIONA_PITCH_STEP_EPSILON);
		$bufferLimitRaw = $this->getConstant(CheckConstants::ROTATIONA_BUFFER_LIMIT);

		$lastDeltaYaw = is_numeric($lastDeltaYawRaw) ? (float) $lastDeltaYawRaw : $deltaYaw;
		$lastDeltaPitch = is_numeric($lastDeltaPitchRaw) ? (float) $lastDeltaPitchRaw : $deltaPitch;
		$combatWindowTicks = is_numeric($combatWindowTicksRaw) ? (float) $combatWindowTicksRaw : 0.0;
		$minDeltaYaw = is_numeric($minDeltaYawRaw) ? (float) $minDeltaYawRaw : 0.0;
		$maxDeltaYaw = is_numeric($maxDeltaYawRaw) ? (float) $maxDeltaYawRaw : 0.0;
		$minDeltaPitch = is_numeric($minDeltaPitchRaw) ? (float) $minDeltaPitchRaw : 0.0;
		$maxDeltaPitch = is_numeric($maxDeltaPitchRaw) ? (float) $maxDeltaPitchRaw : 0.0;
		$yawStepEpsilon = is_numeric($yawStepEpsilonRaw) ? (float) $yawStepEpsilonRaw : 0.0;
		$pitchStepEpsilon = is_numeric($pitchStepEpsilonRaw) ? (float) $pitchStepEpsilonRaw : 0.0;
		$bufferLimit = is_numeric($bufferLimitRaw) ? (int) $bufferLimitRaw : 0;

		$inCombatWindow = $playerAPI->getAttackTicks() < $combatWindowTicks;
		$isPatternStable =
			$deltaYaw >= $minDeltaYaw &&
			$deltaYaw <= $maxDeltaYaw &&
			$deltaPitch >= $minDeltaPitch &&
			$deltaPitch <= $maxDeltaPitch &&
			abs($deltaYaw - $lastDeltaYaw) <= $yawStepEpsilon &&
			abs($deltaPitch - $lastDeltaPitch) <= $pitchStepEpsilon;

		$buffer = $this->getBuffer($playerAPI);
		$this->storeAngles($playerAPI, $yaw, $pitch, $deltaYaw, $deltaPitch);
		$this->dispatchAsyncCheck($playerAPI->getPlayer()->getName(), [
			"type" => self::TYPE,
			"buffer" => $buffer,
			"inCombatWindow" => $inCombatWindow,
			"isPatternStable" => $isPatternStable,
			"deltaYaw" => $deltaYaw,
			"deltaPitch" => $deltaPitch,
			"lastDeltaYaw" => $lastDeltaYaw,
			"lastDeltaPitch" => $lastDeltaPitch,
			"bufferLimit" => $bufferLimit,
		]);
	}

	/**
	 * Evaluates async payload for RotationA violations.
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
		if (($payload["type"] ?? null) !== self::TYPE) {
			return [];
		}

		$bufferRaw = $payload["buffer"] ?? 0;
		$buffer = is_numeric($bufferRaw) ? (int) $bufferRaw : 0;
		if ((bool) ($payload["inCombatWindow"] ?? false) && (bool) ($payload["isPatternStable"] ?? false)) {
			$buffer++;
		} else {
			$buffer = max(0, $buffer - 1);
		}

		$deltaYawRaw = $payload["deltaYaw"] ?? 0.0;
		$deltaPitchRaw = $payload["deltaPitch"] ?? 0.0;
		$lastDeltaYawRaw = $payload["lastDeltaYaw"] ?? 0.0;
		$lastDeltaPitchRaw = $payload["lastDeltaPitch"] ?? 0.0;
		$bufferLimitRaw = $payload["bufferLimit"] ?? 0;
		$deltaYaw = is_numeric($deltaYawRaw) ? (float) $deltaYawRaw : 0.0;
		$deltaPitch = is_numeric($deltaPitchRaw) ? (float) $deltaPitchRaw : 0.0;
		$lastDeltaYaw = is_numeric($lastDeltaYawRaw) ? (float) $lastDeltaYawRaw : 0.0;
		$lastDeltaPitch = is_numeric($lastDeltaPitchRaw) ? (float) $lastDeltaPitchRaw : 0.0;
		$bufferLimit = is_numeric($bufferLimitRaw) ? (int) $bufferLimitRaw : 0;

		$result = [
			"set" => [self::BUFFER => $buffer],
			"debug" => "deltaYaw={$deltaYaw}, deltaPitch={$deltaPitch}, lastDeltaYaw={$lastDeltaYaw}, lastDeltaPitch={$lastDeltaPitch}, buffer={$buffer}",
		];

		if ($buffer >= $bufferLimit) {
			$result["set"][self::BUFFER] = 0;
			$result["failed"] = true;
		}

		return $result;
	}

	/**
	 * Calculates normalized angular difference.
	 *
	 * @param float $from Source angle.
	 * @param float $to Target angle.
	 */
	private function angleDelta(float $from, float $to) : float {
		return abs(fmod(($to - $from + 540.0), 360.0) - 180.0);
	}

	/**
	 * Gets the current rotation buffer value.
	 *
	 * @param PlayerAPI $playerAPI Player state wrapper.
	 */
	private function getBuffer(PlayerAPI $playerAPI) : int {
		$bufferRaw = $playerAPI->getExternalData(self::BUFFER, 0);
		return is_numeric($bufferRaw) ? (int) $bufferRaw : 0;
	}

	/**
	 * Stores the rotation buffer value.
	 *
	 * @param PlayerAPI $playerAPI Player state wrapper.
	 * @param int $value Buffer value to persist.
	 */
	private function setBuffer(PlayerAPI $playerAPI, int $value) : void {
		$playerAPI->setExternalData(self::BUFFER, $value);
	}

	/**
	 * Persists previous angle values used by the check.
	 *
	 * @param PlayerAPI $playerAPI Player state wrapper.
	 * @param float $yaw Current yaw.
	 * @param float $pitch Current pitch.
	 * @param float $deltaYaw Delta yaw.
	 * @param float $deltaPitch Delta pitch.
	 */
	private function storeAngles(PlayerAPI $playerAPI, float $yaw, float $pitch, float $deltaYaw, float $deltaPitch) : void {
		$playerAPI->setExternalData(self::LAST_YAW, $yaw);
		$playerAPI->setExternalData(self::LAST_PITCH, $pitch);
		$playerAPI->setExternalData(self::LAST_DELTA_YAW, $deltaYaw);
		$playerAPI->setExternalData(self::LAST_DELTA_PITCH, $deltaPitch);
	}
}

