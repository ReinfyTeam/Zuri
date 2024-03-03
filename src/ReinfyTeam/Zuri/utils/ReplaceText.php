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
 * @author ReinfyTeam
 * @link https://github.com/ReinfyTeam/
 *
 *
 */

declare(strict_types=1);

namespace ReinfyTeam\Zuri\utils;

use ReinfyTeam\Zuri\config\ConfigManager;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\Utils;
use pocketmine\Server;
use function date;
use function microtime;
use function str_replace;
use function time;

class ReplaceText extends ConfigManager {
	public static function replace(PlayerAPI $player, string $text, string $module = "", string $subType = "") : string {
		$keys = [
			"{prefix}",
			"{player}",
			"{module}",
			"{subtype}",
			"{time}",
			"{violation}",
			"{timechat}",
			"{code}",
			"{tps}"
		];
		$replace = [
			self::getData(self::PREFIX),
			$player->getPlayer()->getName(),
			$module,
			$subType,
			date("F d, Y h:i:sA", time()),
			$player->getRealViolation($module),
			self::getData(self::CHAT_SPAM_DELAY),
			$player->getCaptchaCode(),
			Server::getInstance()->getTicksPerSecond()
		];
		
		$text = str_replace($keys, $replace, $text);
		
		return Utils::ParseColors($text);
	}
}
