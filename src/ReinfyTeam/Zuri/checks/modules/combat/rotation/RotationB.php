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

class RotationB extends Check {
	private const TYPE = "RotationB";
	private const LAST_YAW = CacheData::ROTATION_B_LAST_YAW;
	private const LAST_PITCH = CacheData::ROTATION_B_LAST_PITCH;
	private const LAST_DELTA_YAW = CacheData::ROTATION_B_LAST_DELTA_YAW;
	private const BUFFER = CacheData::ROTATION_B_BUFFER;

	public function getName() : string {
		return "Rotation";
	}

	public function getSubType() : string {
		return "B";
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		if (!$packet instanceof PlayerAuthInputPacket) {
			return;
		}

		$player = $playerAPI->getPlayer();
		$maxPingRaw = $this->getConstant(CheckConstants::ROTATIONB_MAX_PING);
		$maxPing = is_numeric($maxPingRaw) ? (int) $maxPingRaw : 0;
		if (
			!$player->isSurvival() ||
			$playerAPI->isRecentlyCancelledEvent() ||
			(int) $playerAPI->getPing() > $maxPing
		) {
			$this->setBuffer($playerAPI, 0);
			$this->storeState($playerAPI, $packet->getYaw(), $packet->getPitch(), 0.0);
			return;
		}

		$lastYaw = $playerAPI->getExternalData(self::LAST_YAW);
		$lastPitch = $playerAPI->getExternalData(self::LAST_PITCH);
		if ((!is_float($lastYaw) && !is_int($lastYaw)) || (!is_float($lastPitch) && !is_int($lastPitch))) {
			$this->storeState($playerAPI, $packet->getYaw(), $packet->getPitch(), 0.0);
			return;
		}

		$yaw = $packet->getYaw();
		$pitch = $packet->getPitch();
		$deltaYaw = $this->angleDelta((float) $lastYaw, $yaw);
		$deltaPitch = abs($pitch - (float) $lastPitch);
		$lastDeltaYawRaw = $playerAPI->getExternalData(self::LAST_DELTA_YAW, $deltaYaw);
		$combatWindowTicksRaw = $this->getConstant(CheckConstants::ROTATIONB_COMBAT_WINDOW_TICKS);
		$snapMinDeltaYawRaw = $this->getConstant(CheckConstants::ROTATIONB_SNAP_MIN_DELTA_YAW);
		$snapRepeatEpsilonRaw = $this->getConstant(CheckConstants::ROTATIONB_SNAP_REPEAT_EPSILON);
		$snapMaxDeltaPitchRaw = $this->getConstant(CheckConstants::ROTATIONB_SNAP_MAX_DELTA_PITCH);
		$snapBufferLimitRaw = $this->getConstant(CheckConstants::ROTATIONB_SNAP_BUFFER_LIMIT);
		$lastDeltaYaw = is_numeric($lastDeltaYawRaw) ? (float) $lastDeltaYawRaw : $deltaYaw;
		$combatWindowTicks = is_numeric($combatWindowTicksRaw) ? (float) $combatWindowTicksRaw : 0.0;
		$snapMinDeltaYaw = is_numeric($snapMinDeltaYawRaw) ? (float) $snapMinDeltaYawRaw : 0.0;
		$snapRepeatEpsilon = is_numeric($snapRepeatEpsilonRaw) ? (float) $snapRepeatEpsilonRaw : 0.0;
		$snapMaxDeltaPitch = is_numeric($snapMaxDeltaPitchRaw) ? (float) $snapMaxDeltaPitchRaw : 0.0;
		$snapBufferLimit = is_numeric($snapBufferLimitRaw) ? (int) $snapBufferLimitRaw : 0;

		$inCombatWindow = $playerAPI->getAttackTicks() < $combatWindowTicks;
		$looksSnapped =
			$deltaYaw >= $snapMinDeltaYaw &&
			abs($deltaYaw - $lastDeltaYaw) <= $snapRepeatEpsilon &&
			$deltaPitch <= $snapMaxDeltaPitch;

		$buffer = $this->getBuffer($playerAPI);
		$this->storeState($playerAPI, $yaw, $pitch, $deltaYaw);
		$this->dispatchAsyncCheck($playerAPI->getPlayer()->getName(), [
			"type" => self::TYPE,
			"buffer" => $buffer,
			"inCombatWindow" => $inCombatWindow,
			"looksSnapped" => $looksSnapped,
			"deltaYaw" => $deltaYaw,
			"deltaPitch" => $deltaPitch,
			"lastDeltaYaw" => $lastDeltaYaw,
			"bufferLimit" => $snapBufferLimit,
		]);
	}

	public static function evaluateAsync(array $payload) : array {
		if (($payload["type"] ?? null) !== self::TYPE) {
			return [];
		}

		$bufferRaw = $payload["buffer"] ?? 0;
		$buffer = is_numeric($bufferRaw) ? (int) $bufferRaw : 0;
		if ((bool) ($payload["inCombatWindow"] ?? false) && (bool) ($payload["looksSnapped"] ?? false)) {
			$buffer++;
		} else {
			$buffer = max(0, $buffer - 1);
		}

		$deltaYawRaw = $payload["deltaYaw"] ?? 0.0;
		$deltaPitchRaw = $payload["deltaPitch"] ?? 0.0;
		$lastDeltaYawRaw = $payload["lastDeltaYaw"] ?? 0.0;
		$bufferLimitRaw = $payload["bufferLimit"] ?? 0;
		$deltaYaw = is_numeric($deltaYawRaw) ? (float) $deltaYawRaw : 0.0;
		$deltaPitch = is_numeric($deltaPitchRaw) ? (float) $deltaPitchRaw : 0.0;
		$lastDeltaYaw = is_numeric($lastDeltaYawRaw) ? (float) $lastDeltaYawRaw : 0.0;
		$bufferLimit = is_numeric($bufferLimitRaw) ? (int) $bufferLimitRaw : 0;

		$result = [
			"set" => [self::BUFFER => $buffer],
			"debug" => "deltaYaw={$deltaYaw}, deltaPitch={$deltaPitch}, lastDeltaYaw={$lastDeltaYaw}, buffer={$buffer}",
		];

		if ($buffer >= $bufferLimit) {
			$result["set"][self::BUFFER] = 0;
			$result["failed"] = true;
		}

		return $result;
	}

	private function angleDelta(float $from, float $to) : float {
		return abs(fmod(($to - $from + 540.0), 360.0) - 180.0);
	}

	private function getBuffer(PlayerAPI $playerAPI) : int {
		$bufferRaw = $playerAPI->getExternalData(self::BUFFER, 0);
		return is_numeric($bufferRaw) ? (int) $bufferRaw : 0;
	}

	private function setBuffer(PlayerAPI $playerAPI, int $value) : void {
		$playerAPI->setExternalData(self::BUFFER, $value);
	}

	private function storeState(PlayerAPI $playerAPI, float $yaw, float $pitch, float $deltaYaw) : void {
		$playerAPI->setExternalData(self::LAST_YAW, $yaw);
		$playerAPI->setExternalData(self::LAST_PITCH, $pitch);
		$playerAPI->setExternalData(self::LAST_DELTA_YAW, $deltaYaw);
	}
}