<?php

namespace ReinfyTeam\Zuri;

use pocketmine\utils\SingletonTrait;
use ReinfyTeam\Zuri\check\CheckWorker;
use ReinfyTeam\Zuri\check\CheckRegistry;
use ReinfyTeam\Zuri\config\ConfigManager;
use ReinfyTeam\Zuri\config\ConstantValues;
use ReinfyTeam\Zuri\config\ConfigPath;

class ZuriAC extends Loader {
	use SingletonTrait;

	private static CheckWorker $worker;
	private static CheckRegistry $checkRegistry;
	private static ConfigManager $config;
	private static ConstantValues $constants;
	
	protected function onLoad() : void {
		self::$instance = $this;
		
		self::checkPHP();
		self::checkRunningSource();
		
		self::$config = new ConfigManager(ZuriAC::getInstance()->getDataFolder() . "config.yml");
		self::$constants = new ConstantValues(ZuriAC::getInstance()->getDataFolder() . "constants.yml");
	}

	protected function onEnable() : void {
		self::$worker = CheckWorker::spawnWorker($this);
		self::$checkRegistry = CheckRegistry::loadChecks();
		self::registerEvents();
	}

	public static function getWorker() : CheckWorker {
		return self::$worker;
	}

	public static function getCheckRegistry() : CheckRegistry {
		return self::$checkRegistry;
	}

	public static function getConfigManager() : ConfigManager {
		return self::$config;
	}

	public static function getConstants() : ConstantValues {
		return self::$constants;
	}
}