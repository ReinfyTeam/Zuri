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

use pocketmine\utils\TextFormat;
use function array_keys;
use function array_values;
use function implode;
use function is_array;
use function is_string;
use function str_replace;

/**
 * Utility functions for text parsing and replacements.
 */
final class TextUtil {
	/**
	 * Parses custom color tokens into TextFormat codes (or reverses them).
	 *
	 * @param mixed $text The text to parse.
	 * @param bool $reverse When true, converts TextFormat codes back to tokens.
	 */
	public static function parseColors($text, bool $reverse = false) : string {
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

	/**
	 * Replaces keys in the text with provided replacements.
	 *
	 * @param array<string,string> $replacements
	 */
	public static function replaceText(string $text, array $replacements) : string {
		return str_replace(array_keys($replacements), array_values($replacements), $text);
	}

	/**
	 * Pretty prints a nested array into a compact string representation.
	 */
	public static function prettyPrintArray(array $array) : string {
		$parts = [];

		foreach ($array as $key => $value) {
			if (is_array($value)) {
				$parts[] = $key . '=[' . self::prettyPrintArray($value) . ']';
			} else {
				$parts[] = $key . '=' . $value;
			}
		}

		return implode(', ', $parts);
	}
}