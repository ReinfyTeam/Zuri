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

use pocketmine\utils\TextFormat;
use ReinfyTeam\Zuri\ZuriAC;
use function fclose;
use function file_exists;
use function rename;
use function stream_get_contents;
use function yaml_parse;

class ConfigManager extends ConfigPaths {
	public static function getData(string $path) {
		return ZuriAC::getInstance()->getConfig()->getNested($path);
	}

	public static function setData(string $path, $data, bool $reverseColors = false) {
		ZuriAC::getInstance()->getConfig()->setNested($path, $data);
		ZuriAC::getInstance()->getConfig()->save();
	}

	public static function checkConfig() : void {
		if (!file_exists(ZuriAC::getInstance()->getDataFolder() . "config.yml")) {
			ZuriAC::getInstance()->saveResource("config.yml");
		}

		if (!file_exists(ZuriAC::getInstance()->getDataFolder() . "webhook.yml")) {
			ZuriAC::getInstance()->saveResource("webhook.yml");
		}

		$pluginConfigResource = ZuriAC::getInstance()->getResource("config.yml");
		$pluginConfig = yaml_parse(stream_get_contents($pluginConfigResource));
		fclose($pluginConfigResource);
		$config = ZuriAC::getInstance()->getConfig();
		$log = ZuriAC::getInstance()->getServer()->getLogger();
		if ($pluginConfig == false) {
			$log->critical(self::getData(self::PREFIX) . TextFormat::RED . " Invalid syntax. Currupted config.yml!");
			ZuriAC::getInstance()->getServer()->getPluginManager()->disablePlugin(ZuriAC::getInstance());
			return;
		}
		if ($config->getNested("zuri.version") === $pluginConfig["zuri"]["version"]) {
			return;
		}
		@rename(ZuriAC::getInstance()->getDataFolder() . "config.yml", ZuriAC::getInstance()->getDataFolder() . "old-config.yml");
		ZuriAC::getInstance()->saveResource("config.yml");
		$log->notice(self::getData(self::PREFIX) . TextFormat::RED . " Outdated configuration! Your config will be renamed as old-config.yml to backup your data.");
	}
}