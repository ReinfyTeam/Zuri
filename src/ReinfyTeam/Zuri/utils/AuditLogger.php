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
use ReinfyTeam\Zuri\config\ConfigManager;
use ReinfyTeam\Zuri\task\CheckAsyncTask;
use ReinfyTeam\Zuri\ZuriAC;
use function count;
use function date;
use function error_get_last;
use function file_exists;
use function file_put_contents;
use function filemtime;
use function glob;
use function hash;
use function implode;
use function in_array;
use function is_array;
use function is_bool;
use function is_dir;
use function is_numeric;
use function is_string;
use function ksort;
use function microtime;
use function mkdir;
use function pathinfo;
use function preg_replace;
use function register_shutdown_function;
use function round;
use function sprintf;
use function str_repeat;
use function strtotime;
use function trim;
use function unlink;
use const DIRECTORY_SEPARATOR;
use const E_COMPILE_ERROR;
use const E_CORE_ERROR;
use const E_ERROR;
use const E_PARSE;
use const FILE_APPEND;
use const LOCK_EX;
use const PATHINFO_DIRNAME;
use const PHP_VERSION;

final class AuditLogger {
	private static string $lastHash = "genesis";
	private static bool $booted = false;
	private static float $lastCleanupAt = 0.0;

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
	public static function detection(string $action, string $target, string $check, string $subType, array $details = []) : void {
		$details["check"] = $check;
		$details["subType"] = $subType;
		self::log("detection", $action, $target, $details);
	}

	public static function anticheat(string $message) : void {
		self::bootIfNeeded();
		if (!self::isEnabled("anticheat")) {
			return;
		}
		self::appendLine(self::logsPath(self::fileName("anticheat", "anticheat.log")), self::timestamp() . " " . $message);
	}

	public static function thread(string $message) : void {
		self::bootIfNeeded();
		if (!self::isEnabled("thread")) {
			return;
		}
		self::appendLine(self::logsPath(self::fileName("thread", "thread.log")), self::timestamp() . " " . $message);
	}

	public static function crash(string $message) : void {
		self::bootIfNeeded();
		if (!self::isEnabled("crash")) {
			return;
		}
		self::appendLine(self::crashesPath(self::fileName("crash", "crash.log")), self::timestamp() . " " . $message);
	}

	public static function createReportFile() : string {
		self::bootIfNeeded();
		$server = Server::getInstance();
		$metrics = CheckAsyncTask::getMetrics();
		$checks = ZuriAC::Checks();
		$enabledChecks = 0;
		$disabledChecks = 0;
		foreach ($checks as $check) {
			if ($check->enable()) {
				$enabledChecks++;
			} else {
				$disabledChecks++;
			}
		}
		$lines = [
			"Zuri Report",
			"Generated: " . date("Y-m-d H:i:s"),
			"Plugin: " . ZuriAC::getInstance()->getDescription()->getVersion(),
			"PMMP: " . $server->getVersion(),
			"PHP: " . PHP_VERSION,
			"Online players: " . count($server->getOnlinePlayers()) . "/" . $server->getMaxPlayers(),
			"TPS: " . $server->getTicksPerSecond(),
			"Checks enabled/disabled: {$enabledChecks}/{$disabledChecks}",
			"",
			"Async:",
			"- queue: " . ($metrics["queueSize"] ?? 0) . "/" . ($metrics["maxQueueSize"] ?? 0),
			"- inFlight: " . ($metrics["inFlight"] ?? 0) . "/" . ($metrics["maxConcurrentWorkers"] ?? 0),
			"- dispatched/completed/dropped: " . ($metrics["totalDispatched"] ?? 0) . "/" . ($metrics["totalCompleted"] ?? 0) . "/" . ($metrics["totalDropped"] ?? 0),
			"- threadErrors/resultErrors/retries: " . ($metrics["totalThreadErrors"] ?? 0) . "/" . ($metrics["totalThreadResultErrors"] ?? 0) . "/" . ($metrics["totalThreadRetries"] ?? 0),
			"- syncFallback/errors: " . ($metrics["totalSyncFallback"] ?? 0) . "/" . ($metrics["totalFallbackErrors"] ?? 0),
			"- avgWorkerSeconds: " . round((float) ($metrics["avgWorkerTime"] ?? 0.0), 4),
			"- latency(build/queue/worker/merge): "
				. round((float) ($metrics["avgBuildDelay"] ?? 0.0), 4) . "/"
				. round((float) ($metrics["avgQueueWait"] ?? 0.0), 4) . "/"
				. round((float) ($metrics["avgWorkerTime"] ?? 0.0), 4) . "/"
				. round((float) ($metrics["avgMergeTime"] ?? 0.0), 4),
			"- workerTargetMs: " . round((float) ($metrics["workerTargetMs"] ?? 20.0), 2),
			"",
			"Profiler:",
			"- metrics: " . HotPathProfiler::getMetricCount(),
			"- totalMs: " . round(HotPathProfiler::getTotalMillis(), 3),
			"- packetAvgMs: " . round(HotPathProfiler::getAverageMillis("packet.handler.receive"), 3),
			"",
			"Safe exceptions (last window): " . ExceptionHandler::getErrorCount(),
		];
		$file = self::logsPath(self::fileName("report", "report.txt"));
		self::appendLine($file, implode(PHP_EOL, $lines) . PHP_EOL . str_repeat("-", 72));
		return $file;
	}

