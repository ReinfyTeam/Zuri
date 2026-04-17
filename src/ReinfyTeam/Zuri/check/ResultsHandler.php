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
use pocketmine\Server;
use ReinfyTeam\Zuri\config\ConfigPath;
use ReinfyTeam\Zuri\player\PlayerManager;
use ReinfyTeam\Zuri\ZuriAC;
use function max;
use function min;
use function strtolower;
use function unserialize;

/**
 * Handles the results of checks and applies violations to players accordingly.
 *
 * This class receives check results produced by async worker tasks and applies
 * pre-violations and violations to players. Thresholds are adjusted based on
 * server conditions such as ping, TPS and player load.
 */
final class ResultsHandler {
	/**
	 * Handles the result of a check and applies punishments if necessary.
	 * Automatically adjusts threshold based on the player condition.
	 *
	 *	@param array $results The result data from a check.
	 */
	public static function handle(array $results) : void {
		if (($player = Server::getInstance()->getPlayerExact($results["player"])) !== null) {
			$playerZuri = PlayerManager::get($player);

			$check = unserialize($results["check"]);

			if ($results["result"]["failed"]) {
				self::handlePunishment($player, $check);
			}

			if ($playerZuri->isDebug()) {
			}
		}
	}

	/**
	 * Apply punishment logic for a failed check.
	 *
	 * This updates the player's pre-violation and violation counters and
	 * performs configured punishments (kick/ban) when thresholds are reached.
	 *
	 * @param Player $player The player to punish.
	 * @param Check $check The check that was failed.
	 */
	public static function handlePunishment(Player $player, Check $check) : void {
		$threshold = self::adjustThreshold($player, $check);

		$playerZuri = PlayerManager::get($player);

		$reachedMaxPreViolations = $playerZuri->getPreViolation($check->getName()) > $check->getMaxPreViolation();
		$reachedMaxViolations = $playerZuri->getViolation($check->getName()) > $check->getMaxViolation();

		$playerZuri->addPreViolation($check->getName(), $threshold);

		if ($reachedMaxPreViolations) {
			$playerZuri->addViolation($check->getName(), $threshold);
			$playerZuri->resetPreViolation($check->getName());
		}

		if ($reachedMaxPreViolations && $reachedMaxViolations) {
			$punishment = $check->getPunishment();
			$banType = ZuriAC::getConfigManager()->getData(ConfigPath::PUNISHMENT_BAN_TYPE);
			$kickType = ZuriAC::getConfigManager()->getData(ConfigPath::PUNISHMENT_KICK_TYPE);

			match (strtolower($punishment)) {
				"kick" => match (strtolower($kickType)) {
					"native" => self::nativeKick($player),
					"command" => self::commandKick($player)
				},
				"ban" => match (strtolower($banType)) {
					"native" => self::nativeBan($player),
					"command" => self::commandBan($player),
				},
				default => $playerZuri->setFlagged(true),
			};

			$playerZuri->resetViolation($check->getName());
		}
	}

	public static function nativeKick(Player $player) : void {
		$player->kick(ZuriAC::getLanguageManager()->getCurrentLanguage()->translate(LanguagePath::KICK_MESSAGE));
	}

	public static function commandKick(Player $player) : void {
		Server::getInstance()->dispatchCommand(Server::getInstance()->getConsoleSender(), ZuriAC::getConfigManager()->getData(ConfigPath::PUNISHMENT_KICK_COMMAND, null, [
			"{player}" => '"' . $player->getName() . '"' // safe player name insertion for names with spaces
		]));
	}

	public static function nativeBan(Player $player) : void {
		Server::getInstance()->getNameBans()->addBan($player->getName(), ZuriAC::getLanguageManager()->getCurrentLanguage()->translate(LanguagePath::PUNISHMENT_BAN_MESSAGE), Utils::parseToDateTime(ZuriAC::getConfigManager()->getData(ConfigPath::PUNISHMENT_BAN_DURATION)), null);
		$player->kick(ZuriAC::getLanguageManager()->getCurrentLanguage()->translate(LanguagePath::PUNISHMENT_BAN_MESSAGE));
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
		$multiplier *= self::getLoadMultiplier($loadFactor, $checkType);

		// Thresholds should only increase (more lenient), never decrease
		return $baseThreshold * max(1.0, $multiplier);
	}

	/**
	 * Calculate a multiplier based on player ping.
	 */
	public static function getPingMultiplier(int $ping, Check $checkType) : float {
		// Movement checks are more sensitive to ping
		$sensitivity = ZuriAC::getConfigManager()->getData(ConfigPath::THRESHOLDS_PING . strtolower($checkType->getName()), ZuriAC::getConfigManager()->getData(ConfigPath::THRESHOLD_PING_DEFAULT_MULTIPLIER, 1.0));

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

		$sensitivity = ZuriAC::getConfigManager()->getData(ConfigPath::THRESHOLDS_TPS . strtolower($checkType->getName()), ZuriAC::getConfigManager()->getData(ConfigPath::THRESHOLD_TPS_DEFAULT_MULTIPLIER, 1.0));

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