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

namespace ReinfyTeam\Zuri\api\logging;

use ReinfyTeam\Zuri\APIProvider;
use ReinfyTeam\Zuri\components\log\ILog;
use function date;
use function fclose;
use function fopen;
use function fwrite;

class LogManager implements ILog {
	public static function contentLogger(string $text) : void {
		$today = date("Y-m-d");
		$file = fopen(APIProvider::getInstance()->getDataFolder() . "{$today}.txt", "a+") or die("Unable to open file!");
		fwrite($file, "[{$today} " . date("h:i:sA") . "] {$text}\n");
		fclose($file);
	}

	public static function sendLogger(string $text) : void {
		APIProvider::getInstance()->getLogger()->warning($text);
		LogManager::contentLogger($text);
	}
}