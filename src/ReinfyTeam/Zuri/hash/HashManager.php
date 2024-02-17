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

namespace ReinfyTeam\Zuri\hash;

use ReinfyTeam\Zuri\APIProvider;
use function fclose;
use function file_get_contents;
use function fopen;
use function fwrite;
class HashManager {
	public static function getHashCode() {
		$path = APIProvider::getInstance()->getDataFolder() . "hash.txt";
		$read = file_get_contents($path);
		return $read;
	}

	public static function setHashCode(mixed $data) {
		$file = fopen(APIProvider::getInstance()->getDataFolder() . "hash.txt", "w") or die("Unable to open file!");
		fwrite($file, "$data");
		fclose($file);
	}
}