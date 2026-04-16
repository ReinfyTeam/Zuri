<?php

namespace ReinfyTeam\Zuri;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;
use pocketmine\Server;
use Phar;


class Loader extends PluginBase {

    private const MINIMUM_PHP_VERSION = "8.2.0";

    protected static function registerEvents() : void {
		Server::getInstance()->getPluginManager()->registerEvents(new EventListener(), ZuriAC::getInstance());
	}
	
	protected static function checkRunningSource() : void {
		if (!Phar::running()) {
			Server::getInstance()->getLogger()->warning("This plugin must be run as a phar file.");
		}
	}
	
	protected static function checkPHP() : void {
		if (version_compare(PHP_VERSION, self::MINIMUM_PHP_VERSION, '<')) {
			Server::getInstance()->getLogger()->error("Error PHP version is " . PHP_VERSION . " but " . self::MINIMUM_PHP_VERSION . " is required.");
			Server::getInstance()-shutdown();
		}
	}
}