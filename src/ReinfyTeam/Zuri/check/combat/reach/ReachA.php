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

namespace ReinfyTeam\Zuri\check\combat\reach;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use ReinfyTeam\Zuri\check\Check;
use ReinfyTeam\Zuri\config\ConstantPath;
use ReinfyTeam\Zuri\utils\MathUtil;
use ReinfyTeam\Zuri\utils\Utils;
use function is_a;
use function is_array;
use function round;


/**
 * Reach check type A for anti-cheat.
 */
class ReachA extends Check {
	/**
	 * Returns the name of the check.
	 *
	 * @return string Human-readable name used in config keys (e.g., 'Speed').
	 */
	public static function getName() : string {
		return "Reach";
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
	 * Runs the Reach (A) check logic on the provided data.
	 * Reach (A) focuses on distances of attacks and interactions to detect potential reach hacks.
	 *
	 * The worker provides a payload containing player and environment data.
	 * Implementations should return an array created via `self::buildResult`.
	 *
	 * @param array<string,mixed> $data Worker payload (keys: 'type', 'playerData', 'constantData', etc.)
	 * @return array{failed:bool,debug:array<array-key,mixed>,externalData:array<string,mixed>} Result array with `failed` and `debug` keys.
	 */
	public static function check(array $data) : array {
		$type = Utils::readString($data["type"] ?? null);
		if ($type !== EntityDamageByEntityEvent::class && !is_a($type, EntityDamageByEntityEvent::class, true)) {
			return self::buildResult(false);
		}

		$playerData = Utils::readArray($data["playerData"] ?? null);
		$constantData = Utils::readArray($data["constantData"] ?? null);
		$dataCheck = Utils::readArray($data["data"] ?? null);

		$enemyZuri = Utils::readArray($dataCheck["enemy"] ?? null);

		$enemyZuriMovement = Utils::readArray($enemyZuri["movement"] ?? null);
		$playerMovement = Utils::readArray($playerData["movement"] ?? null);

		$enemyMovementPos = $enemyZuriMovement["to"] ?? null;
		$playerMovementPos = $playerMovement["to"] ?? null;
		if (!is_array($enemyMovementPos) || !is_array($playerMovementPos)) {
			return self::buildResult(false);
		}

		/** @var array{x:float,y:float,z:float} $enemeyMovementPos */
		$enemeyMovementPos = $enemyMovementPos;
		/** @var array{x:float,y:float,z:float} $playerMovementPos */
		$playerMovementPos = $playerMovementPos;

		$distance = round(MathUtil::distanceReach(Utils::arrayToVector3($enemeyMovementPos), Utils::arrayToVector3($playerMovementPos), Utils::readFloat($playerData["eyeHeight"] ?? 0.0)), 3);
		$isCreative = Utils::readBool($playerData["isCreative"] ?? false) && !Utils::readBool($playerData["isSpectator"] ?? false);
		$reachLimit = round($isCreative ? Utils::readFloat($constantData[ConstantPath::REACH_CREATIVE]) :      Utils::readFloat($constantData[ConstantPath::REACH_SURVIVAL]), 3);

		$threshold = round($reachLimit * (1.0 + max(0.0, Utils::readFloat($constantData[ConstantPath::TOLERANCE_PERCENTAGE] ?? 0.0))), 3); // allowance for if possible miscalculations to prevent false-flagging

		return self::buildResult($distance > $threshold, [
			"distance" => $distance,
			"reachLimit" => $reachLimit,
			"threshold" => $threshold
		]);
	}
}
