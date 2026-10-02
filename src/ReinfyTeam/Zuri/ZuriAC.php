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

use pocketmine\Server;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\SingletonTrait;
use ReinfyTeam\Zuri\check\Check;
use ReinfyTeam\Zuri\check\CheckRegistry;
use ReinfyTeam\Zuri\check\MetricsData;
use ReinfyTeam\Zuri\check\ResultsHandler;
use ReinfyTeam\Zuri\config\ConfigManager;
use ReinfyTeam\Zuri\config\ConfigPath;
use ReinfyTeam\Zuri\config\ConstantValues;
use ReinfyTeam\Zuri\config\language\LanguageManager;
use ReinfyTeam\Zuri\player\ExternalData;
use ReinfyTeam\Zuri\thread\CheckQueue;
use ReinfyTeam\Zuri\thread\CheckResults;
use ReinfyTeam\Zuri\thread\CheckThread;
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
	private static CheckThread $checkThread;
	private static CheckRegistry $checkRegistry;
	private static ConfigManager $config;
	private static ConstantValues $constants;
	private static LanguageManager $languageManager;
	private static MetricsData $metricsData;
	private static ExternalData $externalData;

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
		self::$checkQueue = new CheckQueue();
		self::$checkResults = new CheckResults();
		self::$checkRegistry = CheckRegistry::loadChecks();
		$workerCount = max(1, (int) self::$config->getData(ConfigPath::THREAD_MAX_WORKER, 1));
		$workerCapacity = max(1, (int) self::$config->getData(ConfigPath::THREAD_WORKER_CAPACITY, 64));
		self::$checkThread = new CheckThread(self::$checkQueue, self::$checkResults, $workerCount, $workerCapacity);
		self::$metricsData = new MetricsData();
		self::$externalData = new ExternalData();
		/*$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() : void {
			foreach (Server::getInstance()->getOnlinePlayers() as $player) {
				if (!$player->isConnected()) {
					continue;
				}

				self::$checkRegistry->spawnCheck([
					"type" => "PlayerAuthInputPacket",
					"player" => $player
				], Check::TYPE_PACKET);
			}
		}), 1);*/
		$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() : void {
			while (($result = self::$checkResults->getNextResult()) !== null) {
				ResultsHandler::handle($result);
			}
		}), 1);
		self::registerEvents();
	}

	protected function onDisable() : void {
		self::$checkThread->quit();
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
	 * Returns the CheckThread instance.
	 */
	public static function getCheckThread() : CheckThread {
		return self::$checkThread;
	}
}