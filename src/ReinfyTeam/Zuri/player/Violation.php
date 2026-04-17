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

namespace ReinfyTeam\Zuri\player;

use ReinfyTeam\Zuri\check\Check;
use function abs;
use function microtime;

/**
 * Tracks pre-violations and violations per check and subtype for a player.
 */
class Violation {
	/** @var array<string, array> */
	public array $preViolation = [];
	/** @var array<string, array> */
	public array $violation = [];

	/**
	 * Returns the number of pre-violations recorded for the given check.
	 *
	 * @param Check $check The check instance to query.
	 * @return int Number of pre-violation timestamps stored.
	 */
	public function getPreViolations(Check $check) : int {
		return $this->preViolation[$check->getName()][$check->getSubType()] ??= 0;
	}

	/**
	 * Adds a pre-violation timestamp for the given check.
	 *
	 * The implementation keeps timestamped entries and prunes old ones
	 * based on a time window when adding a new entry.
	 *
	 * @param Check $check The check to add a pre-violation for.
	 * @param int|float $amount Optional amount (unused numeric marker for future use).
	 */
	public function addPreViolation(Check $check, int|float $amount = 1) : void {
		if (isset($this->preViolation[$check->getName()][$check->getSubType()])) {
			foreach ($this->preViolation[$check->getName()][$check->getSubType()] as $index => $time) {
				if (abs($time - microtime(true)) * 20 > 40) {
					unset($this->preViolation[$check->getName()][$check->getSubType()][$index]);
				}
			}
		}

		$this->preViolation[$check->getName()][$check->getSubType()][] = microtime(true);
	}

	/**
	 * Resets pre-violation entries for a given check.
	 */
	public function resetPreViolation(Check $check) : void {
		if (isset($this->preViolation[$check->getName()][$check->getSubType()])) {
			unset($this->preViolation[$check->getName()][$check->getSubType()]);
		}
	}

	/**
	 * Returns the number of violations recorded for the given check.
	 */
	public function getViolations(Check $check) : int {
		return $this->violation[$check->getName()][$check->getSubType()] ??= 0;
	}

	/**
	 * Adds a violation timestamp for the given check.
	 *
	 * Old violation timestamps are pruned during insertion.
	 *
	 * @param int|float $amount Optional numeric marker for future use.
	 */
	public function addViolation(Check $check, int|float $amount = 1) : void {
		if (isset($this->violation[$check->getName()][$check->getSubType()])) {
			foreach ($this->violation[$check->getName()][$check->getSubType()] as $index => $time) {
				if (abs($time - microtime(true)) * 20 > 40) {
					unset($this->violation[$check->getName()][$check->getSubType()][$index]);
				}
			}
		}

		$this->violation[$check->getName()][$check->getSubType()][] = microtime(true);
	}

	/**
	 * Resets violation entries for a given check.
	 */
	public function resetViolation(Check $check) : void {
		if (isset($this->violation[$check->getName()][$check->getSubType()])) {
			unset($this->violation[$check->getName()][$check->getSubType()]);
		}
	}
}