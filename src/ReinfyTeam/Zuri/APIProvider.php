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
 * @author ReinfyTeam
 * @link https://github.com/ReinfyTeam/
 *
 *
 */

declare(strict_types=1);

namespace ReinfyTeam\Zuri;

use pocketmine\plugin\PluginBase;
use ReinfyTeam\Zuri\command\ZuriCommand;
use ReinfyTeam\Zuri\components\IAPI;
use ReinfyTeam\Zuri\config\ConfigManager;
use ReinfyTeam\Zuri\listener\PlayerListener;
use ReinfyTeam\Zuri\listener\ServerListener;
use ReinfyTeam\Zuri\network\ProxyUDPSocket;
use ReinfyTeam\Zuri\task\CaptchaTask;
use ReinfyTeam\Zuri\task\NetworkTickTask;
use ReinfyTeam\Zuri\task\ServerTickTask;
use ReinfyTeam\Zuri\utils\InternetAddress;

class APIProvider extends PluginBase implements IAPI {
	private static APIProvider $instance;
	private ProxyUDPSocket $proxyUDPSocket;

	public const VERSION_PLUGIN = "1.1.0-BETA";

	public function onLoad() : void {
		self::$instance = $this;
	}

	public static function getInstance() : APIProvider {
		return self::$instance;
	}

	public function onEnable() : void {
		$this->proxyUDPSocket = new ProxyUDPSocket();
		if (ConfigManager::getData(ConfigManager::PROXY_ENABLE)) {
			$ip = ConfigManager::getData(ConfigManager::PROXY_IP);
			$port = ConfigManager::getData(ConfigManager::PROXY_PORT);
			try {
				$this->proxyUDPSocket->bind(new InternetAddress($ip, $port));
			} catch (\Exception $exception) {
				$this->getLogger()->info("{$exception->getMessage()}, stopping proxy...");
				return;
			}
		}
		$this->saveDefaultConfig();
		$this->saveResource("hash.txt");
		$this->getScheduler()->scheduleRepeatingTask(new ServerTickTask($this), 20);
		$this->getScheduler()->scheduleRepeatingTask(new CaptchaTask($this), 20);
		$this->getScheduler()->scheduleRepeatingTask(new NetworkTickTask($this), 100);
		$this->getServer()->getPluginManager()->registerEvents(new PlayerListener(), $this);
		$this->getServer()->getPluginManager()->registerEvents(new ServerListener(), $this);
		$this->getServer()->getCommandMap()->register("Zuri", new ZuriCommand());
	}
}
