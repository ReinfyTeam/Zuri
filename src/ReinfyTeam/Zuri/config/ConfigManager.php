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

namespace ReinfyTeam\Zuri\config;

use JsonException;
use pocketmine\utils\TextFormat;
use ReinfyTeam\Zuri\ZuriAC;
use function fclose;
use function file_exists;
use function is_array;
use function is_resource;
use function is_string;
use function rename;
use function stream_get_contents;
use function yaml_parse;

class ConfigManager extends ConfigPaths {
	public static function getData(string $path, mixed $defaultValue = null) : mixed {
		return ZuriAC::getInstance()->getConfig()->getNested($path, $defaultValue);
	}

	/**
	 * @throws JsonException
	 */
	public static function setData(string $path, mixed $data) : void {
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

		if (!file_exists(ZuriAC::getInstance()->getDataFolder() . "lang/en_US.yml")) {
			ZuriAC::getInstance()->saveResource("lang/en_US.yml");
		}

		$pluginConfigResource = ZuriAC::getInstance()->getResource("config.yml");
		if (!is_resource($pluginConfigResource)) {
			$log = ZuriAC::getInstance()->getServer()->getLogger();
			$log->critical(self::getData(self::PREFIX) . TextFormat::RED . " Failed to load embedded config.yml resource.");
			ZuriAC::getInstance()->getServer()->getPluginManager()->disablePlugin(ZuriAC::getInstance());
			return;
		}

		$pluginConfigRaw = stream_get_contents($pluginConfigResource);
		fclose($pluginConfigResource);
		$pluginConfigParsed = $pluginConfigRaw === false ? false : yaml_parse($pluginConfigRaw);
		$config = ZuriAC::getInstance()->getConfig();
		$log = ZuriAC::getInstance()->getServer()->getLogger();
		if (!is_array($pluginConfigParsed)) {
			$log->critical(self::getData(self::PREFIX) . TextFormat::RED . " Invalid syntax. Currupted config.yml!");
			ZuriAC::getInstance()->getServer()->getPluginManager()->disablePlugin(ZuriAC::getInstance());
			return;
		}
		$pluginVersion = $pluginConfigParsed["zuri"]["version"] ?? null;
		if (is_string($pluginVersion) && $config->getNested("zuri.version") === $pluginVersion) {
			return;
		}
		@rename(ZuriAC::getInstance()->getDataFolder() . "config.yml", ZuriAC::getInstance()->getDataFolder() . "old-config.yml");
		ZuriAC::getInstance()->saveResource("config.yml");
		$log->notice(self::getData(self::PREFIX) . TextFormat::RED . " Outdated configuration! Your config will be renamed as old-config.yml to backup your data.");
	}
}