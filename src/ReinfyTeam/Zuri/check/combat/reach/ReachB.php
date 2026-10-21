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

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerInteractEvent;
use ReinfyTeam\Zuri\check\Check;
use ReinfyTeam\Zuri\config\ConstantPath;
use ReinfyTeam\Zuri\utils\MathUtil;
use ReinfyTeam\Zuri\utils\Utils;
use function is_a;
use function round;


/**
 * Reach check type B for anti-cheat.
 */
class ReachB extends Check {
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
		return "B";
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
		if (!($type === BlockPlaceEvent::class || is_a($type, BlockPlaceEvent::class, true)
			|| $type === BlockBreakEvent::class || is_a($type, BlockBreakEvent::class, true)
			|| $type === PlayerInteractEvent::class || is_a($type, PlayerInteractEvent::class, true))) {
			return self::buildResult(false);
		}

		$playerData = Utils::readArray($data["playerData"] ?? null);
		$constantData = Utils::readArray($data["constantData"] ?? null);
		$dataCheck = Utils::readArray($data["data"] ?? null);

		if (Utils::readBool($playerData["isSpectator"] ?? false)) {
			return self::buildResult(false); // we do not run on spectator players, as they can place blocks at any distance
		}

		/** @var array{x: float, y: float, z: float} $blockPosRaw */
		$blockPosRaw = Utils::readArray($dataCheck["blockPos"]);
		$blockPos = Utils::arrayToVector3($blockPosRaw);
		$movement = Utils::readArray($playerData["movement"] ?? null);
		/** @var array{x: float, y: float, z: float} $movementData */
		$movementData = Utils::readArray($movement["to"] ?? null);
		$playerPos = Utils::arrayToVector3($movementData);

		$eyeHeight = Utils::readFloat($playerData["eyeHeight"]);
		$distance = MathUtil::distanceReach($playerPos, $blockPos, $eyeHeight);

		$isCreative = Utils::readBool($playerData["isCreative"] ?? false);
		$isSurvival = Utils::readBool($playerData["isSurvival"] ?? false);

		$maxDistance = match (true) {
			$isCreative => Utils::readFloat($constantData[ConstantPath::REACH_CREATIVE_BLOCK_REACH]),
			$isSurvival => Utils::readFloat($constantData[ConstantPath::REACH_SURVIVAL_BLOCK_REACH]),
			default => 0.0, // spectator? its ignored anyways :/
		};
		$result = round($distance, 3) > round($maxDistance, 3);

		return self::buildResult($result, [
			"distance" => round($distance, 3),
			"maxDistance" => round($maxDistance, 3),
		]);
	}
}
