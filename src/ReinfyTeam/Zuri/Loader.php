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

use Phar;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use function shutdown;
use function version_compare;


/**
 * Base loader for the plugin, providing common lifecycle helpers.
 */
class Loader extends PluginBase {
	/**
	 * Minimum supported PHP version.
	 */
	private const MINIMUM_PHP_VERSION = "8.2.0";

	/**
	 * Registers event listeners required by the plugin.
	 */
	protected static function registerEvents() : void {
		Server::getInstance()->getPluginManager()->registerEvents(new EventListener(), ZuriAC::getInstance());
	}

	/**
	 * Checks whether the plugin is running from a Phar archive.
	 * Logs a warning if not.
	 */
	protected static function checkRunningSource() : void {
		if (!Phar::running()) {
			Server::getInstance()->getLogger()->warning("This plugin must be run as a phar file.");
		}
	}

	/**
	 * Validates the running PHP version against the minimum requirement.
	 */
	protected static function checkPHP() : void {
		if (version_compare(PHP_VERSION, self::MINIMUM_PHP_VERSION, '<')) {
			Server::getInstance()->getLogger()->error("Error PHP version is " . PHP_VERSION . " but " . self::MINIMUM_PHP_VERSION . " is required.");
			Server::getInstance() - shutdown();
		}
	}
}