	public static function bootIfNeeded() : void {
		if (self::$booted) {
			if ((microtime(true) - self::$lastCleanupAt) > 3600.0) {
				self::cleanupOldFiles();
			}
			return;
		}
		self::$booted = true;
		self::ensureDirectory(self::logsPath(""));
		self::ensureDirectory(self::crashesPath(""));
		self::cleanupOldFiles();
		register_shutdown_function(static function() : void {
			$error = error_get_last();
			if (!is_array($error)) {
				return;
			}
			$type = (int) $error["type"];
			if (!in_array($type, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
				return;
			}
			$message = (string) $error["message"];
			$file = (string) $error["file"];
			$line = (int) $error["line"];
			self::crash("Hard-crash captured: {$message} at {$file}:{$line}");
		});
	}

	/** @param array<string,string|int|float|bool> $details */
	private static function log(string $category, string $action, string $actor, array $details) : void {
		self::bootIfNeeded();
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
		Server::getInstance()->getLogger()->debug("[DEBUG] AUDIT (" . $category . ") actor=" . $actor . ", action=" . $action . $detailText . " [chain=" . $currentHash . "]");
		$line = self::timestamp() . " category={$category}, actor={$actor}, action={$action}{$detailText}, chain={$currentHash}";
		if ($category === "punishment" || $category === "detection") {
			if (self::isEnabled("punishment")) {
				self::appendLine(self::logsPath(self::fileName("punishment", "punishment.log")), $line);
			}
		} else {
			if (self::isEnabled("anticheat")) {
				self::appendLine(self::logsPath(self::fileName("anticheat", "anticheat.log")), $line);
			}
		}
	}

	private static function timestamp() : string {
		return "[" . date("Y-m-d H:i:s") . "]";
	}

	private static function logsPath(string $file) : string {
		$folderRaw = ConfigManager::getData("zuri.logging.folders.logs", "logs");
		$folder = is_string($folderRaw) && $folderRaw !== "" ? $folderRaw : "logs";
		return ZuriAC::getInstance()->getDataFolder() . $folder . DIRECTORY_SEPARATOR . $file;
	}

	private static function crashesPath(string $file) : string {
		$folderRaw = ConfigManager::getData("zuri.logging.folders.crashes", "crashes");
		$folder = is_string($folderRaw) && $folderRaw !== "" ? $folderRaw : "crashes";
		return ZuriAC::getInstance()->getDataFolder() . $folder . DIRECTORY_SEPARATOR . $file;
	}

	private static function ensureDirectory(string $path) : void {
		if ($path === "") {
			return;
		}
		if (is_dir($path)) {
			return;
		}
		@mkdir($path, 0777, true);
	}

	private static function appendLine(string $file, string $line) : void {
		$dir = pathinfo($file, PATHINFO_DIRNAME);
		if (is_string($dir) && $dir !== "") {
			self::ensureDirectory($dir);
		}
		$sanitized = self::stripFormattingCodes($line);
		@file_put_contents($file, $sanitized . PHP_EOL, FILE_APPEND | LOCK_EX);
	}

	private static function stripFormattingCodes(string $text) : string {
		// Strip Bedrock color/style codes like §a, &c, and hex forms.
		$text = (string) preg_replace('/(?:§|&)x(?:(?:§|&)[0-9A-Fa-f]){6}/', '', $text);
		$text = (string) preg_replace('/(?:§|&)[0-9A-FK-ORa-fk-or]/', '', $text);
		return trim($text);
	}

	private static function isEnabled(string $bucket) : bool {
		$globalRaw = ConfigManager::getData("zuri.logging.enable", true);
		$global = !is_bool($globalRaw) || $globalRaw;
		if (!$global) {
			return false;
		}
		$bucketRaw = ConfigManager::getData("zuri.logging.channels.{$bucket}.enable", true);
		return !is_bool($bucketRaw) || $bucketRaw;
	}

	private static function fileName(string $bucket, string $fallback) : string {
		$fileRaw = ConfigManager::getData("zuri.logging.channels.{$bucket}.file", $fallback);
		return is_string($fileRaw) && $fileRaw !== "" ? $fileRaw : $fallback;
	}

	private static function cleanupOldFiles() : void {
		self::$lastCleanupAt = microtime(true);
		$maxAgeRaw = ConfigManager::getData("zuri.logging.cleanup.max-age-days", 30);
		$maxAgeDays = is_numeric($maxAgeRaw) ? (int) $maxAgeRaw : 30;
		$cutoffDateRaw = ConfigManager::getData("zuri.logging.cleanup.delete-before-date", "");
		$cutoffDate = is_string($cutoffDateRaw) && $cutoffDateRaw !== "" ? strtotime($cutoffDateRaw) : false;
		$ageCutoff = $maxAgeDays > 0 ? (microtime(true) - ($maxAgeDays * 86400)) : 0.0;

		$patterns = [
			self::logsPath("*.log"),
			self::logsPath("*.txt"),
			self::crashesPath("*.log"),
			self::crashesPath("*.txt"),
		];
		foreach ($patterns as $pattern) {
			$files = glob($pattern);
			if (!is_array($files)) {
				continue;
			}
			foreach ($files as $file) {
				$mtime = @file_exists($file) ? @filemtime($file) : false;
				if (!is_numeric($mtime)) {
					continue;
				}
				$byAge = $maxAgeDays > 0 && $mtime < $ageCutoff;
				$byDate = is_numeric($cutoffDate) && $mtime < $cutoffDate;
				if ($byAge || $byDate) {
					@unlink($file);
				}
			}
		}
	}
}
