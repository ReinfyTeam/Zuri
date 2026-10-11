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

use pocketmine\console\ConsoleCommandSender;
use pocketmine\player\Player;
use pocketmine\Server;
use ReinfyTeam\Zuri\config\ConfigPath;
use ReinfyTeam\Zuri\config\language\LanguagePath;
use ReinfyTeam\Zuri\player\PlayerManager;
use ReinfyTeam\Zuri\utils\MathUtil;
use ReinfyTeam\Zuri\ZuriAC;
use function is_array;
use function is_string;
use function is_subclass_of;
use function max;
use function min;
use function strtolower;

/**
 * Handles the results of checks and applies violations to players accordingly.
 *
 * This class receives check results produced by worker threads and applies
 * pre-violations and violations to players. Thresholds are adjusted based on
 * server conditions such as ping, TPS and player load.
 */
final class ResultsHandler {
	/** @param array{result:array<array-key,mixed>,check:string,player:?string} $results */
	public static function handle(array $results) : void {
		$result = $results["result"];
		if (isset($result["error"]) && is_array($result["error"])) {
			$message = $result["error"]["message"] ?? "Unknown check error";
			Server::getInstance()->getLogger()->error("Check " . $results["check"] . " failed: " . (is_string($message) ? $message : "Unknown check error"));
			return;
		}
		$playerName = $results["player"];
		if ($playerName === null || ($player = Server::getInstance()->getPlayerExact($playerName)) === null || !is_subclass_of($results["check"], Check::class)) {
			return;
		}
		$check = new $results["check"]();
		$playerZuri = PlayerManager::get($player);
		if (($result["failed"] ?? false) === true) {
			self::handlePunishment($player, $check);
		}
		$externalData = $result["externalData"] ?? [];
		if (is_array($externalData)) {
			foreach ($externalData as $parameter => $value) {
				if (is_string($parameter)) {
					ZuriAC::getExternalData()->setExternalData($playerZuri, $check->getName(), $parameter, $value);
				}
			}
		}
	}

	public static function handlePunishment(Player $player, Check $check) : void {
		$threshold = self::adjustThreshold($player, $check);
		$playerZuri = PlayerManager::get($player);
		$playerZuri->addPreViolation($check);
		if ($playerZuri->getPreViolations($check) < max(1, $check->getMaxPreViolation()) * $threshold) {
			return;
		}
		$playerZuri->addViolation($check);
		$playerZuri->resetPreViolation($check);
		if ($playerZuri->getViolations($check) < max(1, $check->getMaxViolation()) * $threshold) {
			return;
		}
		$config = ZuriAC::getConfigManager();
		switch (strtolower($check->getPunishment())) {
			case "kick":
				if (strtolower($config->getString(ConfigPath::PUNISHMENT_KICK_TYPE, "command")) === "native") {
					self::nativeKick($player);
				} else {
					self::commandKick($player);
				}
				break;
			case "ban":
				if (strtolower($config->getString(ConfigPath::PUNISHMENT_BAN_TYPE, "command")) === "native") {
					self::nativeBan($player);
				} else {
					self::commandBan($player);
				}
				break;
			default:
				$playerZuri->setFlagged(true);
		}
		$playerZuri->resetViolation($check);
	}

	public static function nativeKick(Player $player) : void {
		$player->kick(ZuriAC::getLanguageManager()->getCurrentLanguage()->translate(LanguagePath::KICK_MESSAGE));
	}

	public static function commandKick(Player $player) : void {
		self::dispatchPunishment($player, ConfigPath::PUNISHMENT_KICK_COMMAND, "kick {player} Unfair Advantage");
	}

	public static function commandBan(Player $player) : void {
		self::dispatchPunishment($player, ConfigPath::PUNISHMENT_BAN_COMMAND, "ban {player} Unfair Advantage");
	}

