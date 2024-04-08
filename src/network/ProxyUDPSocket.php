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

namespace ReinfyTeam\Zuri\network;

use Exception;
use pocketmine\utils\TextFormat;
use ReinfyTeam\Zuri\config\ConfigManager;
use ReinfyTeam\Zuri\utils\InternetAddress;
use ReinfyTeam\Zuri\ZuriAC;
use function socket_bind;
use function socket_close;
use function socket_connect;
use function socket_create;
use function socket_recvfrom;
use function socket_sendto;
use function socket_set_option;
use function strlen;

class ProxyUDPSocket {
	protected $socket;
	protected InternetAddress $bindAddress;

	public function __construct() {
		$this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
		socket_set_option($this->socket, SOL_SOCKET, SO_SNDBUF, 1024 * 1024 * 8);
		socket_set_option($this->socket, SOL_SOCKET, SO_RCVBUF, 1024 * 1024 * 8);
	}

	/**
	 * @throws Exception
	 */
	public function bind(InternetAddress $address) : void {
		ZuriAC::getInstance()->getServer()->getLogger()->warning(ConfigManager::getData(ConfigManager::PREFIX) . TextFormat::YELLOW . " --------------------------------------------------------------");
		ZuriAC::getInstance()->getServer()->getLogger()->warning(ConfigManager::getData(ConfigManager::PREFIX) . TextFormat::YELLOW . " YOU ARE RUNNING THIS PLUGIN WITH PROXY UDP SUPPORT!");
		ZuriAC::getInstance()->getServer()->getLogger()->warning(ConfigManager::getData(ConfigManager::PREFIX) . TextFormat::YELLOW . " ProxyUDP is on development testing stage, which leads many bugs and issue you will encounter.");
		ZuriAC::getInstance()->getServer()->getLogger()->warning(ConfigManager::getData(ConfigManager::PREFIX) . TextFormat::YELLOW . " If you encountered any bugs or issues, dont hesitate to create an issue on github.");
		ZuriAC::getInstance()->getServer()->getLogger()->warning(ConfigManager::getData(ConfigManager::PREFIX) . TextFormat::YELLOW . " This feature has many performance impact. Your performance might be degraded.");
		ZuriAC::getInstance()->getServer()->getLogger()->warning(ConfigManager::getData(ConfigManager::PREFIX) . TextFormat::YELLOW . " USE IT AT YOUR OWN RISKS! IM NOT RESPONSIBLE FOR ANY DAMAGE COST.");
		ZuriAC::getInstance()->getServer()->getLogger()->warning(ConfigManager::getData(ConfigManager::PREFIX) . TextFormat::YELLOW . " To disable this feature, set 'zuri.proxy.enabled' value in the config to false.");
		ZuriAC::getInstance()->getServer()->getLogger()->warning(ConfigManager::getData(ConfigManager::PREFIX) . TextFormat::YELLOW . " --------------------------------------------------------------");

		if (socket_bind($this->socket, $address->ip, $address->port)) {
			ZuriAC::getInstance()->getServer()->getLogger()->info(ConfigManager::getData(ConfigManager::PREFIX) . TextFormat::GREEN . " Successfully bound to {$address->ip}:{$address->port}!");
			$result = socket_connect($this->socket, $address->ip, $address->port);
			if ($result) {
				ZuriAC::getInstance()->getServer()->getLogger()->info(ConfigManager::getData(ConfigManager::PREFIX) . TextFormat::GREEN . " Proxy has been binded successfully!");
			}
		} else {
			ZuriAC::getInstance()->getServer()->getLogger()->info(ConfigManager::getData(ConfigManager::PREFIX) . TextFormat::RED . " We could'nt bind to {$address->ip}:{$address->port}! Is something running in that same ip?");
			throw new Exception("Could not bound to {$address->ip}:{$address->port}");
		}
	}

	public function receive(?string &$buffer, ?string &$ip, ?int &$port) : void {
		socket_recvfrom($this->socket, $buffer, 65535, 0, $ip, $port);
	}

	public function send(string $buffer, string $ip, int $port) : void {
		socket_sendto($this->socket, $buffer, strlen($buffer), 0, $ip, $port);
	}

	public function close() : void {
		socket_close($this->socket);
	}
}
