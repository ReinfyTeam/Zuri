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

use pocketmine\Server;
use ReinfyTeam\Zuri\config\ConfigManager;
use ReinfyTeam\Zuri\player\PlayerAPI;
use function base64_encode;
use function date;
use function is_string;
use function str_replace;
use function strtolower;
use function time;
use function zlib_encode;

class ReplaceText extends ConfigManager {
	public static function replace(string|PlayerAPI $player, string $text, string $module = "", string $subType = "") : string {
		$keys = [
			"{prefix}",
			"{player_name}",
			"{module}",
			"{subtype}",
			"{time}",
			"{realViolation}",
			"{violation}",
			"{timechat}",
			"{code}",
			"{tps}",
			"{error_code}"
		];
		$replace = [
			self::getData(self::PREFIX),
			(is_string($player) ? $player : $player->getPlayer()->getName()),
			$module,
			$subType,
			date("F d, Y h:i:sA", time()),
			(is_string($player) ? "N/A"  : $player->getRealViolation($module)),
			(is_string($player) ? "N/A" : $player->getViolation($module)),
			self::getData(self::CHAT_SPAM_DELAY),
			(is_string($player) ? "N/A" : $player->getCaptchaCode($module)),
			Server::getInstance()->getTicksPerSecond(),
			base64_encode(zlib_encode(strtolower($module . $subType . date("F d, Y h:i:sA", time())), ZLIB_ENCODING_DEFLATE, 9))
		];

		$text = str_replace($keys, $replace, $text);

		return Utils::ParseColors($text);
	}
}
