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

use pocketmine\utils\SingletonTrait;
use ReinfyTeam\Zuri\check\CheckRegistry;
use ReinfyTeam\Zuri\check\CheckWorker;
use ReinfyTeam\Zuri\config\ConfigManager;
use ReinfyTeam\Zuri\config\ConstantValues;
use ReinfyTeam\Zuri\config\language\LanguageManager;

/**
 * Main plugin class for ZuriAC.
 *
 * Provides lifecycle hooks and global accessors for subsystems.
 */
class ZuriAC extends Loader {
	use SingletonTrait;

	private static CheckWorker $worker;
	private static CheckRegistry $checkRegistry;
	private static ConfigManager $config;
	private static ConstantValues $constants;
	private static LanguageManager $languageManager;
	private static MetricsData $metricsData;

	/**
	 * Called when the plugin is loaded.
	 */
	protected function onLoad() : void {
		self::$instance = $this;

		self::checkPHP();
		self::checkRunningSource();

		self::$config = new ConfigManager(ZuriAC::getInstance()->getDataFolder() . "config.yml");
		self::$constants = new ConstantValues(ZuriAC::getInstance()->getDataFolder() . "constants.yml");
	}

	/**
	 * Called when the plugin is enabled.
	 * Initializes worker and check registry.
	 */
	protected function onEnable() : void {
		self::$worker = CheckWorker::spawnWorker($this);
		self::$checkRegistry = CheckRegistry::loadChecks();
		self::$metricsData = new MetricsData();
		self::registerEvents();
	}

	/**
	 * Returns the configured CheckWorker instance.
	 */
	public static function getWorker() : CheckWorker {
		return self::$worker;
	}

	/**
	 * Returns the CheckRegistry instance.
	 */
	public static function getCheckRegistry() : CheckRegistry {
		return self::$checkRegistry;
	}

	/**
	 * Returns the ConfigManager instance.
	 */
	public static function getConfigManager() : ConfigManager {
		return self::$config;
	}

	/**
	 * Returns the ConstantValues instance.
	 */
	public static function getConstants() : ConstantValues {
		return self::$constants;
	}

	/**
	 * Returns the LanguageManager instance.
	 */
	public static function getLanguageManager() : LanguageManager {
		return self::$languageManager;
	}

	/**
	 * Returns the MetricsData instance.
	 */
	public static function getMetricsData() : MetricsData {
		return self::$metricsData;
	}
}