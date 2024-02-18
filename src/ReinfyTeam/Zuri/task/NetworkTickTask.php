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

namespace ReinfyTeam\Zuri\task;

use pocketmine\scheduler\Task;
use pocketmine\Server;
use ReinfyTeam\Zuri\APIProvider;
use ReinfyTeam\Zuri\config\ConfigManager;

class NetworkTickTask extends Task {
	private array $network = [];
	private array $count = [];
	private static $instance = null;
	protected APIProvider $plugin;

	public function __construct(APIProvider $plugin) {
		$this->plugin = $plugin;
	}

	public function onRun() : void {
		self::$instance = $this;
		foreach (Server::getInstance()->getOnlinePlayers() as $player) {
			$ipPlayer = $player->getNetworkSession()->getIp();
			if (isset($this->network[$player->getUniqueId()->__toString()]["ip"])) {
				if ($this->network[$player->getUniqueId()->__toString()]["ip"] !== $ipPlayer) {
					foreach ($this->network as $xuid => $data) {
						if ($data["ip"] === $ipPlayer) {
							if (!isset($this->count[$player->getUniqueId()->__toString()])) {
								$this->count[] = $player->getUniqueId()->__toString();
								$this->count[$player->getUniqueId()->__toString()] = 0;
							}
							$this->count[$player->getUniqueId()->__toString()] += 1;
						}
					}
					if ($this->count[$player->getUniqueId()->__toString()] > ConfigManager::getData(ConfigManager::NETWORK_LIMIT)) { // this will let decide in how many count will able to connect to the server.
						$player->kick(ConfigManager::getData(ConfigManager::NETWORK_MESSAGE), null, ConfigManager::getData(ConfigManager::NETWORK_MESSAGE));
					}
				}
			} else {
				$this->network[$player->getUniqueId()->__toString()] = ["ip" => $ipPlayer, "player" => $player];
			}
		}
		foreach ($this->network as $xuid => $data) {
			$player2 = $data["player"];
			if (!$player2->isOnline()) {
				unset($this->network[$xuid]);
			}
		}
	}

	public static function getInstance() : self {
		return self::$instance;
	}
}
