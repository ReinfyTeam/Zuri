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

namespace ReinfyTeam\Zuri\network;

use Exception;
use pocketmine\utils\TextFormat;
use ReinfyTeam\Zuri\config\ConfigManager;
use ReinfyTeam\Zuri\config\ConfigPaths;
use ReinfyTeam\Zuri\utils\InternetAddress;
use ReinfyTeam\Zuri\ZuriAC;
use function socket_bind;
use function socket_close;
use function socket_create;
use function socket_last_error;
use function socket_recvfrom;
use function socket_sendto;
use function socket_set_nonblock;
use function socket_set_option;
use function socket_strerror;
use function strlen;

class ProxyUDPSocket {
	protected $socket = null;
	protected ?InternetAddress $bindAddress = null;
	private bool $ready = false;
	private bool $closed = false;

	public function __construct() {
		$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
		if ($socket === false) {
			$error = socket_last_error();
			ZuriAC::getInstance()->getServer()->getLogger()->error(ConfigManager::getData(ConfigPaths::PREFIX) . TextFormat::RED . " Failed to create ProxyUDP socket: " . socket_strerror($error));
			return;
		}

		$this->socket = $socket;
		$this->ready = true;
		socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
		socket_set_option($this->socket, SOL_SOCKET, SO_SNDBUF, 1024 * 1024 * 8);
		socket_set_option($this->socket, SOL_SOCKET, SO_RCVBUF, 1024 * 1024 * 8);
		socket_set_nonblock($this->socket);
	}

	/**
	 * @throws Exception
	 */
	public function bind(InternetAddress $address) : void {
		if (!$this->ready || $this->socket === null) {
			throw new Exception("Could not initialize ProxyUDP socket");
		}

		$this->bindAddress = $address;

		ZuriAC::getInstance()->getServer()->getLogger()->warning(ConfigManager::getData(ConfigPaths::PREFIX) . TextFormat::YELLOW . " --------------------------------------------------------------");
		ZuriAC::getInstance()->getServer()->getLogger()->warning(ConfigManager::getData(ConfigPaths::PREFIX) . TextFormat::YELLOW . " YOU ARE RUNNING THIS PLUGIN WITH PROXY UDP SUPPORT!");
		ZuriAC::getInstance()->getServer()->getLogger()->warning(ConfigManager::getData(ConfigPaths::PREFIX) . TextFormat::YELLOW . " ProxyUDP is in testing stage and may still contain bugs.");
		ZuriAC::getInstance()->getServer()->getLogger()->warning(ConfigManager::getData(ConfigPaths::PREFIX) . TextFormat::YELLOW . " If you hit issues, please report them on GitHub.");
		ZuriAC::getInstance()->getServer()->getLogger()->warning(ConfigManager::getData(ConfigPaths::PREFIX) . TextFormat::YELLOW . " This feature can impact performance depending on your network load.");
		ZuriAC::getInstance()->getServer()->getLogger()->warning(ConfigManager::getData(ConfigPaths::PREFIX) . TextFormat::YELLOW . " USE IT AT YOUR OWN RISKS! IM NOT RESPONSIBLE FOR ANY DAMAGE COST.");
		ZuriAC::getInstance()->getServer()->getLogger()->warning(ConfigManager::getData(ConfigPaths::PREFIX) . TextFormat::YELLOW . " To disable this feature, set 'zuri.proxy.enable' to false in the config.");
		ZuriAC::getInstance()->getServer()->getLogger()->warning(ConfigManager::getData(ConfigPaths::PREFIX) . TextFormat::YELLOW . " --------------------------------------------------------------");

		if (socket_bind($this->socket, $address->ip, $address->port)) {
			ZuriAC::getInstance()->getServer()->getLogger()->info(ConfigManager::getData(ConfigPaths::PREFIX) . TextFormat::GREEN . " Successfully bound to $address->ip:$address->port!");
			ZuriAC::getInstance()->getServer()->getLogger()->info(ConfigManager::getData(ConfigPaths::PREFIX) . TextFormat::GREEN . " Proxy UDP socket is ready to receive packets.");
		} else {
			$error = socket_last_error($this->socket);
			ZuriAC::getInstance()->getServer()->getLogger()->info(ConfigManager::getData(ConfigPaths::PREFIX) . TextFormat::RED . " Could not bind to $address->ip:$address->port: " . socket_strerror($error));
			throw new Exception("Could not bind to $address->ip:$address->port");
		}
	}

	public function receive(?string &$buffer, ?string &$ip, ?int &$port) : void {
		if (!$this->ready || $this->socket === null || $this->closed) {
			$buffer = null;
			$ip = null;
			$port = null;
			return;
		}

		$result = @socket_recvfrom($this->socket, $buffer, 65535, 0, $ip, $port);
		if ($result === false) {
			$buffer = null;
			$ip = null;
			$port = null;
		}
	}

	public function send(string $buffer, string $ip, int $port) : void {
		if (!$this->ready || $this->socket === null || $this->closed || $buffer === "") {
			return;
		}

		@socket_sendto($this->socket, $buffer, strlen($buffer), 0, $ip, $port);
	}

	public function close() : void {
		if ($this->socket !== null && !$this->closed) {
			socket_close($this->socket);
			$this->closed = true;
			$this->ready = false;
		}
	}
}
