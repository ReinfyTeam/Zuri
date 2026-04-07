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
use function max;

class RotationA extends Check {
	private const string TYPE = "RotationA";
	private const string LAST_YAW = CacheData::ROTATION_A_LAST_YAW;
	private const string LAST_PITCH = CacheData::ROTATION_A_LAST_PITCH;
	private const string LAST_DELTA_YAW = CacheData::ROTATION_A_LAST_DELTA_YAW;
	private const string LAST_DELTA_PITCH = CacheData::ROTATION_A_LAST_DELTA_PITCH;
	private const string BUFFER = CacheData::ROTATION_A_BUFFER;

	public function getName() : string {
		return "Rotation";
	}

	public function getSubType() : string {
		return "A";
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		if (!$packet instanceof PlayerAuthInputPacket) {
			return;
		}

		$player = $playerAPI->getPlayer();
		if (!$player->isSurvival() || (int) $playerAPI->getPing() > (int) $this->getConstant(CheckConstants::ROTATIONA_MAX_PING)) {
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
		$lastDeltaYaw = (float) $playerAPI->getExternalData(self::LAST_DELTA_YAW, $deltaYaw);
		$lastDeltaPitch = (float) $playerAPI->getExternalData(self::LAST_DELTA_PITCH, $deltaPitch);

		$inCombatWindow = $playerAPI->getAttackTicks() < (float) $this->getConstant(CheckConstants::ROTATIONA_COMBAT_WINDOW_TICKS);
		$isPatternStable =
			$deltaYaw >= (float) $this->getConstant(CheckConstants::ROTATIONA_MIN_DELTA_YAW) &&
			$deltaYaw <= (float) $this->getConstant(CheckConstants::ROTATIONA_MAX_DELTA_YAW) &&
			$deltaPitch >= (float) $this->getConstant(CheckConstants::ROTATIONA_MIN_DELTA_PITCH) &&
			$deltaPitch <= (float) $this->getConstant(CheckConstants::ROTATIONA_MAX_DELTA_PITCH) &&
			abs($deltaYaw - $lastDeltaYaw) <= (float) $this->getConstant(CheckConstants::ROTATIONA_YAW_STEP_EPSILON) &&
			abs($deltaPitch - $lastDeltaPitch) <= (float) $this->getConstant(CheckConstants::ROTATIONA_PITCH_STEP_EPSILON);

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
			"bufferLimit" => (int) $this->getConstant(CheckConstants::ROTATIONA_BUFFER_LIMIT),
		]);
	}

	public static function evaluateAsync(array $payload) : array {
		if (($payload["type"] ?? null) !== self::TYPE) {
			return [];
		}

		$buffer = (int) ($payload["buffer"] ?? 0);
		if ((bool) ($payload["inCombatWindow"] ?? false) && (bool) ($payload["isPatternStable"] ?? false)) {
			$buffer++;
		} else {
			$buffer = max(0, $buffer - 1);
		}

		$result = [
			"set" => [self::BUFFER => $buffer],
			"debug" => "deltaYaw=" . (float) ($payload["deltaYaw"] ?? 0.0) . ", deltaPitch=" . (float) ($payload["deltaPitch"] ?? 0.0) . ", lastDeltaYaw=" . (float) ($payload["lastDeltaYaw"] ?? 0.0) . ", lastDeltaPitch=" . (float) ($payload["lastDeltaPitch"] ?? 0.0) . ", buffer={$buffer}",
		];

		if ($buffer >= (int) ($payload["bufferLimit"] ?? 0)) {
			$result["set"][self::BUFFER] = 0;
			$result["failed"] = true;
		}

		return $result;
	}

	private function angleDelta(float $from, float $to) : float {
		return abs(fmod(($to - $from + 540.0), 360.0) - 180.0);
	}

	private function getBuffer(PlayerAPI $playerAPI) : int {
		return (int) $playerAPI->getExternalData(self::BUFFER, 0);
	}

	private function setBuffer(PlayerAPI $playerAPI, int $value) : void {
		$playerAPI->setExternalData(self::BUFFER, $value);
	}

	private function storeAngles(PlayerAPI $playerAPI, float $yaw, float $pitch, float $deltaYaw, float $deltaPitch) : void {
		$playerAPI->setExternalData(self::LAST_YAW, $yaw);
		$playerAPI->setExternalData(self::LAST_PITCH, $pitch);
		$playerAPI->setExternalData(self::LAST_DELTA_YAW, $deltaYaw);
		$playerAPI->setExternalData(self::LAST_DELTA_PITCH, $deltaPitch);
	}
}
