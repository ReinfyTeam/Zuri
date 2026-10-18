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
use ReinfyTeam\Zuri\check\MetricsData;
use ReinfyTeam\Zuri\config\ConfigManager;
use ReinfyTeam\Zuri\config\ConfigPath;
use ReinfyTeam\Zuri\config\ConstantValues;
use ReinfyTeam\Zuri\config\language\LanguageManager;
use ReinfyTeam\Zuri\player\ExternalData;
use ReinfyTeam\Zuri\player\PlayerManager;
use ReinfyTeam\Zuri\thread\CheckQueue;
use ReinfyTeam\Zuri\thread\CheckResults;
use ReinfyTeam\Zuri\thread\CheckThreadPool;
use ReinfyTeam\Zuri\utils\PermissionManager;
use function max;

/**
 * Main plugin class for ZuriAC.
 *
 * Provides lifecycle hooks and global accessors for subsystems.
 */
class ZuriAC extends Loader {
	use SingletonTrait;

	private static CheckQueue $checkQueue;
	private static CheckResults $checkResults;
	private static CheckThreadPool $checkThreadPool;
	private static CheckRegistry $checkRegistry;
	private static ConfigManager $config;
	private static ConstantValues $constants;
	private static LanguageManager $languageManager;
	private static MetricsData $metricsData;
	private static ExternalData $externalData;
	private static PermissionManager $permissionManager;
	private static PlayerManager $playerManager;

	/**
	 * Called when the plugin is loaded.
	 */
	protected function onLoad() : void {
		self::$instance = $this;

		self::$config = new ConfigManager(ZuriAC::getInstance()->getDataFolder() . "config.yml");
		self::$constants = new ConstantValues(ZuriAC::getInstance()->getDataFolder() . "constants.yml");
		self::$languageManager = LanguageManager::loadLanguage();

		self::checkPHP();
		self::checkRunningSource();
		self::checkDependencies();
	}

	/**
	 * Called when the plugin is enabled.
	 * Initializes worker and check registry.
	 */
	protected function onEnable() : void {
		$threadCount = max(1, self::$config->getInt(ConfigPath::THREAD_COUNT));
		$workerCount = max(1, self::$config->getInt(ConfigPath::THREAD_MAX_WORKER));
		$workerCapacity = max(1, self::$config->getInt(ConfigPath::THREAD_WORKER_CAPACITY));

		self::$checkQueue = new CheckQueue();
		self::$checkResults = new CheckResults();
		self::$playerManager = new PlayerManager();
		self::$metricsData = new MetricsData();
		self::$permissionManager = new PermissionManager();
		self::$externalData = new ExternalData();

		self::$checkThreadPool = new CheckThreadPool(
			self::$checkQueue,
			self::$checkResults,
			$threadCount,
			$workerCount,
			$workerCapacity
		);

		self::$metricsData->update();
		self::$checkRegistry = CheckRegistry::loadChecks();

		self::registerEvents();
		self::loadCommands();
		self::loadTasks();
	}

	protected function onDisable() : void {
		self::$checkThreadPool->quit();
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

	/**
	 * Returns the ExternalData instance.
	 */
	public static function getExternalData() : ExternalData {
		return self::$externalData;
	}

	public static function getCheckQueue() : CheckQueue {
		return self::$checkQueue;
	}

	/**
	 * Returns the CheckResults instance.
	 */
	public static function getCheckResults() : CheckResults {
		return self::$checkResults;
	}

	/**
	 * Returns the CheckThreadPool managing all threads.
	 */
	public static function getCheckThreadPool() : CheckThreadPool {
		return self::$checkThreadPool;
	}

	/**
	 * Returns the PermissionManager instance.
	 */
	public static function getPermissionManager() : PermissionManager {
		return self::$permissionManager;
	}

	/**
	 * Returns the PlayerManager instance.
	 */
	public static function getPlayerManager() : PlayerManager {
		return self::$playerManager;
	}
}
