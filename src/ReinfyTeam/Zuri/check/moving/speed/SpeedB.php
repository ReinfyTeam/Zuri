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
use ReinfyTeam\Zuri\config\ConstantPath;
use ReinfyTeam\Zuri\utils\Utils;
use function abs;
use function is_string;
use function max;
use function pow;
use function str_ends_with;


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
	 * Runs the Speed (B) check logic on the provided data.
	 * Speed (B) focuses on vertical logic for the base threshold for speed.
	 *
	 * The worker provides a payload containing player and environment data.
	 * Implementations should return an array created via `self::buildResult`.
	 *
	 * @param array<string,mixed> $data Worker payload (keys: 'type', 'playerData', 'constantData', etc.)
	 * @return array{failed:bool,debug:array<array-key,mixed>,externalData:array<string,mixed>} Result array with `failed` and `debug` keys.
	 */
	public static function check(array $data) : array {
		$type = $data["type"] ?? null;
		if ($type !== "PlayerMoveEvent" && !(is_string($type) && str_ends_with($type, "\\PlayerMoveEvent"))) {
			return self::buildResult(false);
		}

		$playerData = Utils::readArray($data["playerData"] ?? null);
		$constantData = Utils::readArray($data["constantData"] ?? null);
		$externalData = Utils::readArray($playerData["externalData"] ?? null);

		if (Utils::readBool($playerData["isRecentlyEnderPearled"] ?? false)) {
			return self::buildResult(false); // Prevent flag with ender pearls teleportation.
		}
		if (Utils::readBool($playerData["isRecentlyCancelledEvent"] ?? false)) {
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

		// Calculate distance (Old Y and New Y)
		// Delta Y is used to determine if the player is moving up or down, which affects the speed limit.
		$distance = abs($to->getY() - $from->getY());
		$deltaY = $to->getY() - $from->getY();
		$isMovingUp = $deltaY > 0.0001;
		$isMovingDown = $deltaY < -0.0001;

		$limit = 0.0;
		if (Utils::readBool($playerData["isOnStairs"] ?? false)) {
			if ($isMovingUp) {
				$limit += Utils::readFloat($constantData[ConstantPath::SPEED_STAIRS_UP]);
			}
			if ($isMovingDown) {
				$limit += Utils::readFloat($constantData[ConstantPath::SPEED_STAIRS_DOWN]);
			}
		} elseif (Utils::readBool($playerData["isOnClimbable"] ?? ($playerData["isClimbing"] ?? false))) {
			if ($isMovingUp) {
				$limit += Utils::readFloat($constantData[ConstantPath::SPEED_CLIMBABLE_UP]);
			}
			if ($isMovingDown) {
				$limit += Utils::readFloat($constantData[ConstantPath::SPEED_CLIMBABLE_DOWN]);
			}
		} elseif (Utils::readBool($playerData["isInMagmaBubble"] ?? false)) {
			if ($isMovingDown) {
				$limit += Utils::readFloat($constantData[ConstantPath::SPEED_MAGMA_BUBBLE_DOWN]);
			}
		} elseif (Utils::readBool($playerData["isInSoulSandBubble"] ?? false)) {
			if ($isMovingUp) {
				$limit += Utils::readFloat($constantData[ConstantPath::SPEED_SOUL_SAND_BUBBLE_UP]);
			}
		} elseif (Utils::readBool($playerData["isInLavafall"] ?? false)) {
			if ($isMovingUp) {
				$limit += Utils::readFloat($constantData[ConstantPath::SPEED_LAVAFALL_FIRE_RESISTANCE]);
			}
		} elseif (Utils::readBool($playerData["isLevitating"] ?? false)) {
			if ($isMovingUp) {
				$limit += Utils::readFloat($constantData[ConstantPath::SPEED_LEVITATION]);
			}
		} elseif ((Utils::readBool($playerData["isCreative"] ?? false) || Utils::readBool($playerData["isSpectator"] ?? false)) && Utils::readBool($playerData["isFlying"] ?? false)) {
			if ($isMovingUp || $isMovingDown) {
				$limit += Utils::readFloat($constantData[ConstantPath::SPEED_CREATIVE_SPECTATOR_VERTICAL]);
			}
		} elseif (!Utils::readBool($playerData["isOnGround"] ?? false) && $isMovingDown && !Utils::readBool($playerData["isOnLiquid"] ?? ($playerData["isInLiquid"] ?? false)) && !Utils::readBool($playerData["isOnClimbable"] ?? ($playerData["isClimbing"] ?? false)) && !Utils::readBool($playerData["isGliding"] ?? false)) { // @phpstan-ignore booleanNot.alwaysTrue
			// Calculations from https://minecraft.fandom.com/wiki/Transportation
			// Falling terminal calculation player logic
			$fallTicks = Utils::readInt($playerData["getFallTicks"] ?? 0);

			// fetch the initial vertical motion when the player left the ground
			$vt0 = Utils::arrayToVector3(Utils::readArray($playerData["getMotion"] ?? ($playerData["motion"] ?? null)))->getY(); // @phpstan-ignore argument.type

			// Apply exact displacement formula with initial conditions
			// Shift N by +1 to account for the formula's internal 1-tick latency offset
			$N = $fallTicks + 1;

			$currentDisplacement = 50 * ($vt0 + 3.92) * (1 - pow(0.98, $N)) - (3.92 * $N);
			$previousDisplacement = 50 * ($vt0 + 3.92) * (1 - pow(0.98, max(0, $N - 1))) - (3.92 * max(0, $N - 1));

			// Calculate the delta step limit allowed for this single frame
			$limit = abs($currentDisplacement - $previousDisplacement);

			// Fallback floor boundary safety layer for the first tick frame
			if ($limit < 0.08) {
				$limit = 0.08;
			}
		} elseif (Utils::readBool($playerData["isOnScaffold"] ?? false)) {
			if ($isMovingUp) {
				$limit += Utils::readFloat($constantData[ConstantPath::SPEED_SCAFFOLDING_UP]);
			}

			if ($isMovingDown) {
				$limit += Utils::readFloat($constantData[ConstantPath::SPEED_SCAFFOLDING_DOWN]);
			}
		} elseif (Utils::readBool($playerData["isSwimming"] ?? false)) {
			$limit += Utils::readFloat($constantData[ConstantPath::SPEED_SWIM_UNDERWATER]);
		} else {
			if (Utils::readBool($playerData["isJumping"] ?? false)) {
				$limit += Utils::readFloat($constantData[ConstantPath::SPEED_JUMPING]); // Base limit for jumping.
			}

			// Todo: Add additional vertical movement checks for other scenarios if needed.
		}

		$limit += $limit * Utils::readFloat($constantData[ConstantPath::THRESHOLD_PERCENTAGE] ?? null); // this ensure will be higher on limit so this certain percentage will be accurate as limits according to approximate values and prevent false-flagging.
		$result = $distance > $limit;

		return self::buildResult($result, [
			"distance" => $distance,
			"limit" => $limit
		]);
	}
}
