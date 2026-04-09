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
	 * @param array<string, string>|null $cache
	 * @internal For tests only.
	 */
	public static function setGroupCacheForTesting(?array $cache) : void {
		self::$groupCache = $cache;
	}

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
	 * @return string|null One of GROUP_* constants, or null if not in any group
	 */
	public static function classifyGroup(string $checkName) : ?string {
		$cache = self::buildGroupCache();
		return $cache[strtolower($checkName)] ?? null;
	}

	/**
	 * @param array<string, float> $groupHits
	 * @return array<string, float>
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

	public static function normalizeRequiredGroups(int $requiredGroups) : int {
		return max(1, min(3, $requiredGroups));
	}
}
