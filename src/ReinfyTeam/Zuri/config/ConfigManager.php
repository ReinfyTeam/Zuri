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

namespace ReinfyTeam\Zuri\config;

use ReinfyTeam\Zuri\APIProvider;
use pocketmine\utils\TextFormat;

class ConfigManager extends ConfigPaths {
	public static function getData(string $path) {
		return APIProvider::getInstance()->getConfig()->getNested($path);
	}

	public static function setData(string $path, $data) {
		APIProvider::getInstance()->getConfig()->setNested($path, $data);
		APIProvider::getInstance()->getConfig()->save();
	}
	
	public static function checkConfig() : void {
		
		if(!file_exists(APIProvider::getInstance()->getDataFolder() . "config.yml")) {
			APIProvider::getInstance()->saveResource("config.yml");
			return;
		}
		
		$pluginConfigResource = APIProvider::getInstance()->getResource("config.yml");
		$pluginConfig = yaml_parse(stream_get_contents($pluginConfigResource));
		fclose($pluginConfigResource);
		$config = APIProvider::getInstance()->getConfig();
		$log = APIProvider::getInstance()->getServer()->getLogger();
		if ($pluginConfig == false) {
			$log->critical(self::getData(self::PREFIX) . TextFormat::RED . " Invalid syntax. Currupted config.yml!");
			APIProvider::getInstance()->getServer()->getPluginManager()->disablePlugin(APIProvider::getInstance());
			return;
		}
		if ($config->getNested("zuri.version") === $pluginConfig["zuri"]["version"]) {
			return;
		}
		@rename(APIProvider::getInstance()->getDataFolder() . "config.yml", APIProvider::getInstance()->getDataFolder() . "old-config.yml");
		APIProvider::getInstance()->saveResource("config.yml");
		$log->notice(self::getData(self::PREFIX) . TextFormat::RED . "Outdated configuration! Your config will be renamed as old-config.yml to backup your data.");
	}
}