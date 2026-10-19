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

use pocketmine\event\player\PlayerMoveEvent;
use ReinfyTeam\Zuri\check\Check;
use ReinfyTeam\Zuri\config\ConstantPath;
use ReinfyTeam\Zuri\utils\MathUtil;
use ReinfyTeam\Zuri\utils\Utils;
use function is_a;
use function max;
use function round;


/**
 * Speed check type A for anti-cheat.
 */
class SpeedA extends Check {
	/**
	 * Returns the name of the check.
	 *
	 * @return string Human-readable name used in config keys (e.g., 'Speed').
	 */
	public static function getName() : string {
		return "Speed";
	}


	/**
	 * Returns the subtype of the check.
	 *
	 * Subtypes are used to group related checks under a single config key.
	 *
	 * @return string Short subtype identifier (e.g., 'A').
	 */
	public static function getSubType() : string {
		return "A";
	}


	/**
	 * Returns the type of the check (packet).
	 *
	 * @return int One of self::TYPE_PACKET, self::TYPE_PLAYER or self::TYPE_EVENT.
	 */
	public static function getType() : int {
		return self::TYPE_PLAYER;
	}

	/**
	 * Runs the Speed (A) check logic on the provided data.
	 * Speed (A) focuses on horizontal logic for base threshold for speed.
	 *
	 * The worker provides a payload containing player and environment data.
	 * Implementations should return an array created via `self::buildResult`.
	 *
	 * @param array<string,mixed> $data Worker payload (keys: 'type', 'playerData', 'constantData', etc.)
	 * @return array{failed:bool,debug:array<array-key,mixed>,externalData:array<string,mixed>} Result array with `failed` and `debug` keys.
	 */
	public static function check(array $data) : array {
		$type = Utils::readString($data["type"] ?? null);
		if ($type !== PlayerMoveEvent::class && !is_a($type, PlayerMoveEvent::class, true)) {
			return self::buildResult(false);
		}

		$playerData = Utils::readArray($data["playerData"] ?? null);
		$constantData = Utils::readArray($data["constantData"] ?? null);

		/** @var array{from: array{x: float, y: float, z: float}, to: array{x: float, y: float, z: float}} $movement */
		$movement = Utils::readArray($playerData["movement"]);
		$from = Utils::arrayToVector3($movement["from"]);
		$to = Utils::arrayToVector3($movement["to"]);

		$moveElapsedTicks = Utils::readInt($playerData["moveElapsedTicks"] ?? -1);
		$blocksPerSeconds = round(MathUtil::calculateHorizontalBPS($from, $to, $moveElapsedTicks), 3);

		if ($blocksPerSeconds === 0.0) {
			return self::buildResult(false);
		}

		$isSprinting = Utils::readBool($playerData["isSprinting"] ?? false);
		$isSneaking = Utils::readBool($playerData["isSneaking"] ?? false);
		$isCrawling = Utils::readBool($playerData["isCrawling"] ?? false);
		$isSwimming = Utils::readBool($playerData["isSwimming"] ?? false);
		$isCreative = Utils::readBool($playerData["isCreative"] ?? false);
		$isSpectator = Utils::readBool($playerData["isSpectator"] ?? false);
		$isFlying = Utils::readBool($playerData["isFlying"] ?? false);

		if ($isSprinting) {
			$limit = match (true) {
				$isSpectator && $isFlying => Utils::readFloat($constantData[ConstantPath::SPEED_SPECTATOR_SPRINT_FLIGHT]),
				$isCreative && $isFlying => Utils::readFloat($constantData[ConstantPath::SPEED_CREATIVE_SPRINT_FLIGHT]),
				default => Utils::readFloat($constantData[ConstantPath::SPEED_SPRINTING]),
			};
		} else {
			$limit = match (true) {
				$isSwimming => Utils::readFloat($constantData[ConstantPath::SPEED_SWIMMING]),
				$isSneaking => Utils::readFloat($constantData[ConstantPath::SPEED_SNEAKING]),
				$isCrawling => Utils::readFloat($constantData[ConstantPath::SPEED_CRAWLING]),
				$isCreative && $isFlying => Utils::readFloat($constantData[ConstantPath::SPEED_CREATIVE_FLIGHT]),
				$isSpectator && $isFlying => Utils::readFloat($constantData[ConstantPath::SPEED_SPECTATOR_FLIGHT]),
				default => Utils::readFloat($constantData[ConstantPath::SPEED_WALKING]),
			};
		}

		// Calculate Effects
		/** @var array{name: string, amplifier: int, duration: int}[] $playerEffects */
		$playerEffects = Utils::readArray($playerData["effects"]);
		$effectMultiplier = Utils::getEffectMovementHorizontalMultiplier($playerEffects);
		$limit *= $effectMultiplier;

		$threshold = round($limit * (1.0 + max(0.0, Utils::readFloat($constantData[ConstantPath::TOLERANCE_PERCENTAGE] ?? 0.0))), 3);
		$failed = $blocksPerSeconds > $threshold;

		return self::buildResult($failed, [
			"bps" => $blocksPerSeconds,
			"threshold" => $threshold
		]);
	}
}
