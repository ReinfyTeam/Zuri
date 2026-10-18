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
use function abs;
use function is_a;


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

		if (Utils::readBool($playerData["isRecentlyEnderPearled"] ?? false) && Utils::readBool($playerData["hasNoClientPredictions"] ?? false)) {
			return self::buildResult(false); // Prevent flag with ender pearls teleportation and frozen/no client prediction movements.
		}
		if (Utils::readBool($playerData["isRecentlyCancelledEvent"] ?? false)) {
			return self::buildResult(false);
		}
		if (
			Utils::readFloat($playerData["teleportTicks"] ?? 100.0) < 60.0 ||
			Utils::readFloat($playerData["teleportCommandTicks"] ?? 100.0) < 40.0
		) {
			return self::buildResult(false);
		}
		if (!Utils::readBool($playerData["isCurrentChunkLoaded"] ?? true)) {
			return self::buildResult(false);
		}

		$movement = Utils::readArray($playerData["movement"] ?? null);
		if (!isset($movement["from"], $movement["to"])) {
			return self::buildResult(false);
		}
		$from = Utils::arrayToVector3($movement["from"]); // @phpstan-ignore argument.type
		$to = Utils::arrayToVector3($movement["to"]); // @phpstan-ignore argument.type

		// Calculate distance (X, Y)
		$distance = MathUtil::horizontalDistance($from, $to);
		$blocksPerSeconds = MathUtil::calculateBPS(abs($distance), Utils::readFloat($playerData["lastMoveTicks"]));

		if ($blocksPerSeconds === 0.0) { // ignore to prevent spam on 0 BPS
			return self::buildResult(false);
		}

		$limit = 0.0;
		$baseLimit = Utils::readFloat($constantData[ConstantPath::SPEED_WALKING] ?? 0.0);
		$isWalking = true;
		$applyEffectMultiplier = true;
		if (Utils::readBool($playerData["isCreative"] ?? false) && Utils::readBool($playerData["isFlying"] ?? false)) { // Creative flight
			$isWalking = false;
			$applyEffectMultiplier = false;
			$baseLimit = Utils::readBool($playerData["isSprinting"] ?? false) ? Utils::readFloat($constantData[ConstantPath::SPEED_CREATIVE_SPRINT_FLIGHT] ?? 0.0) : Utils::readFloat($constantData[ConstantPath::SPEED_CREATIVE_FLIGHT] ?? 0.0);
		} elseif (Utils::readBool($playerData["isSpectator"] ?? false) && Utils::readBool($playerData["isFlying"] ?? false)) { // Spectator flight
			$isWalking = false;
			$applyEffectMultiplier = false;
			$baseLimit = Utils::readBool($playerData["isSprinting"] ?? false) ? Utils::readFloat($constantData[ConstantPath::SPEED_SPECTATOR_SPRINT_FLIGHT] ?? 0.0) : Utils::readFloat($constantData[ConstantPath::SPEED_SPECTATOR_FLIGHT] ?? 0.0);
		} elseif (Utils::readBool($playerData["isSprinting"] ?? false)) { // Sprinting
			$isWalking = false;
			$baseLimit = Utils::readBool($playerData["isRecentlyJumped"] ?? false) ? Utils::readFloat($constantData[ConstantPath::SPEED_SPRINT_JUMP] ?? 0.0) : Utils::readFloat($constantData[ConstantPath::SPEED_SPRINT] ?? 0.0); // Sprinting and jumping

			if (Utils::readBool($playerData["hasTwoBlockPassage"] ?? false)) {
				$limit += Utils::readFloat($constantData[ConstantPath::SPEED_SPRINT_JUMP_2_BLOCK_PASSAGE] ?? 0.0)
				- $baseLimit;
			}
		} elseif (Utils::readBool($playerData["isSneaking"] ?? false)) { // Sneaking
			$isWalking = false;
			$baseLimit = Utils::readFloat($constantData[ConstantPath::SPEED_SNEAKING_FLAT] ?? 0.0);

			if (MathUtil::isDiagonal($from, $to)) {
				$limit += Utils::readFloat($constantData[ConstantPath::SPEED_SNEAKING_DIAGONAL] ?? 0.0) - $baseLimit;
			}

			if (Utils::readBool($playerData["isOnSoul"] ?? false)) {
				$limit += Utils::readFloat($constantData[ConstantPath::SPEED_SNEAKING_SOUL] ?? 0.0) - $baseLimit;
			}

			if (Utils::readBool($playerData["hasSwiftSneak"] ?? false)) {
				$limit += Utils::readFloat($constantData[ConstantPath::SPEED_SNEAKING_SWIFT] ?? 0.0) - $baseLimit;
			}
		} elseif (Utils::readBool($playerData["isSwimming"] ?? false)) { // Swimming
			$isWalking = false;
			$baseLimit = Utils::readFloat($constantData[ConstantPath::SPEED_SWIM_SURFACE] ?? 0.0);


			if (Utils::readBool($playerData["isDiving"] ?? false)) {
				$limit += Utils::readFloat($constantData[ConstantPath::SPEED_SWIM_DIVING] ?? 0.0) - $baseLimit;
			}
		}

		$limit += $baseLimit;
		if ($isWalking && MathUtil::isDiagonal($from, $to)) {
			$limit = Utils::readFloat($constantData[ConstantPath::SPEED_WALK_DIAGONAL] ?? 0.0);
		}

		// this ensure will be higher on limit so this certain percentage will be accurate as limits according to approximate values and prevent false-flagging.
		$limit += $baseLimit * Utils::readFloat($constantData[ConstantPath::TOLERANCE_PERCENTAGE] ?? 0.0);
		if ($applyEffectMultiplier) {
			$limit *= Utils::getEffectMovementMultiplier(Utils::readArray($playerData["effects"] ?? null));
		}
		$result = $blocksPerSeconds > $limit;

		return self::buildResult($result, [
			"limit" => $limit,
			"blocksPerSeconds" => $blocksPerSeconds,
			"distance" => $distance
		]);
	}
}
