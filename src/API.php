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
use function array_filter;
use function array_values;

final class API {
	private static ConfigManager $config;

	public static function getVersion() : string {
		return self::getPluginInstance()->getDescription()->getVersion();
	}

	public static function getPlayer(string|Player $player) : ?PlayerAPI {
		if ($player instanceof Player) {
			return PlayerAPI::getAPIPlayer($player);
		}
		$found = Server::getInstance()->getPlayerExact($player);
		return PlayerAPI::getAPIPlayer($found);
	}

	public static function getModule(string $name, string $subType) : ?Check {
		foreach (ZuriAC::Checks() as $module) {
			if ($module->getName() === $name && $module->getSubType() === $subType) {
				return $module;
			}
		}
		return null;
	}

	public static function getAllChecks(bool $includeSubChecks = true) : array {
		if (!$includeSubChecks) {
			$unique = [];
			foreach (ZuriAC::Checks() as $module) {
				$unique[$module->getName()] ??= $module;
			}
			return array_values($unique);
		}
		return ZuriAC::Checks();
	}

	public static function getAllDisabledChecks(bool $includeSubChecks = true) : array {
		return array_filter(
			self::getAllChecks($includeSubChecks),
			static fn(Check $module) => !$module->enable()
		);
	}

	public static function getAllEnabledChecks(bool $includeSubChecks = true) : array {
		return array_filter(
			self::getAllChecks($includeSubChecks),
			static fn(Check $module) => $module->enable()
		);
	}

	public static function getAllModules() : array {
		return ZuriAC::Checks();
	}

	public static function getConfig() : ConfigManager {
		return self::$config ??= new ConfigManager();
	}

	public static function allModuleInfo(string $name, string $subType) : ?array {
		$module = self::getModule($name, $subType);
		if ($module === null) {
			return null;
		}

		return [
			"name" => $module->getName(),
			"subType" => $module->getSubType(),
			"punishment" => $module->getPunishment(),
			"maxViolations" => $module->maxViolations()
		];
	}


	private static function getModuleInfoField(string $name, string $field) : mixed {
		$info = self::allModulesInfo();
		return $info[$name][$field] ?? null;
	}

	public static function getModule(string $name, string $subType) : ?Check {
		$matches = array_values(array_filter(
			ZuriAC::Checks(),
			static fn(Check $module) : bool =>
				$module->getName() === $name && $module->getSubType() === $subType
		));
		return $matches[0] ?? null;
	}


	public static function getSubTypeByModule(string $name) : ?string {
		return self::getModuleInfoField($name, "subType");
	}

	public static function getMaxViolationByModule(string $name) : ?int {
		return self::getModuleInfoField($name, "maxViolations");
	}

	public static function getPunishmentByModule(string $name) : ?array {
		return self::getModuleInfoField($name, "punishment");
	}

	public static function getPluginInstance() : ZuriAC {
		return ZuriAC::getInstance();
	}

	public static function getDiscordWebhookConfig() : Config {
		return Discord::getWebhookConfig();
	}
}
