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
use ReinfyTeam\Zuri\utils\Utils;
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
		if ((Utils::readString($data["type"]) ?? null) !== "PlayerMoveEvent") {
			return self::buildResult(false);
		}

		$playerData = Utils::readArray($data["playerData"]);
		$constantData = Utils::readArray($data["constantData"]);
		$externalData = Utils::readArray($playerData["externalData"]);

		if ($playerData["isRecentlyEnderPearled"]) {
			return self::buildResult(false); // Prevent flag with ender pearls teleportation.
		}

		$movement = Utils::readArray($playerData["movement"]);
		$from = Utils::arrayToVector3($movement["from"]);
		$to = Utils::arrayToVector3($movement["to"]);

		// Calculate distance (Old Y and New Y)
		// Delta Y is used to determine if the player is moving up or down, which affects the speed limit.
		$distance = abs($to->getX() - $from->getX());
		$deltaY = $to->getY() - $from->getY(); 
		$isMovingUp = $deltaY > 0.0001;
		$isMovingDown = $deltaY < -0.0001;

		$limit = 0.0;
		if (Utils::readBool($playerData["isOnStairs"])) {
			if ($isMovingUp) {
				$limit += Utils::readFloat($constantData[ConstantPath::SPEED_STAIRS_UP]);
			}
			if ($isMovingDown) {
				$limit += Utils::readFloat($constantData[ConstantPath::SPEED_STAIRS_DOWN]);
			}
		} elseif (Utils::readBool($playerData["isOnClimbable"])) {
			if ($isMovingUp) {
				$limit += Utils::readFloat($constantData[ConstantPath::SPEED_CLIMBABLE_UP]);
			}
			if ($isMovingDown) {
				$limit += Utils::readFloat($constantData[ConstantPath::SPEED_CLIMBABLE_DOWN]);
			}
		} elseif (Utils::readBool($playerData["isInMagmaBubble"])) {
			if ($isMovingDown) {
				$limit += Utils::readFloat($constantData[ConstantPath::SPEED_MAGMA_BUBBLE_DOWN]);
			}
		} elseif (Utils::readBool($playerData["isInSoulSandBubble"])) {
			if ($isMovingUp) {
				$limit += Utils::readFloat($constantData[ConstantPath::SPEED_SOUL_SAND_BUBBLE_UP]);
			}
		} elseif (Utils::readBool($playerData["isInLavafall"])) {
			if ($isMovingUp) {
				$limit += Utils::readFloat($constantData[ConstantPath::SPEED_LAVAFALL_FIRE_RESISTANCE]);
			}
		} elseif (Utils::readBool($playerData["isLevitating"])) {
			if ($isMovingUp) {
				$limit += Utils::readFloat($constantData[ConstantPath::SPEED_LEVITATION]);
			}
		} elseif ((Utils::readBool($playerData["iscreative"]) || Utils::readBool($playerData["isSpectator"])) && Utils::readBool($playerData["isFlying"])) {
			if ($isMovingUp || $isMovingDown) {
				$limit += Utils::readFloat($constantData[ConstantPath::SPEED_CREATIVE_SPECTATOR_VERTICAL]);
			}
		} elseif(!Utils::readBool($playerData["isOnGround"]) && $isMovingDown && !Utils::readBool($playerData["isOnLiquid"]) && !Utils::readBool($playerData["isOnClimbable"]) && !Utils::readBool($playerData["isGliding"])) {
    		$limit += Utils::readFloat($constantData[ConstantPath::SPEED_FALLING_PLAYER_TERMINAL]);
		} elseif (Utils::readBool($playerData["isOnScaffolding"])) {
			if ($isMovingUp) {
				$limit += Utils::readFloat($constantData[ConstantPath::SPEED_SCAFFOLDING_UP]);
			}

			if ($isMovingDown) {
				$limit += Utils::readFloat($constantData[ConstantPath::SPEED_SCAFFOLDING_DOWN]);
			}
		} elseif(Utils::readBool($playerData["isSwimming"])) {
			$limit += Utils::readFloat($constantData[ConstantPath::SPEED_SWIM_UNDERWATER]);
		} else {
			if(Utils::readBool($playerData["isRecentlyJumped"])) {
				$limit += Utils::readFloat($constantData[ConstantPath::SPEED_JUMPING]); // Base limit for jumping.
			}

			// Todo: Add additional vertical movement checks for other scenarios if needed.
		}

		$limit += $limit * $constantData[ConstantPath::THRESHOLD_PERCENTAGE]; // this ensure will be higher on limit so this certain percentage will be accurate as limits according to approximate values and prevent false-flagging.

		if (($result = $distance > $limit)) {
			return self::buildResult($result, [
				"distance" => $distance,
				"limit" => $limit
			]);
		}
	}
}