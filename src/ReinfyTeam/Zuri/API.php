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

namespace ReinfyTeam\Zuri;

use pocketmine\player\Player;
use pocketmine\Server;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\config\ConfigManager;
use ReinfyTeam\Zuri\player\PlayerAPI;
use function in_array;

final class API {
	private static ConfigManager $config;

	public static function getVersion() : string {
		return APIProvider::VERSION_PLUGIN;
	}

	public static function getPlayer(string|Player $player) : ?PlayerAPI {
		if ($player instanceof Player) {
			return PlayerAPI::getAPIPlayer($player);
		}

		$player = Server::getInstance()->getPlayerExact($player);
		return PlayerAPI::getAPIPlayer($player);
	}

	public static function getModule(string $name, string $subType) : ?Check {
		if (in_array($name, APIProvider::Checks(), true)) {
			return null;
		}

		foreach (APIProvider::Checks() as $module) {
			if ($module->getName() === $name && $module->getSubType() === $subType) {
				$result = $module;
			}
			continue;
		}

		return $module;
	}

	public static function getAllModules() : array {
		return APIProvider::Checks();
	}

	public static function getConfig() : ConfigManager {
		if (self::$config === null) {
			self::$config = new ConfigManager();
		}

		return self::$config;
	}

	public static function allModulesInfo() : ?array {
		foreach (APIProvider::Checks() as $module) {
			$result[$module->getName()] = ["name" => $module->getName(), "subType" => $module->getSubType(), "kick" => $module->kick(), "ban" => $module->ban(), "flag" => $module->flag(), "captcha" => $module->captcha(), "maxViolations" => $module->maxViolations()];
		}

		return $result;
	}

	public static function getModuleInfo(string $name, string $subType) : ?string {
		if (in_array($name, APIProvider::Checks(), true)) {
			return null;
		}

		return self::allModulesInfo()[$name];
	}

	public static function getSubTypeByModule(string $name) : ?string {
		if (in_array($name, APIProvider::Checks(), true)) {
			return null;
		}

		return self::allModulesInfo()[$name]["subType"];
	}

	public static function getMaxViolationByModule(string $name) : ?string {
		if (in_array($name, APIProvider::Checks(), true)) {
			return null;
		}

		return self::allModulesInfo()[$name]["maxViolations"];
	}

	public function getPunishmentByModule(string $name) : ?array {
		if (in_array($name, APIProvider::Checks(), true)) {
			return null;
		}

		$result["kick"] = self::allModulesInfo()[$name]["kick"];
		$result["ban"] = self::allModulesInfo()[$name]["ban"];
		$result["flag"] = self::allModulesInfo()[$name]["flag"];
		$result["captcha"] = self::allModulesInfo()[$name]["captcha"];

		return $result;
	}

	public static function getPluginInstance() : ?APIProvider {
		return APIProvider::getInstance();
	}
}