	private static function dispatchPunishment(Player $player, string $key, string $default) : void {
		$server = Server::getInstance();
		$command = ZuriAC::getConfigManager()->getString($key, $default, ["{player}" => '"' . $player->getName() . '"']);
		$server->dispatchCommand(new ConsoleCommandSender($server, $server->getLanguage()), $command);
	}

	public static function nativeBan(Player $player) : void {
		$message = ZuriAC::getLanguageManager()->getCurrentLanguage()->translate(LanguagePath::PUNISHMENT_BAN_MESSAGE);
		$duration = ZuriAC::getConfigManager()->getString(ConfigPath::PUNISHMENT_BAN_DURATION, "30d");
		$expires = strtolower($duration) === "permanent" ? null : MathUtil::parseToDateTime($duration);
		Server::getInstance()->getNameBans()->addBan($player->getName(), $message, $expires, null);
		$player->kick($message);
	}

	/**
	 * Adjust the punishment threshold based on player and server conditions.
	 *
	 * The returned threshold should be >= base threshold; it accounts for ping,
	 * TPS and server load to allow more tolerance during laggy conditions.
	 */
	public static function adjustThreshold(Player $player, Check $checkType) : float {
		$multiplier = 1.0;

		$server = Server::getInstance();

		// Apply ping adjustment
		$ping = $player->getNetworkSession()->getPing() ?? 0;
		$multiplier *= self::getPingMultiplier($ping, $checkType);

		$tps = ZuriAC::getMetricsData()->getServerTPS();
		$maxPlayers = ZuriAC::getMetricsData()->getMaxPlayerCount();
		$onlinePlayers = ZuriAC::getMetricsData()->getPlayerCount();

		// Apply TPS adjustment
		$multiplier *= self::getTpsMultiplier($tps, $checkType);

		// Apply load factors (current players)
		$playerLoad = $maxPlayers > 0 ? $onlinePlayers / $maxPlayers : 0.0;
		$tpsLoad = max(0.0, (20.0 - $tps) / 10.0); // 0 at 20 TPS, 1.0 at 10 TPS

		$loadFactor = min(1.0, ($playerLoad * 0.4) + ($tpsLoad * 0.6));

		// Apply load adjustment
		$multiplier *= self::getLoadMultiplier($loadFactor);

		// Thresholds should only increase (more lenient), never decrease
		return max(1.0, $multiplier);
	}

	/**
	 * Calculate a multiplier based on player ping.
	 */
	public static function getPingMultiplier(int $ping, Check $checkType) : float {
		// Movement checks are more sensitive to ping
		$sensitivity = ZuriAC::getConfigManager()->getFloat(ConfigPath::THRESHOLDS_PING . "." . strtolower($checkType->getName()), ZuriAC::getConfigManager()->getFloat(ConfigPath::THRESHOLD_PING_DEFAULT_MULTIPLIER, 1.0));

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
	 * Calculate a multiplier based on server load factor.
	 *
	 * @param float $loadFactor Value in [0,1] describing normalized server load.
	 */
	public static function getLoadMultiplier(float $loadFactor) : float {
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
	 * Calculate a multiplier based on server TPS.
	 *
	 * @param float $tps Current server ticks per second.
	 */
	public static function getTpsMultiplier(float $tps, Check $checkType) : float {
		// Timer checks are most sensitive to TPS fluctuation

		$sensitivity = ZuriAC::getConfigManager()->getFloat(ConfigPath::THRESHOLDS_TPS . "." . strtolower($checkType->getName()), ZuriAC::getConfigManager()->getFloat(ConfigPath::THRESHOLD_TPS_DEFAULT_MULTIPLIER, 1.0));

		return match (true) {
			$tps >= 19.5 => 1.0,
			$tps >= 18.0 => 1.0 + (0.1 * $sensitivity),
			$tps >= 16.0 => 1.0 + (0.25 * $sensitivity),
			$tps >= 14.0 => 1.0 + (0.5 * $sensitivity),
			$tps >= 10.0 => 1.0 + (0.8 * $sensitivity),
			default => 1.0 + (1.2 * $sensitivity), // Severe lag
		};
	}
}