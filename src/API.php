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

namespace ReinfyTeam\Zuri;

use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\Config;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\config\ConfigManager;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\Discord;
use function in_array;

/**
 * API Documentation can be found in github wiki.
 * This should be only use for plugins.
 * @link https://github.com/ReinfyTeam/Zuri/wiki
 */
final class API {
	private static ConfigManager $config;

	public static function getVersion() : string {
		return $this->getPluginInstance()->getDescription()->getVersion();
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
		foreach (ZuriAC::Checks() as $module) {
			$result[$module->getName()] = ["name" => $module->getName(), "subType" => $module->getSubType(), "punishment" => $module->getPunishment(), "maxViolations" => $module->maxViolations()];
		}

		return $result;
	}

	public static function getModuleInfo(string $name, string $subType) : ?string {
		if (in_array($name, ZuriAC::Checks(), true)) {
			return null;
		}

		return self::allModulesInfo()[$name];
	}

	public static function getSubTypesByModule(string $name) : ?string {
		if (in_array($name, ZuriAC::Checks(), true)) {
			return null;
		}

		return self::allModulesInfo()[$name]["subType"];
	}

	public static function getMaxViolationByModule(string $name) : ?string {
		if (in_array($name, ZuriAC::Checks(), true)) {
			return null;
		}

		return self::allModulesInfo()[$name]["maxViolations"];
	}

	public function getPunishmentByModule(string $name) : ?array {
		if (in_array($name, ZuriAC::Checks(), true)) {
			return null;
		}

		return self::allModulesInfo()[$name]["punishment"];
	}

	public static function getPluginInstance() : ?ZuriAC {
		return ZuriAC::getInstance();
	}

	public static function getDiscordWebhookConfig() : Config {
		return Discord::getWebhookConfig();
	}
}
