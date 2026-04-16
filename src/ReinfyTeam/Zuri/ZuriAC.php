<?php

namespace ReinfyTeam\Zuri;

use pocketmine\utils\SingletonTrait;
use ReinfyTeam\Zuri\check\CheckWorker;
use ReinfyTeam\Zuri\check\CheckRegistry;
use ReinfyTeam\Zuri\config\ConfigManager;
use ReinfyTeam\Zuri\config\ConfigPath;

class ZuriAC extends Loader {
	use SingletonTrait;

	private static CheckWorker $worker;
	private static CheckRegistry $checkRegistry;
	private static ConfigManager $config;
	
	protected function onLoad() : void {
		self::$instance = $this;
		
		self::checkPHP();
		self::checkRunningSource();
		
		ConfigManager::configure(ZuriAC::getInstance()->getDataFolder() . "config.yml");
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

	public static function getConfig() : ConfigManager {
		return self::$config;
	}
}