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

namespace ReinfyTeam\Zuri\checks;

use function array_keys;
use function array_unique;
use function count;
use function in_array;
use function max;
use function min;
use function strtolower;

/**
 * Provides correlation grouping utilities used for cross-check escalation logic.
 */
final class CrossCheckCorrelation {
	public const GROUP_MOVEMENT = 'movement';
	public const GROUP_COMBAT = 'combat';
	public const GROUP_PACKET_TIMING = 'packet_timing';

	private const VALID_GROUPS = [
		self::GROUP_MOVEMENT,
		self::GROUP_COMBAT,
		self::GROUP_PACKET_TIMING,
	];

	/** @var array<string, string>|null */
	private static ?array $groupCache = null;

	/**
	 * Prevents instantiation of this static utility class.
	 *
	 * @return void
	 */
	private function __construct() {
	}

	/**
	 * Build classification cache by scanning all Check subclasses via ZuriAC registry.
	 * Checks declare their correlation group via getCorrelationGroup() method.
	 * No manual registration required—modules auto-detected.
	 *
	 * @return array<string, string> Map of check name (lowercase) to group constant
	 */
	private static function buildGroupCache() : array {
		if (self::$groupCache !== null) {
			return self::$groupCache;
		}

		$cache = [];
		try {
			// Scan all registered checks via ZuriAC
			$checks = \ReinfyTeam\Zuri\ZuriAC::Checks();
			foreach ($checks as $check) {
				$group = $check->getCorrelationGroup();
				if ($group !== null && self::isValidGroup($group)) {
					$cache[strtolower($check->getName())] = $group;
				}
			}
		} catch (\Throwable) {
			// If ZuriAC is not available yet, don't cache this failed bootstrap attempt.
			return [];
		}

		return self::$groupCache = $cache;
	}

	/**
	 * Overrides the cached check-to-group classification map for tests.
	 *
	 * @param array<string, string>|null $cache
	 * @internal For tests only.
	 */
	public static function setGroupCacheForTesting(?array $cache) : void {
		self::$groupCache = $cache;
	}

	/**
	 * Determines whether a correlation group value is recognized.
	 *
	 * @param string $group Correlation group identifier to validate.
	 * @return bool True when the group is one of the supported GROUP_* constants.
	 */
	private static function isValidGroup(string $group) : bool {
		return in_array($group, self::VALID_GROUPS, true);
	}

	/**
	 * Clear the correlation group cache.
	 * Called on plugin reload or for testing.
	 */
	public static function clearCache() : void {
		self::$groupCache = null;
	}

	/**
	 * Get the correlation group for a check by name.
	 * Automatically discovered from check's getCorrelationGroup() declaration.
	 *
	 * @param string $checkName Check name to classify.
	 * @return string|null One of GROUP_* constants, or null if not in any group
	 */
	public static function classifyGroup(string $checkName) : ?string {
		$cache = self::buildGroupCache();
		return $cache[strtolower($checkName)] ?? null;
	}

	/**
	 * Removes correlation-group hits that are outside the configured time window.
	 *
	 * @param array<string, float> $groupHits
	 * @param float $now Current timestamp in seconds.
	 * @param float $windowSeconds Correlation window length in seconds.
	 * @return array<string, float> Filtered group hit timestamps inside the active window.
	 */
	public static function pruneExpired(array $groupHits, float $now, float $windowSeconds) : array {
		$window = max(0.1, $windowSeconds);
		$cutoff = $now - $window;
		$pruned = [];
		foreach ($groupHits as $group => $timestamp) {
			if ($timestamp >= $cutoff) {
				$pruned[$group] = $timestamp;
			}
		}
		return $pruned;
	}

	/**
	 * Record a check hit and count unique correlated groups active in the window.
	 *
	 * @param array<string, float> $groupHits
	 * @param string $checkName Name of the check that just failed.
	 * @param float $now Current timestamp in seconds.
	 * @param float $windowSeconds Correlation window length in seconds.
	 * @return array{0:int,1:array<string,float>} [count of unique groups, updated hits map]
	 */
	public static function recordAndCount(array $groupHits, string $checkName, float $now, float $windowSeconds) : array {
		$pruned = self::pruneExpired($groupHits, $now, $windowSeconds);
		$group = self::classifyGroup($checkName);
		if ($group !== null) {
			$pruned[$group] = $now;
		}
		return [count(array_unique(array_keys($pruned))), $pruned];
	}

	/**
	 * Clamps the configured required group count to the supported range.
	 *
	 * @param int $requiredGroups Configured required group count.
	 * @return int Normalized required group count between 1 and 3.
	 */
	public static function normalizeRequiredGroups(int $requiredGroups) : int {
		return max(1, min(3, $requiredGroups));
	}
}
