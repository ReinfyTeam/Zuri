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
use ReinfyTeam\Zuri\config\ConfigPath;
use ReinfyTeam\Zuri\ZuriAC;
use function microtime;

/**
 * Tracks time-decaying pre-violations and violations per check and subtype.
 */
class Violation {
	/** @var array<string, array<string, array<int, array{time: float, amount: float}>>> */
	public array $preViolation = [];

	/** @var array<string, array<string, array<int, array{time: float, amount: float}>>> */
	public array $violation = [];

	/**
	 * Returns the current time-decayed pre-violation amount.
	 */
	public function getPreViolations(Check $check) : float {
		return $this->getAmount(
			$this->preViolation[$check->getName()][$check->getSubType()] ?? []
		);
	}

	/**
	 * Adds a weighted pre-violation amount.
	 */
	public function addPreViolation(Check $check, int|float $amount = 1) : void {
		$this->addAmount(
			$this->preViolation,
			$check,
			(float) $amount
		);
	}

	/**
	 * Resets pre-violations for a check.
	 */
	public function resetPreViolation(Check $check) : void {
		unset($this->preViolation[$check->getName()][$check->getSubType()]);
	}

	/**
	 * Returns the current time-decayed violation amount.
	 */
	public function getViolations(Check $check) : float {
		return $this->getAmount(
			$this->violation[$check->getName()][$check->getSubType()] ?? []
		);
	}

	/**
	 * Adds a weighted violation amount.
	 */
	public function addViolation(Check $check, int|float $amount = 1) : void {
		$this->addAmount(
			$this->violation,
			$check,
			(float) $amount
		);
	}

	/**
	 * Resets violations for a check.
	 */
	public function resetViolation(Check $check) : void {
		unset($this->violation[$check->getName()][$check->getSubType()]);
	}

	/**
	 * Calculates the remaining amount after linear time decay.
	 *
	 * Each entry reaches zero after DECAY_TIME seconds.
	 *
	 * @param array<int, array{time: float, amount: float}> $entries
	 */
	private function getAmount(array $entries) : float {
		$now = microtime(true);
		$total = 0.0;
		$decayTime = ZuriAC::getConfigManager()->getFloat(ConfigPath::VIOLATION_DECAY_TIME);

		foreach ($entries as $entry) {
			$elapsed = $now - $entry["time"];

			if ($elapsed >= $decayTime || $elapsed < 0.0) {
				continue;
			}

			$remaining = 1.0 - ($elapsed / $decayTime);
			$total += $entry["amount"] * $remaining;
		}

		return $total;
	}

	/**
	 * Adds one weighted entry and removes expired entries.
	 *
	 * @param array<string, array<string, array<int, array{time: float, amount: float}>>> $storage
	 */
	private function addAmount(array &$storage, Check $check, float $amount) : void {
		if ($amount <= 0.0) {
			return;
		}

		$name = $check->getName();
		$subType = $check->getSubType();
		$now = microtime(true);
		$decayTime = ZuriAC::getConfigManager()->getFloat(ConfigPath::VIOLATION_DECAY_TIME);

		foreach ($storage[$name][$subType] ?? [] as $index => $entry) {
			if ($now - $entry["time"] >= $decayTime) {
				unset($storage[$name][$subType][$index]);
			}
		}

		$storage[$name][$subType][] = [
			"time" => $now,
			"amount" => $amount
		];
	}
}
