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

namespace ReinfyTeam\Zuri\utils;

use pocketmine\Server;
use ReinfyTeam\Zuri\lang\Lang;
use function count;
use function max;
use function microtime;
use function round;

final class HotPathProfiler {
	/** @var array<string,array{count:int,total:float,max:float}> */
	private static array $metrics = [];
	private static float $windowStartedAt = 0.0;
	private static float $flushIntervalSeconds = 30.0;
	private static bool $enabled = true;

	public static function setEnabled(bool $enabled) : void {
		self::$enabled = $enabled;
	}

	public static function record(string $metric, float $seconds) : void {
		if (!self::$enabled) {
			return;
		}

		if (self::$windowStartedAt === 0.0) {
			self::$windowStartedAt = microtime(true);
		}

		$entry = self::$metrics[$metric] ?? ["count" => 0, "total" => 0.0, "max" => 0.0];
		$entry["count"]++;
		$entry["total"] += $seconds;
		$entry["max"] = max($entry["max"], $seconds);
		self::$metrics[$metric] = $entry;

		if ((microtime(true) - self::$windowStartedAt) >= self::$flushIntervalSeconds) {
			self::flush();
		}
	}

	public static function flush() : void {
		if (self::$metrics === []) {
			self::$windowStartedAt = microtime(true);
			return;
		}

		$logger = Server::getInstance()->getLogger();
		$windowSeconds = self::$windowStartedAt > 0.0 ? max(0.001, microtime(true) - self::$windowStartedAt) : 0.001;
		$totalCalls = 0;
		foreach (self::$metrics as $entry) {
			$totalCalls += $entry["count"];
		}
		$logger->debug(Lang::get("messages.debug.system.hotpath-window", [
			"calls" => (string) $totalCalls,
			"duration" => (string) round($windowSeconds, 2),
		], "{prefix} Hot-path profile window: calls={calls}, duration={duration}s"));

		foreach (self::$metrics as $metric => $entry) {
			$avg = $entry["total"] / max(1, $entry["count"]);
			$logger->debug(Lang::get("messages.debug.system.hotpath-entry", [
				"metric" => $metric,
				"count" => (string) $entry["count"],
				"avg" => (string) round($avg * 1000.0, 3),
				"max" => (string) round($entry["max"] * 1000.0, 3),
			], "{prefix} Profile {metric}: count={count}, avg={avg}ms, max={max}ms"));
		}

		self::$metrics = [];
		self::$windowStartedAt = microtime(true);
	}

	/** @return array<string,array{count:int,total:float,max:float}> */
	public static function getMetrics() : array {
		return self::$metrics;
	}

	public static function getAverageMillis(string $metric) : float {
		$entry = self::$metrics[$metric] ?? null;
		if ($entry === null || $entry["count"] === 0) {
			return 0.0;
		}
		return ($entry["total"] / $entry["count"]) * 1000.0;
	}

	public static function getTotalMillis() : float {
		if (self::$metrics === []) {
			return 0.0;
		}
		$total = 0.0;
		foreach (self::$metrics as $entry) {
			$total += $entry["total"];
		}
		return $total * 1000.0;
	}

	public static function getMetricCount() : int {
		return count(self::$metrics);
	}
}
