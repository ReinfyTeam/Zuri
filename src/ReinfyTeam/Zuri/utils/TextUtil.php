<?php

namespace ReinfyTeam\Zuri\utils;

use pocketmine\utils\TextFormat;

final class TextUtil {
    
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

    public static function replaceText(string $text, array $replacements) : string {
        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }
}