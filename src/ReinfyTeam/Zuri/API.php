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
use ReinfyTeam\Zuri\events\api\CheckStateChangeEvent;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\Discord;
use function array_filter;
use function array_values;

/**
 * Provides the public API for interacting with Zuri.
 */
final class API {
	private static ConfigManager $config;

	/**
	 * Gets the installed Zuri plugin version.
	 */
	public static function getVersion() : string {
		return self::getPluginInstance()->getDescription()->getVersion();
	}

	/**
	 * Resolves a player name or instance to a PlayerAPI wrapper.
	 */
	public static function getPlayer(string|Player $player) : ?PlayerAPI {
		if ($player instanceof Player) {
			return PlayerAPI::getAPIPlayer($player);
		}
		$found = Server::getInstance()->getPlayerExact($player);
		if ($found === null) {
			return null;
		}
		return PlayerAPI::getAPIPlayer($found);
	}

	/**
	 * Gets all checks with optional subtype deduplication.
	 *
	 * @return list<Check>
	 */
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

	/**
	 * Gets all disabled checks.
	 *
	 * @return list<Check>
	 */
	public static function getAllDisabledChecks(bool $includeSubChecks = true) : array {
		return array_filter(
			self::getAllChecks($includeSubChecks),
			static fn(Check $module) => !$module->enable()
		);
	}

	/**
	 * Gets all enabled checks.
	 *
	 * @return list<Check>
	 */
	public static function getAllEnabledChecks(bool $includeSubChecks = true) : array {
		return array_filter(
			self::getAllChecks($includeSubChecks),
			static fn(Check $module) => $module->enable()
		);
	}

	/**
	 * Gets all check module instances.
	 *
	 * @return list<Check>
	 */
	public static function getAllModules() : array {
		return ZuriAC::Checks();
	}

	/**
	 * Gets a check by name and optional subtype.
	 */
	public static function getCheck(string $name, ?string $subType = null) : ?Check {
		if ($subType !== null) {
			return self::getModule($name, $subType);
		}

		$checks = self::getChecksByName($name);
		return $checks[0] ?? null;
	}

	/**
	 * Gets all checks matching a name.
	 *
	 * @return list<Check>
	 */
	public static function getChecksByName(string $name) : array {
		return array_values(array_filter(
			ZuriAC::Checks(),
			static fn(Check $module) : bool => $module->getName() === $name
		));
	}

	/**
	 * Enables or disables a check after dispatching a state change event.
	 */
	public static function setCheckEnabled(string $name, bool $enabled, ?string $subType = null) : bool {
		$plugin = self::getPluginInstance();
		$event = new CheckStateChangeEvent($name, $subType, $enabled);
		$event->call();
		if ($event->isCancelled()) {
			return false;
		}

		return $plugin->setCheckEnabled($event->getCheckName(), $event->getSubType(), $event->isEnabled());
	}

	/**
	 * Reloads the check registry from configuration.
	 */
	public static function reloadChecks() : void {
		self::getPluginInstance()->reloadChecks();
	}

	/**
	 * Rebuilds internal check lookup buckets.
	 */
	public static function rebuildCheckBuckets() : void {
		self::getPluginInstance()->rebuildCheckBuckets();
	}

	/**
	 * Gets the shared API configuration manager instance.
	 */
	public static function getConfig() : ConfigManager {
		return self::$config ??= new ConfigManager();
	}

	/**
	 * Gets a normalized info payload for a specific module.
	 *
	 * @return array{name:string, subType:string, punishment:string, maxViolations:int}|null
	 */
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

	/**
	 * Gets a check by module name and subtype.
	 */
	public static function getModule(string $name, string $subType) : ?Check {
		$matches = array_values(array_filter(
			ZuriAC::Checks(),
			static fn(Check $module) : bool =>
				$module->getName() === $name && $module->getSubType() === $subType
		));
		return $matches[0] ?? null;
	}

	/**
	 * Gets the subtype of the first check matching the module name.
	 */
	public static function getSubTypeByModule(string $name) : ?string {
		return self::getCheck($name)?->getSubType();
	}

	/**
	 * Gets the max violation threshold of the first matching module.
	 */
	public static function getMaxViolationByModule(string $name) : ?int {
		return self::getCheck($name)?->maxViolations();
	}

	/**
	 * Gets the punishment mode of the first matching module.
	 */
	public static function getPunishmentByModule(string $name) : ?string {
		return self::getCheck($name)?->getPunishment();
	}

	/**
	 * Sets the flagged state for a player.
	 */
	public static function setPlayerFlagged(string|Player $player, bool $flagged = true) : bool {
		$apiPlayer = self::getPlayer($player);
		if ($apiPlayer === null) {
			return false;
		}

		$apiPlayer->setFlagged($flagged);
		return true;
	}

	/**
	 * Sets debug mode for a player.
	 */
	public static function setPlayerDebug(string|Player $player, bool $debug = true) : bool {
		$apiPlayer = self::getPlayer($player);
		if ($apiPlayer === null) {
			return false;
		}

		$apiPlayer->setDebug($debug);
		return true;
	}

	/**
	 * Sets captcha mode for a player.
	 */
	public static function setPlayerCaptcha(string|Player $player, bool $captcha = true) : bool {
		$apiPlayer = self::getPlayer($player);
		if ($apiPlayer === null) {
			return false;
		}

		$apiPlayer->setCaptcha($captcha);
		return true;
	}

	/**
	 * Gets the singleton plugin instance.
	 */
	public static function getPluginInstance() : ZuriAC {
		return ZuriAC::getInstance();
	}

	/**
	 * Gets the Discord webhook configuration file.
	 */
	public static function getDiscordWebhookConfig() : Config {
		return Discord::getWebhookConfig();
	}
}
