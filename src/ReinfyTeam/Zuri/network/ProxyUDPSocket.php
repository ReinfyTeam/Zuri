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

use ReinfyTeam\Zuri\APIProvider;
use ReinfyTeam\Zuri\utils\InternetAddress;
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

	public function bind(InternetAddress $address) {
		if (socket_bind($this->socket, $address->ip, $address->port)) {
			APIProvider::getInstance()->getLogger()->info("Successfully bound to {$address->ip}:{$address->port}");
			$result = socket_connect($this->socket, $address->ip, $address->port);
			if ($result) {
				APIProvider::getInstance()->getLogger()->info("Successfully connected to {$address->ip}:{$address->port}");
			}
		} else {
			throw new \Exception("Could not bound to {$address->ip}:{$address->port}");
		}
	}

	public function receive(?string &$buffer, ?string &$ip, ?int &$port) {
		socket_recvfrom($this->socket, $buffer, 65535, 0, $ip, $port);
	}

	public function send(string $buffer, string $ip, int $port) {
		socket_sendto($this->socket, $buffer, strlen($buffer), 0, $ip, $port);
	}

	public function close() {
		socket_close($this->socket);
	}
}