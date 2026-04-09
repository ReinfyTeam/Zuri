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

use pocketmine\Server;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\task\ServerTickTask;
use function count;
use function max;
use function microtime;
use function min;

/**
 * Dynamic threshold adjustment based on server conditions.
 *
 * Adjusts check thresholds in real-time based on:
 * - Player ping (network latency)
 * - Server TPS (ticks per second)
 * - Server load (player count, entity count)
 *
 * This reduces false positives during degraded conditions while
 * maintaining strict detection when conditions are optimal.
 */
class DynamicThreshold {
	/** Cached server metrics for efficiency */
	private static float $lastMetricsUpdate = 0.0;
	private static float $cachedTps = 20.0;
	private static int $cachedPlayerCount = 0;
	private static float $cachedLoadFactor = 1.0;

	/** How often to refresh server metrics (seconds) */
	private const METRICS_CACHE_TTL = 1.0;

	/**
	 * Calculate an adjusted threshold based on current conditions.
	 *
	 * @param float $baseThreshold The base threshold value
	 * @param PlayerAPI $playerAPI The player being checked
	 * @param string $checkType Type of check (movement, combat, etc.) for tuning
	 * @return float Adjusted threshold (always >= base threshold)
	 */
	public static function adjust(float $baseThreshold, PlayerAPI $playerAPI, string $checkType = "default") : float {
		self::refreshMetrics();

		$multiplier = 1.0;

		// Apply ping adjustment
		$ping = $playerAPI->getPlayer()->getNetworkSession()->getPing() ?? 0;
		$multiplier *= self::getPingMultiplier($ping, $checkType);

		// Apply TPS adjustment
		$multiplier *= self::getTpsMultiplier(self::$cachedTps, $checkType);

		// Apply load adjustment
		$multiplier *= self::getLoadMultiplier(self::$cachedLoadFactor, $checkType);

		// Thresholds should only increase (more lenient), never decrease
		return $baseThreshold * max(1.0, $multiplier);
	}

	/**
	 * Get multiplier based on player ping.
	 * Higher ping = higher threshold (more lenient).
	 */
	private static function getPingMultiplier(int $ping, string $checkType) : float {
		// Movement checks are more sensitive to ping
		$sensitivity = match ($checkType) {
			"movement", "speed", "fly" => 1.5,
			"combat", "reach", "killaura" => 1.3,
			"timer", "packet" => 1.2,
			default => 1.0,
		};

		return match (true) {
			$ping < 50 => 1.0,
			$ping < 100 => 1.0 + (0.1 * $sensitivity),
			$ping < 150 => 1.0 + (0.2 * $sensitivity),
			$ping < 200 => 1.0 + (0.35 * $sensitivity),
			$ping < 300 => 1.0 + (0.5 * $sensitivity),
			$ping < 400 => 1.0 + (0.7 * $sensitivity),
			default => 1.0 + (1.0 * $sensitivity),
		};
	}

	/**
	 * Get multiplier based on server TPS.
	 * Lower TPS = higher threshold (more lenient).
	 */
	private static function getTpsMultiplier(float $tps, string $checkType) : float {
		// Timer checks are most sensitive to TPS fluctuation
		$sensitivity = match ($checkType) {
			"timer", "packet" => 2.0,
			"movement", "speed" => 1.5,
			"combat" => 1.2,
			default => 1.0,
		};

		return match (true) {
			$tps >= 19.5 => 1.0,
			$tps >= 18.0 => 1.0 + (0.1 * $sensitivity),
			$tps >= 16.0 => 1.0 + (0.25 * $sensitivity),
			$tps >= 14.0 => 1.0 + (0.5 * $sensitivity),
			$tps >= 10.0 => 1.0 + (0.8 * $sensitivity),
			default => 1.0 + (1.2 * $sensitivity), // Severe lag
		};
	}

	/**
	 * Get multiplier based on server load.
	 * Higher load = slightly higher threshold.
	 */
	private static function getLoadMultiplier(float $loadFactor, string $checkType) : float {
		// Load affects all checks roughly equally
		return match (true) {
			$loadFactor < 0.3 => 1.0,
			$loadFactor < 0.5 => 1.05,
			$loadFactor < 0.7 => 1.1,
			$loadFactor < 0.9 => 1.15,
			default => 1.2,
		};
	}

	/**
	 * Refresh cached server metrics if stale.
	 */
	private static function refreshMetrics() : void {
		$now = microtime(true);
		if (($now - self::$lastMetricsUpdate) < self::METRICS_CACHE_TTL) {
			return;
		}

		self::$lastMetricsUpdate = $now;

		$server = Server::getInstance();
		self::$cachedTps = $server->getTicksPerSecond();
		self::$cachedPlayerCount = count($server->getOnlinePlayers());

		// Calculate load factor (0.0 to 1.0+)
		// Based on player count relative to max and TPS degradation
		$maxPlayers = $server->getMaxPlayers();
		$playerLoad = $maxPlayers > 0 ? self::$cachedPlayerCount / $maxPlayers : 0.0;
		$tpsLoad = max(0.0, (20.0 - self::$cachedTps) / 10.0); // 0 at 20 TPS, 1.0 at 10 TPS

		self::$cachedLoadFactor = min(1.0, ($playerLoad * 0.4) + ($tpsLoad * 0.6));
	}

	/**
	 * Get current server TPS.
	 */
	public static function getTps() : float {
		self::refreshMetrics();
		return self::$cachedTps;
	}

	/**
	 * Get current load factor (0.0 = idle, 1.0 = heavily loaded).
	 */
	public static function getLoadFactor() : float {
		self::refreshMetrics();
		return self::$cachedLoadFactor;
	}

	/**
	 * Check if server is currently under stress.
	 */
	public static function isServerStressed() : bool {
		self::refreshMetrics();
		return self::$cachedTps < 18.0 || self::$cachedLoadFactor > 0.7;
	}

	/**
	 * Check if server is severely lagging.
	 */
	public static function isServerLagging() : bool {
		self::refreshMetrics();
		return self::$cachedTps < 14.0 || (ServerTickTask::getInstance()?->isLagging(microtime(true)) ?? false);
	}

	/**
	 * Get diagnostic info for debugging.
	 */
	/** @return array{tps:float, playerCount:int, loadFactor:float, isStressed:bool, isLagging:bool} */
	public static function getDiagnostics() : array {
		self::refreshMetrics();
		return [
			"tps" => self::$cachedTps,
			"playerCount" => self::$cachedPlayerCount,
			"loadFactor" => self::$cachedLoadFactor,
			"isStressed" => self::isServerStressed(),
			"isLagging" => self::isServerLagging(),
		];
	}

	/**
	 * Calculate player-specific adjustment factor.
	 * Combines ping and recent behavior for per-player tuning.
	 */
	public static function getPlayerFactor(PlayerAPI $playerAPI) : float {
		$ping = $playerAPI->getPlayer()->getNetworkSession()->getPing() ?? 0;
		$onlineTime = $playerAPI->getOnlineTime();

		// New players get more lenient thresholds
		$newPlayerBonus = match (true) {
			$onlineTime < 200 => 1.3,   // First 10 seconds
			$onlineTime < 600 => 1.15,  // First 30 seconds
			$onlineTime < 1200 => 1.05, // First minute
			default => 1.0,
		};

		// High ping players get more lenient thresholds
		$pingFactor = match (true) {
			$ping < 80 => 1.0,
			$ping < 150 => 1.1,
			$ping < 250 => 1.2,
			default => 1.35,
		};

		return $newPlayerBonus * $pingFactor;
	}
}
