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

namespace ReinfyTeam\Zuri\check\moving\speed;

use ReinfyTeam\Zuri\check\Check;
use ReinfyTeam\Zuri\player\PlayerZuri;
use ReinfyTeam\Zuri\utils\MathUtil;
use function abs;
use function in_array;
use function max;
use function min;


/**
 * Speed check type B for anti-cheat.
 */
class SpeedB extends Check {
	/**
	 * Returns the name of the check.
	 *
	 * @return string Human-readable name used in config keys (e.g., 'Speed').
	 */
	public function getName() : string {
		return "Speed";
	}


	/**
	 * Returns the subtype of the check.
	 *
	 * Subtypes are used to group related checks under a single config key.
	 *
	 * @return string Short subtype identifier (e.g., 'A').
	 */
	public function getSubType() : string {
		return "B";
	}


	/**
	 * Returns the type of the check (packet).
	 *
	 * @return int One of self::TYPE_PACKET, self::TYPE_PLAYER or self::TYPE_EVENT.
	 */
	public function getType() : int {
		return self::TYPE_PLAYER;
	}

	/**
	 * Runs the SpeedB check logic on the provided data.
	 *
	 * The worker provides a payload containing player and environment data.
	 * Implementations should return an array created via `self::buildResult`.
	 *
	 * @param array $data Worker payload (keys: 'type', 'playerData', 'constantData', etc.)
	 * @return array{failed:bool,debug:array} Result array with `failed` and `debug` keys.
	 */
	public static function check(array $data) : array {
		if ($data["type"] === "PlayerMoveEvent") {
			$playerData = $data["playerData"] ?? [];
			$movement = $playerData["movement"] ?? [];
			$externalData = $playerData["externalData"] ?? [];
			$from = $movement["from"] ?? [];
			$to = $movement["to"] ?? [];
			$fromX = (float) ($from["x"] ?? 0.0);
			$fromZ = (float) ($from["z"] ?? 0.0);
			$toX = (float) ($to["x"] ?? 0.0);
			$toZ = (float) ($to["z"] ?? 0.0);
			$state = (int) ($playerData["currentState"] ?? PlayerZuri::STATE_WALK);
			$deltaTicks = max(1.0, (float) ($playerData["deltaTicks"] ?? 1.0));
			$velocity = MathUtil::horizontalVelocity($fromX, $fromZ, $toX, $toZ, $deltaTicks);
			$actualSpeed = MathUtil::horizontalSpeed($velocity["x"], $velocity["z"]);
			$previousVelocity = $externalData["speedBVelocity"] ?? [];
			$previousVelocityX = (float) ($previousVelocity["x"] ?? 0.0);
			$previousVelocityZ = (float) ($previousVelocity["z"] ?? 0.0);
			$previousSpeed = MathUtil::horizontalSpeed($previousVelocityX, $previousVelocityZ);
			$acceleration = MathUtil::horizontalAcceleration(
				$velocity["x"],
				$velocity["z"],
				$previousVelocityX,
				$previousVelocityZ
			);
			$directionChange = MathUtil::directionChange(
				$velocity["x"],
				$velocity["z"],
				$previousVelocityX,
				$previousVelocityZ
			);
			$verticalError = (float) ($playerData["verticalError"] ?? 0.0);
			$verticalState = (int) ($playerData["verticalState"] ?? PlayerZuri::VERTICAL_GROUND);
			$specialVerticalState = in_array($state, [
				PlayerZuri::STATE_GRACE,
				PlayerZuri::STATE_GLIDING,
				PlayerZuri::STATE_CREATIVE,
				PlayerZuri::STATE_SPECTATOR,
				PlayerZuri::STATE_LAVA,
				PlayerZuri::STATE_SWIMMING,
				PlayerZuri::STATE_CLIMBING,
				PlayerZuri::STATE_STAIRS
			], true);

			if ($state === PlayerZuri::STATE_GRACE || ($playerData["isCurrentChunkLoaded"] ?? true) === false) {
				return self::buildResult(false, [], [
					"speedBVelocity" => $velocity,
					"speedBBuffer" => 0.0,
					"speedBVerticalBuffer" => 0.0
				]);
			}

			$expectedSpeed = MathUtil::movementSpeed(
				$state,
				(bool) ($playerData["isUnderwater"] ?? false),
				(bool) ($playerData["isSprinting"] ?? false),
				(bool) ($playerData["isSneaking"] ?? false),
				(bool) ($playerData["isStartedJumping"] ?? false) || !($playerData["isOnGround"] ?? true),
				(bool) ($playerData["twoBlockPassage"] ?? false),
				(float) ($playerData["pitch"] ?? 0.0),
				(int) ($playerData["speedLevel"] ?? 0),
				(int) ($playerData["slownessLevel"] ?? 0),
				(bool) ($playerData["isSoulSpeedSurface"] ?? false),
				(int) ($playerData["soulSpeedLevel"] ?? 0)
			);
			$expectedSpeed = MathUtil::iceSpeed(
				$expectedSpeed,
				$previousSpeed,
				$state === PlayerZuri::STATE_ICE,
				(int) ($playerData["previousSurface"] ?? PlayerZuri::SURFACE_UNKNOWN) === PlayerZuri::SURFACE_ICE
			);
			$externalVelocity = (int) ($playerData["externalVelocityTicks"] ?? 0) > 0 &&
				(int) ($playerData["horizontalVelocitySource"] ?? PlayerZuri::SOURCE_UNKNOWN) !== PlayerZuri::SOURCE_UNKNOWN
				? ($playerData["motion"] ?? ["x" => 0.0, "z" => 0.0])
				: ["x" => 0.0, "z" => 0.0];
			$allowedSpeed = MathUtil::applyExternalVelocity(
				$expectedSpeed,
				(float) ($externalVelocity["x"] ?? 0.0),
				(float) ($externalVelocity["z"] ?? 0.0)
			);
			$allowedSpeed += ($deltaTicks - 1.0) * $expectedSpeed;
			$allowedSpeed += ($expectedSpeed * 0.10) + 0.10;
			$allowedSpeed += min(4.0, $acceleration * 0.25);
			$allowedSpeed += min(1.0, $directionChange * 0.25);

			$excess = max(0.0, $actualSpeed - $allowedSpeed);
			$buffer = (float) ($externalData["speedBBuffer"] ?? 0.0);
			$verticalBuffer = (float) ($externalData["speedBVerticalBuffer"] ?? 0.0);
			$verticalExcess = $specialVerticalState || $verticalState === PlayerZuri::VERTICAL_GROUND
				? 0.0
				: max(0.0, abs($verticalError) - 0.35);
			$buffer = $excess > 0.25
				? min(10.0, $buffer + min(2.0, $excess * 0.5))
				: max(0.0, $buffer - 0.15);
			$verticalBuffer = $verticalExcess > 0.2
				? min(10.0, $verticalBuffer + min(2.0, $verticalExcess * 0.5))
				: max(0.0, $verticalBuffer - 0.15);
			$failed = $buffer >= 4.0 || $verticalBuffer >= 4.0;

			return self::buildResult($failed, [
				"state" => $state,
				"actualSpeed" => $actualSpeed,
				"expectedSpeed" => $expectedSpeed,
				"allowedSpeed" => $allowedSpeed,
				"excess" => $excess,
				"verticalError" => $verticalError,
				"verticalExcess" => $verticalExcess,
				"verticalState" => $verticalState,
				"acceleration" => $acceleration,
				"directionChange" => $directionChange,
				"buffer" => $buffer
			], [
				"speedBVelocity" => $velocity,
				"speedBBuffer" => $failed ? 0.0 : $buffer,
				"speedBVerticalBuffer" => $failed ? 0.0 : $verticalBuffer
			]);
		}

		return self::buildResult(false);
	}
}