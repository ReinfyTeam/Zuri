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

use pocketmine\player\Player;
use ReinfyTeam\Zuri\check\moving\speed\SpeedA;
use ReinfyTeam\Zuri\ZuriAC;
use function array_filter;


/**
 * Manages registration and retrieval of anti-cheat checks.
 */
class CheckRegistry {
	/** @var Check[] List of registered checks. */
	private array $checks = [];


	/**
	 * Initializes the registry with a list of checks.
	 *
	 * @param Check[] $checks Array of check instances.
	 */
	public function __construct(array $checks) {
		$this->checks = $checks;
	}


	/**
	 * Registers a new check in the registry.
	 *
	 * @param Check $check The check instance to register.
	 */
	public function registerCheck(Check $check) : void {
		$this->checks[] = $check;
	}


	/**
	 * Returns all registered checks.
	 *
	 * @return Check[]
	 */
	public function getChecks() : array {
		return $this->checks;
	}


	/**
	 * Queues all checks of a given type for asynchronous processing.
	 *
	 * @param array $data Data to pass to each check.
	 * @param int $type The type of check (see Check::TYPE_* constants).
	 */
	public function spawnCheck(array $data, int $type) : void {
		foreach ($this->getChecksByType($type) as $check) {
			ZuriAC::getWorker()->queue($data, $check);
		}
	}


	/**
	 * Retrieves all checks matching a specific type.
	 *
	 * Needed for filtering checks when running them asynchronously, as we don't want to run player checks on packet data, for example.
	 *
	 * @param int $type The type of check (see Check::TYPE_* constants).
	 * @return Check[]
	 * @see Check::TYPE_PACKET
	 * @see Check::TYPE_PLAYER
	 * @see Check::TYPE_EVENT
	 */
	public function getChecksByType(int $type) : array {
		return array_filter($this->checks, function(Check $check) use ($type) {
			return $check->getType() === $type;
		});
	}

	/**
	 * Loads the default set of checks into the registry.
	 */
	public static function loadChecks() : self {
		return new self([
			new SpeedA()
		]);
	}
}