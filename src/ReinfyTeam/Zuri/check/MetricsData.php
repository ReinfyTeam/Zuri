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

namespace ReinfyTeam\Zuri\check;

use pocketmine\Server;
use pocketmine\utils\Process;
use function count;

class MetricsData {
	private float $serverTPS = 0.0;
	private int $playerCount = 0;
	private float $cpuUsage = 0.0;
	private int $threadCount = 0;
	private float $reservedMemoryUsage = 0.0;
	private float $virtualMemoryUsage = 0.0;
	private float $memoryUsage = 0.0;
	private float $virtualMemorySize = 0.0;

	public function setServerTPS(float $tps) : void {
		$this->serverTPS = $tps;
	}

	public function getServerTPS() : float {
		return $this->serverTPS;
	}

	public function getPlayerCount() : int {
		return $this->playerCount;
	}

	public function setPlayerCount(int $playerCount) : void {
		$this->playerCount = $playerCount;
	}

	public function getThreadCount() : int {
		return $this->threadCount;
	}

	public function setThreadCount(int $threadCount) : void {
		$this->threadCount = $threadCount;
	}

	public function getReservedMemoryUsage() : float {
		return $this->reservedMemoryUsage;
	}

	public function setReservedMemoryUsage(float $reservedMemoryUsage) : void {
		$this->reservedMemoryUsage = $reservedMemoryUsage;
	}

	public function getVirtualMemoryUsage() : float {
		return $this->virtualMemoryUsage;
	}

	public function getMemoryUsage() : float {
		return $this->memoryUsage;
	}

	public function setMemoryUsage(float $memoryUsage) : void {
		$this->memoryUsage = $memoryUsage;
	}

	public function setVirtualMemoryUsage(float $virtualMemoryUsage) : void {
		$this->virtualMemoryUsage = $virtualMemoryUsage;
	}

	public function setVirtualMemorySize(float $virtualMemorySize) : void {
		$this->virtualMemorySize = $virtualMemorySize;
	}

	public function getVirtualMemorySize() : float {
		return $this->virtualMemorySize;
	}

	public function getMaxPlayerCount() : int {
		return $this->maxPlayerCount;
	}

	public function setMaxPlayerCount(int $maxPlayerCount) : void {
		$this->maxPlayerCount = $maxPlayerCount;
	}

	public function update() : void {
		$this->setServerTPS(Server::getInstance()->getTicksPerSecond());
		$this->setPlayerCount(count(Server::getInstance()->getOnlinePlayers()));
		$this->setThreadCount(Process::getThreadCount());
		$this->setMemoryUsage(Process::getMemoryUsage());
		$this->setReservedMemoryUsage(Process::getAdvancedMemoryUsage()[0]);
		$this->setVirtualMemoryUsage(Process::getAdvancedMemoryUsage()[1]);
		$this->setVirtualMemorySize(Process::getAdvancedMemoryUsage()[2]);
		$this->setMaxPlayerCount(Server::getInstance()->getMaxPlayers());
	}
}