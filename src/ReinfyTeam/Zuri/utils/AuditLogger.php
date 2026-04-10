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

use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\Server;
use function hash;
use function implode;
use function ksort;
use function microtime;
use function sprintf;

final class AuditLogger {
	private static string $lastHash = "genesis";

	/** @param array<string,string|int|float|bool> $details */
	public static function command(CommandSender $sender, string $command, array $details = []) : void {
		$actor = $sender instanceof Player ? $sender->getName() : $sender->getName();
		self::log("command", $command, $actor, $details);
	}

	/** @param array<string,string|int|float|bool> $details */
	public static function punishment(string $action, string $target, string $check, string $subType, array $details = []) : void {
		$details["check"] = $check;
		$details["subType"] = $subType;
		self::log("punishment", $action, $target, $details);
	}

	/** @param array<string,string|int|float|bool> $details */
	private static function log(string $category, string $action, string $actor, array $details) : void {
		ksort($details);
		$detailPairs = [];
		foreach ($details as $k => $v) {
			$detailPairs[] = $k . "=" . (string) $v;
		}
		$detailText = $detailPairs !== [] ? " [" . implode(", ", $detailPairs) . "]" : "";
		$timestamp = sprintf("%.6f", microtime(true));
		$payload = $timestamp . "|" . $category . "|" . $action . "|" . $actor . "|" . implode("|", $detailPairs) . "|" . self::$lastHash;
		$currentHash = hash("sha256", $payload);
		self::$lastHash = $currentHash;
		Server::getInstance()->getLogger()->notice("[Zuri][Audit] category={$category} action={$action} actor={$actor}{$detailText} chain={$currentHash}");
	}
}
