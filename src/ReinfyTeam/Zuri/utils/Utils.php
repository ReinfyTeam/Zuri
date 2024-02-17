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

use ReinfyTeam\Zuri\APIProvider;
use function array_diff;
use function array_key_last;
use function basename;
use function explode;
use function implode;
use function pathinfo;
use function rtrim;
use function scandir;
use function str_replace;

class Utils {
	public static function getResourceFile(string $file) : string {
		return str_replace(["\\utils", "/utils"], DIRECTORY_SEPARATOR . "resources", __DIR__) . DIRECTORY_SEPARATOR . $file;
	}

	public static function callDirectory(string $directory, callable $callable) : void {
		$main = explode("\\", APIProvider::getInstance()->getDescription()->getMain());
		unset($main[array_key_last($main)]);
		$pathPlugin = APIProvider::getInstance()->getServer()->getPluginPath() . "/" . APIProvider::getInstance()->getDescription()->getName();
		$main = implode("/", $main);
		$directory = rtrim(str_replace(DIRECTORY_SEPARATOR, "/", $directory), "/");
		$dir = rtrim($pathPlugin, "/" . DIRECTORY_SEPARATOR) . "/" . "src/$main/" . $directory;
		foreach (array_diff(scandir($dir), [".", ".."]) as $file) {
			$path = $dir . "/$file";
			$extension = pathinfo($path)["extension"] ?? null;
			if ($extension === null) {
				self::callDirectory($directory . "/" . $file, $callable);
			} elseif ($extension === "php") {
				$namespaceDirectory = str_replace("/", "\\", $directory);
				$namespaceMain = str_replace("/", "\\", $main);
				$namespace = $namespaceMain . "\\$namespaceDirectory\\" . basename($file, ".php");
				$callable($namespace);
			}
		}
	}
}