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

namespace ReinfyTeam\Zuri\utils;

use JsonMapper;
use JsonMapper_Exception;
use pocketmine\entity\Attribute;
use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\JwtException;
use pocketmine\network\mcpe\JwtUtils;
use pocketmine\network\mcpe\protocol\types\login\AuthenticationData;
use pocketmine\network\mcpe\protocol\types\login\JwtChain;
use pocketmine\network\PacketHandlingException;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function array_keys;
use function array_values;
use function is_array;
use function is_string;
use function mt_getrandmax;
use function mt_rand;
use function sqrt;
use function str_replace;

class Utils {
	public static function ParseColors($text, bool $reverse = false) : string {
		if (!is_string($text)) {
			return "";
		}

		$colors = [
			"{BLACK}" => TextFormat::BLACK,
			"{DARK_BLUE}" => TextFormat::DARK_BLUE,
			"{DARK_GREEN}" => TextFormat::DARK_GREEN,
			"{DARK_AQUA}" => TextFormat::DARK_AQUA,
			"{DARK_RED}" => TextFormat::DARK_RED,
			"{DARK_PURPLE}" => TextFormat::DARK_PURPLE,
			"{DARK_GRAY}" => TextFormat::DARK_GRAY,
			"{LIGHT_PURPLE}" => TextFormat::LIGHT_PURPLE,
			"{GOLD}" => TextFormat::GOLD,
			"{GRAY}" => TextFormat::GRAY,
			"{BLUE}" => TextFormat::BLUE,
			"{GREEN}" => TextFormat::GREEN,
			"{AQUA}" => TextFormat::AQUA,
			"{RED}" => TextFormat::RED,
			"{YELLOW}" => TextFormat::YELLOW,
			"{WHITE}" => TextFormat::WHITE,
			"{MINECOIN_GOLD}" => TextFormat::MINECOIN_GOLD,
		];

		$formats = [
			"&" => TextFormat::ESCAPE,
			"{ESCAPE}" => TextFormat::ESCAPE,
			"{OBFUSCATED}" => TextFormat::OBFUSCATED,
			"{BOLD}" => TextFormat::BOLD,
			"{STRIKETHROUGH}" => TextFormat::STRIKETHROUGH,
			"{UNDERLINE}" => TextFormat::UNDERLINE,
			"{ITALIC}" => TextFormat::ITALIC,
		];

		if ($reverse) {
			$text = str_replace(array_values($colors), array_keys($colors), $text);
			$text = str_replace(array_values($formats), array_keys($formats), $text);
		} else {
			$text = str_replace(array_keys($colors), array_values($colors), $text);
			$text = str_replace(array_keys($formats), array_values($formats), $text);
		}

		return $text;
	}

	// Grabbed from PMMP LOL
	public static function calculatePossibleKnockback(Player $player, float $x, float $z, float $force = Living::DEFAULT_KNOCKBACK_FORCE, ?float $verticalLimit = Living::DEFAULT_KNOCKBACK_VERTICAL_LIMIT) : ?Vector3 {
		$f = sqrt($x * $x + $z * $z);
		if ($f <= 0) {
			return null;
		}
		if (mt_rand() / mt_getrandmax() > $player->getAttributeMap()->get(Attribute::KNOCKBACK_RESISTANCE)->getValue()) {
			$f = 1 / $f;

			$motionX = $player->getMotion()->x / 2;
			$motionY = $player->getMotion()->y / 2;
			$motionZ = $player->getMotion()->z / 2;
			$motionX += $x * $f * $force;
			$motionY += $force;
			$motionZ += $z * $f * $force;

			$verticalLimit ??= $force;
			if ($motionY > $verticalLimit) {
				$motionY = $verticalLimit;
			}

			return new Vector3($motionX, $motionY, $motionZ);
		}

		return null;
	}

	public static function fetchAuthData(JwtChain $chain) : AuthenticationData {
		/** @var AuthenticationData|null $extraData */
		$extraData = null;
		foreach ($chain->chain as $jwt) {
			// Validate every chain element
			try {
				[, $claims,] = JwtUtils::parse($jwt);
			} catch(JwtException $e) {
				throw PacketHandlingException::wrap($e);
			}
			if (isset($claims["extraData"])) {
				if ($extraData !== null) {
					throw new PacketHandlingException("Found 'extraData' more than once in chainData");
				}

				if (!is_array($claims["extraData"])) {
					throw new PacketHandlingException("'extraData' key should be an array");
				}
				$mapper = new JsonMapper;
				$mapper->bEnforceMapType = false; //TODO: we don't really need this as an array, but right now we don't have enough models
				$mapper->bExceptionOnMissingData = true;
				$mapper->bExceptionOnUndefinedProperty = true;
				try {
					/** @var AuthenticationData $extraData */
					$extraData = $mapper->map($claims["extraData"], new AuthenticationData);
				} catch(JsonMapper_Exception $e) {
					throw PacketHandlingException::wrap($e);
				}
			}
		}
		if ($extraData === null) {
			throw new PacketHandlingException("'extraData' not found in chain data");
		}
		return $extraData;
	}
}