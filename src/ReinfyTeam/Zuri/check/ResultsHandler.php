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
use ReinfyTeam\Zuri\utils\TextUtil;
use ReinfyTeam\Zuri\utils\Utils;
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

	/**
	 * @param array{
	 *     result: array<array-key, mixed>,
	 *     check: string,
	 *     subtype: ?string,
	 *     player: ?string
	 * } $results
	 */
	public static function handle(array $results) : void {
		$result = $results["result"];
		if (isset($result["error"]) && is_array($result["error"])) {
			$message = Utils::readString($result["error"]["message"] ?? null, ZuriAC::getLanguageManager()->getCurrentLanguage()->translate(LanguagePath::CHECK_UNKNOWN_ERROR));
			Server::getInstance()->getLogger()->error(ZuriAC::getLanguageManager()->getCurrentLanguage()->translate(LanguagePath::CHECK_ERROR, ["{check}" => $results["check"], "{message}" => $message]));
			return;
		}

		$playerName = $results["player"];
		$checkClass = Utils::readString($results["check"]);
		$subType = Utils::readString($results["subtype"]);
		if ($playerName === null || ($player = Server::getInstance()->getPlayerExact($playerName)) === null || !is_subclass_of($checkClass, Check::class)) {
			return; // if the player is not online or the check class is invalid, we cannot handle the result
		}
		$playerZuri = PlayerManager::get($player);

		if (($result["failed"] ?? false) === true) {
			self::handlePunishment($player, $checkClass, $subType);
		}

		$externalData = Utils::readArray($result["externalData"] ?? null);
		foreach ($externalData as $parameter => $value) {
			if (is_string($parameter)) { // only accept string
				ZuriAC::getExternalData()->setExternalData($playerZuri, $results["check"], $parameter, $value);
			}
		}

		if ($playerZuri->isDebug()) {
			$debugData = Utils::readArray($result["debug"] ?? null);
			$player->sendMessage(ZuriAC::getLanguageManager()->getCurrentLanguage()->translate(LanguagePath::CHECK_DEBUG_MESSAGE, [
				"{check}" => $results["check"],
				"{subType}" => $results["subtype"],
				"{debug_data}" => TextUtil::prettyPrintArray($debugData)
			]));
		}
	}

	public static function handlePunishment(Player $player, string $check, string $subType) : void {
		/** @var Check $checkClass */
		$checkClass = ZuriAC::getCheckRegistry()->getCheckByName($check, $subType);
		$threshold = self::adjustThreshold($player, $checkClass);
		$playerZuri = PlayerManager::get($player);
		$playerZuri->addPreViolation($checkClass);
		if ($playerZuri->getPreViolations($checkClass) < max(1, $checkClass->getMaxPreViolation()) * $threshold) {
			return;
		}
		$playerZuri->addViolation($checkClass);
		$playerZuri->resetPreViolation($checkClass);
		if ($playerZuri->getViolations($checkClass) < max(1, $checkClass->getMaxViolation()) * $threshold) {
			return;
		}
		$config = ZuriAC::getConfigManager();
		switch (strtolower($checkClass->getPunishment())) {
			case "kick":
				if (strtolower($config->getString(ConfigPath::PUNISHMENT_KICK_TYPE)) === "native") {
					self::nativeKick($player);
				} else {
					self::commandKick($player);
				}
				break;
			case "ban":
				if (strtolower($config->getString(ConfigPath::PUNISHMENT_BAN_TYPE)) === "native") {
					self::nativeBan($player);
				} else {
					self::commandBan($player);
				}
				break;
			default:
				$playerZuri->setFlagged(true);
		}
		$playerZuri->resetViolation($checkClass);
	}

	public static function nativeKick(Player $player) : void {
		$player->kick(ZuriAC::getLanguageManager()->getCurrentLanguage()->translate(LanguagePath::KICK_MESSAGE));
	}

	public static function commandKick(Player $player) : void {
		self::dispatchPunishment($player, ConfigPath::PUNISHMENT_KICK_COMMAND);
	}

	public static function commandBan(Player $player) : void {
		self::dispatchPunishment($player, ConfigPath::PUNISHMENT_BAN_COMMAND);
	}

	private static function dispatchPunishment(Player $player, string $key) : void {
		$server = Server::getInstance();
		$command = ZuriAC::getConfigManager()->getString($key, ["{player}" => '"' . $player->getName() . '"']);
		$server->dispatchCommand(new ConsoleCommandSender($server, $server->getLanguage()), $command);
	}

	public static function nativeBan(Player $player) : void {
		$message = ZuriAC::getLanguageManager()->getCurrentLanguage()->translate(LanguagePath::PUNISHMENT_BAN_MESSAGE);
		$duration = ZuriAC::getConfigManager()->getString(ConfigPath::PUNISHMENT_BAN_DURATION);
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
		$sensitivity = Utils::readFloat(ZuriAC::getConfigManager()->getData(ConfigPath::THRESHOLDS_PING . "." . strtolower($checkType->getName()), ZuriAC::getConfigManager()->getFloat(ConfigPath::THRESHOLD_PING_DEFAULT_MULTIPLIER)));

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

		$sensitivity = Utils::readFloat(ZuriAC::getConfigManager()->getData(ConfigPath::THRESHOLDS_TPS . "." . strtolower($checkType->getName()), ZuriAC::getConfigManager()->getFloat(ConfigPath::THRESHOLD_TPS_DEFAULT_MULTIPLIER)));

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