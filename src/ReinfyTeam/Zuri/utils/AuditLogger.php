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
use ReinfyTeam\Zuri\config\ConfigPaths;
use ReinfyTeam\Zuri\lang\Lang;
use ReinfyTeam\Zuri\lang\LangKeys;
use ReinfyTeam\Zuri\task\CheckAsyncTask;
use ReinfyTeam\Zuri\ZuriAC;
use Throwable;
use function array_slice;
use function count;
use function date;
use function error_get_last;
use function file;
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
use function is_float;
use function is_int;
use function is_numeric;
use function is_readable;
use function is_string;
use function ksort;
use function max;
use function microtime;
use function mkdir;
use function pathinfo;
use function preg_replace;
use function register_shutdown_function;
use function round;
use function sprintf;
use function str_repeat;
use function str_replace;
use function stripos;
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

/**
 * Writes tamper-evident operational, detection, punishment, and crash audit logs.
 */
final class AuditLogger {
	private static string $lastHash = "genesis";
	private static bool $booted = false;
	private static float $lastCleanupAt = 0.0;

	/** @param array<string,string|int|float|bool> $details */
	/**
	 * Records an administrative command action in the audit stream.
	 *
	 * @param CommandSender $sender Command actor.
	 * @param string $command Executed command string.
	 * @param array<string,string|int|float|bool> $details Additional metadata.
	 */
	public static function command(CommandSender $sender, string $command, array $details = []) : void {
		$actor = $sender instanceof Player ? $sender->getName() : $sender->getName();
		self::log("command", $command, $actor, $details);
	}

	/** @param array<string,string|int|float|bool> $details */
	/**
	 * Records a punishment action in the audit stream.
	 *
	 * @param string $action Action label.
	 * @param string $target Target player or entity.
	 * @param string $check Triggering check name.
	 * @param string $subType Triggering check subtype.
	 * @param array<string,string|int|float|bool> $details Additional metadata.
	 */
	public static function punishment(string $action, string $target, string $check, string $subType, array $details = []) : void {
		$details["check"] = $check;
		$details["subType"] = $subType;
		self::log("punishment", $action, $target, $details);
	}

	/** @param array<string,string|int|float|bool> $details */
	/**
	 * Records a detection action in the audit stream.
	 *
	 * @param string $action Action label.
	 * @param string $target Target player or entity.
	 * @param string $check Triggering check name.
	 * @param string $subType Triggering check subtype.
	 * @param array<string,string|int|float|bool> $details Additional metadata.
	 */
	public static function detection(string $action, string $target, string $check, string $subType, array $details = []) : void {
		$details["check"] = $check;
		$details["subType"] = $subType;
		self::log("detection", $action, $target, $details);
	}

	/**
	 * Writes a plain anti-cheat log line when channel is enabled.
	 *
	 * @param string $message Log message.
	 */
	public static function anticheat(string $message) : void {
		self::bootIfNeeded();
		if (!self::isEnabled("anticheat")) {
			return;
		}
		self::appendLine(self::logsPath(self::fileName("anticheat", "anticheat.log")), self::timestamp() . " " . $message);
	}

	/**
	 * Writes thread/runtime diagnostics to thread log channel.
	 *
	 * @param string $message Log message.
	 */
	public static function thread(string $message) : void {
		self::bootIfNeeded();
		if (!self::isEnabled("thread")) {
			return;
		}
		self::appendLine(self::logsPath(self::fileName("thread", "thread.log")), self::timestamp() . " " . $message);
	}

	/**
	 * Writes crash-oriented messages to crash log channel.
	 *
	 * @param string $message Log message.
	 */
	public static function crash(string $message) : void {
		self::bootIfNeeded();
		if (!self::isEnabled("crash")) {
			return;
		}
		self::appendLine(self::crashesPath(self::fileName("crash", "crash.log")), self::timestamp() . " " . $message);
	}

	/**
	 * Logs a throwable summary and stack trace into crash logs.
	 *
	 * @param string $context Crash context label.
	 * @param Throwable $throwable Throwable to serialize.
	 */
	public static function crashThrowable(string $context, Throwable $throwable) : void {
		$summary = self::tr(LangKeys::DEBUG_AUDIT_THROWABLE_SUMMARY, [
			"context" => $context,
			"type" => $throwable::class,
			"message" => $throwable->getMessage(),
			"file" => $throwable->getFile(),
			"line" => $throwable->getLine(),
		], "{context}: type={type}, message={message}, file={file}, line={line}");
		self::crash($summary);
		$trace = trim($throwable->getTraceAsString());
		if ($trace !== "") {
			self::crash(self::tr(LangKeys::DEBUG_AUDIT_STACK_TRACE, [
				"context" => $context,
				"trace" => str_replace(["\r", "\n"], ["", " | "], $trace),
			], "Stack trace ({context}): {trace}"));
		}
	}

	/**
	 * Generates and stores a human-readable runtime diagnostics report.
	 *
	 * @return string Absolute report file path.
	 */
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
		$queueSize = self::metricInt($metrics, "queueSize");
		$maxQueueSize = max(1, self::metricInt($metrics, "maxQueueSize"));
		$inFlight = self::metricInt($metrics, "inFlight");
		$maxWorkers = max(1, self::metricInt($metrics, "maxConcurrentWorkers"));
		$totalDispatched = self::metricInt($metrics, "totalDispatched");
		$totalCompleted = self::metricInt($metrics, "totalCompleted");
		$totalDropped = self::metricInt($metrics, "totalDropped");
		$throughput = $totalDispatched > 0 ? ($totalCompleted / $totalDispatched) : 0.0;
		$dropRate = $totalDispatched > 0 ? ($totalDropped / $totalDispatched) : 0.0;

		$lines = [
			self::tr("commands.report.content.title", [], "Zuri Report"),
			self::tr("commands.report.content.generated", ["date" => date("Y-m-d H:i:s")], "Generated: {date}"),
			"",
			self::tr("commands.report.content.section-system", [], "System"),
			self::tr("commands.report.content.plugin-version", ["version" => ZuriAC::getInstance()->getDescription()->getVersion()], "- Plugin version: {version}"),
			self::tr("commands.report.content.pmmp-version", ["version" => $server->getVersion()], "- PMMP version: {version}"),
			self::tr("commands.report.content.php-version", ["version" => PHP_VERSION], "- PHP version: {version}"),
			self::tr("commands.report.content.players", ["online" => count($server->getOnlinePlayers()), "max" => $server->getMaxPlayers()], "- Players: {online}/{max}"),
			self::tr("commands.report.content.tps", ["tps" => round((float) $server->getTicksPerSecond(), 2)], "- TPS: {tps}"),
			self::tr("commands.report.content.checks", ["enabled" => $enabledChecks, "disabled" => $disabledChecks], "- Checks enabled/disabled: {enabled}/{disabled}"),
			"",
			self::tr("commands.report.content.section-async", [], "Async pipeline"),
			self::tr("commands.report.content.async-queue", ["queue" => $queueSize, "maxQueue" => $maxQueueSize, "utilization" => self::formatPercent($queueSize / $maxQueueSize)], "- Queue: {queue}/{maxQueue} ({utilization})"),
			self::tr("commands.report.content.async-batches", ["classGroups" => self::metricInt($metrics, "queueClassGroups"), "batchUnits" => self::metricInt($metrics, "queueBatchUnits"), "batchSize" => self::metricInt($metrics, "batchSize")], "- Queue groups/batches@size: {classGroups}/{batchUnits}@{batchSize}"),
			self::tr("commands.report.content.async-workers", ["inFlight" => $inFlight, "maxWorkers" => $maxWorkers, "utilization" => self::formatPercent($inFlight / $maxWorkers), "minWorkers" => self::metricInt($metrics, "minConcurrentWorkers")], "- Workers: {inFlight}/{maxWorkers} ({utilization}, min={minWorkers})"),
			self::tr("commands.report.content.async-throughput", ["dispatched" => $totalDispatched, "completed" => $totalCompleted, "throughput" => self::formatPercent($throughput), "dropped" => $totalDropped, "dropRate" => self::formatPercent($dropRate)], "- Throughput: dispatched={dispatched}, completed={completed} ({throughput}), dropped={dropped} ({dropRate})"),
			self::tr("commands.report.content.async-threads", ["threadErrors" => self::metricInt($metrics, "totalThreadErrors"), "resultErrors" => self::metricInt($metrics, "totalThreadResultErrors"), "retries" => self::metricInt($metrics, "totalThreadRetries"), "late" => self::metricInt($metrics, "totalLateCompletions")], "- Thread outcomes: errors={threadErrors}, resultErrors={resultErrors}, retries={retries}, late={late}"),
			self::tr("commands.report.content.async-fallback", ["active" => self::formatBool(($metrics["syncFallbackActive"] ?? false)), "count" => self::metricInt($metrics, "totalSyncFallback"), "errors" => self::metricInt($metrics, "totalFallbackErrors")], "- Sync fallback: active={active}, used={count}, errors={errors}"),
			self::tr("commands.report.content.async-scaling", ["optimizations" => self::metricInt($metrics, "totalQueueOptimizations"), "scaleUps" => self::metricInt($metrics, "totalWorkerScaleUps"), "scaleDowns" => self::metricInt($metrics, "totalWorkerScaleDowns"), "overloadAlerts" => self::metricInt($metrics, "totalOverloadAlerts"), "overloadActive" => self::formatBool(($metrics["overloadActive"] ?? false))], "- Optimizer: runs={optimizations}, up={scaleUps}, down={scaleDowns}, alerts={overloadAlerts}, overload={overloadActive}"),
			self::tr("commands.report.content.async-latency", ["build" => round(self::metricFloat($metrics, "avgBuildDelay"), 4), "queue" => round(self::metricFloat($metrics, "avgQueueWait"), 4), "worker" => round(self::metricFloat($metrics, "avgWorkerTime"), 4), "merge" => round(self::metricFloat($metrics, "avgMergeTime"), 4), "targetMs" => round(self::metricFloat($metrics, "workerTargetMs", 20.0), 2)], "- Latency avg (s): build={build}, queue={queue}, worker={worker}, merge={merge}, targetMs={targetMs}"),
			self::tr("commands.report.content.async-resources", ["memoryUsage" => self::metricInt($metrics, "memoryUsageBytes"), "memoryPeak" => self::metricInt($metrics, "memoryPeakBytes"), "memoryLimit" => self::metricInt($metrics, "memoryLimitBytes"), "memoryUtilization" => self::formatPercent(self::metricFloat($metrics, "memoryUtilization")), "cpuLoad" => round(self::metricFloat($metrics, "cpuLoad"), 3), "asyncTps" => round(self::metricFloat($metrics, "tps"), 2)], "- Resources: mem={memoryUsage}/{memoryLimit} ({memoryUtilization}), peak={memoryPeak}, cpu={cpuLoad}, tps={asyncTps}"),
			self::tr("commands.report.content.async-guards", ["maxLag" => self::metricInt($metrics, "maxAllowedSequenceLag"), "maxAge" => round(self::metricFloat($metrics, "maxAllowedResultAgeSeconds"), 3), "timeout" => round(self::metricFloat($metrics, "workerTimeoutSeconds"), 3), "degradedUntil" => round(self::metricFloat($metrics, "degradedUntil"), 3), "lastDispatchAt" => round(self::metricFloat($metrics, "lastDispatchAt"), 3), "lastCompleteAt" => round(self::metricFloat($metrics, "lastCompleteAt"), 3), "lastHealthCheckAt" => round(self::metricFloat($metrics, "lastHealthCheckAt"), 3)], "- Guards: maxLag={maxLag}, maxAge={maxAge}s, timeout={timeout}s, degradedUntil={degradedUntil}, lastDispatch={lastDispatchAt}, lastComplete={lastCompleteAt}, lastHealth={lastHealthCheckAt}"),
			"",
			self::tr("commands.report.content.section-profiler", [], "Profiler"),
			self::tr("commands.report.content.profiler-metrics", ["metrics" => HotPathProfiler::getMetricCount()], "- Metrics: {metrics}"),
			self::tr("commands.report.content.profiler-total", ["totalMs" => round(HotPathProfiler::getTotalMillis(), 3)], "- Total ms: {totalMs}"),
			self::tr("commands.report.content.profiler-packet", ["packetAvgMs" => round(HotPathProfiler::getAverageMillis("packet.handler.receive"), 3)], "- Packet avg ms: {packetAvgMs}"),
			self::tr("commands.report.content.safe-exceptions", ["count" => ExceptionHandler::getErrorCount()], "- Safe exceptions (window): {count}"),
			"",
			self::tr("commands.report.content.section-config", [], "Configuration snapshot"),
			self::tr("commands.report.content.cfg-language", ["locale" => self::cfgString(ConfigPaths::LANGUAGE_LOCALE, "en_US"), "fallback" => self::cfgString(ConfigPaths::LANGUAGE_FALLBACK_LOCALE, "en_US")], "- Language: locale={locale}, fallback={fallback}"),
			self::tr("commands.report.content.cfg-alerts", ["enable" => self::formatBool(ConfigManager::getData(ConfigPaths::ALERTS_ENABLE, true)), "adminOnly" => self::formatBool(ConfigManager::getData(ConfigPaths::ALERTS_ADMIN, false)), "permission" => self::cfgString(ConfigPaths::ALERTS_PERMISSION, "zuri.admin")], "- Alerts: enable={enable}, adminOnly={adminOnly}, permission={permission}"),
			self::tr("commands.report.content.cfg-punishments", ["ban" => self::formatBool(ConfigManager::getData(ConfigPaths::BAN_ENABLE, true)), "kick" => self::formatBool(ConfigManager::getData(ConfigPaths::KICK_ENABLE, true)), "detection" => self::formatBool(ConfigManager::getData(ConfigPaths::DETECTION_ENABLE, false)), "warning" => self::formatBool(ConfigManager::getData(ConfigPaths::WARNING_ENABLE, true))], "- Actions: ban={ban}, kick={kick}, detection={detection}, warning={warning}"),
			self::tr("commands.report.content.cfg-debug", ["debug" => self::formatBool(ConfigManager::getData(ConfigPaths::DEBUG_ENABLE, false)), "logAdmin" => self::formatBool(ConfigManager::getData(ConfigPaths::DEBUG_LOG_ADMIN, true)), "logServer" => self::formatBool(ConfigManager::getData(ConfigPaths::DEBUG_LOG_SERVER, false))], "- Debug: enabled={debug}, logAdmin={logAdmin}, logServer={logServer}"),
			self::tr("commands.report.content.cfg-captcha", ["enable" => self::formatBool(ConfigManager::getData(ConfigPaths::CAPTCHA_ENABLE, true)), "message" => self::formatBool(ConfigManager::getData(ConfigPaths::CAPTCHA_MESSAGE, true)), "tip" => self::formatBool(ConfigManager::getData(ConfigPaths::CAPTCHA_TIP, false)), "title" => self::formatBool(ConfigManager::getData(ConfigPaths::CAPTCHA_TITLE, false)), "randomize" => self::formatBool(ConfigManager::getData(ConfigPaths::CAPTCHA_RANDOMIZE, false)), "length" => self::cfgInt(ConfigPaths::CAPTCHA_CODE_LENGTH, 5)], "- Captcha: enable={enable}, message={message}, tip={tip}, title={title}, randomize={randomize}, length={length}"),
			self::tr("commands.report.content.cfg-async", ["workers" => self::cfgInt("zuri.async.max-concurrent-workers", 4), "batchSize" => self::cfgInt("zuri.async.batch-size", 64), "targetMs" => self::cfgFloat("zuri.async.worker-target-ms", 20.0), "maxQueue" => self::cfgInt("zuri.async.max-queue-size", 2048), "timeout" => self::cfgFloat("zuri.async.worker-timeout-seconds", 3.0), "degraded" => self::cfgFloat("zuri.async.degraded-cooldown-seconds", 6.0)], "- Async cfg: workers={workers}, batch={batchSize}, targetMs={targetMs}, maxQueue={maxQueue}, timeout={timeout}, degradedCooldown={degraded}"),
			self::tr("commands.report.content.cfg-safety", ["confidence" => self::cfgFloat("zuri.confidence.threshold", 0.5), "dynamicThresholds" => self::formatBool(ConfigManager::getData("zuri.dynamic-thresholds.enable", true)), "correlation" => self::formatBool(ConfigManager::getData("zuri.correlation.enable", true)), "correlationWindow" => self::cfgFloat("zuri.correlation.window-seconds", 10.0), "correlationGroups" => self::cfgInt("zuri.correlation.required-groups", 3), "forceMultiplier" => self::cfgFloat("zuri.correlation.force-escalation-multiplier", 2.5), "forceExtraVl" => self::cfgInt("zuri.correlation.force-escalation-extra-real-vl", 3)], "- Safety cfg: confidence={confidence}, dynamicThresholds={dynamicThresholds}, correlation={correlation}, window={correlationWindow}, groups={correlationGroups}, forceMultiplier={forceMultiplier}, forceExtraVl={forceExtraVl}"),
			self::tr("commands.report.content.cfg-logging", ["enable" => self::formatBool(ConfigManager::getData("zuri.logging.enable", true)), "logsFolder" => self::cfgString("zuri.logging.folders.logs", "logs"), "crashesFolder" => self::cfgString("zuri.logging.folders.crashes", "crashes"), "retentionDays" => self::cfgInt("zuri.logging.cleanup.max-age-days", 30), "deleteBeforeDate" => self::cfgString("zuri.logging.cleanup.delete-before-date", "")], "- Logging: enable={enable}, folders={logsFolder}/{crashesFolder}, retentionDays={retentionDays}, deleteBeforeDate={deleteBeforeDate}"),
			self::tr("commands.report.content.cfg-log-channels", ["anticheat" => self::formatBool(ConfigManager::getData("zuri.logging.channels.anticheat.enable", true)), "punishment" => self::formatBool(ConfigManager::getData("zuri.logging.channels.punishment.enable", true)), "thread" => self::formatBool(ConfigManager::getData("zuri.logging.channels.thread.enable", true)), "crash" => self::formatBool(ConfigManager::getData("zuri.logging.channels.crash.enable", true)), "report" => self::formatBool(ConfigManager::getData("zuri.logging.channels.report.enable", true))], "- Log channels: anticheat={anticheat}, punishment={punishment}, thread={thread}, crash={crash}, report={report}"),
		];
		$recentCrashes = self::readRecentLines(
			self::crashesPath(self::fileName("crash", "crash.log")),
			15
		);
		$lines[] = "";
		$lines[] = self::tr("commands.report.content.recent-crashes-header", ["count" => 15], "Recent crashes (latest {count} lines):");
		if ($recentCrashes === []) {
			$lines[] = self::tr("commands.report.content.recent-crashes-none", [], "- none");
		} else {
			foreach ($recentCrashes as $crashLine) {
				$lines[] = self::tr("commands.report.content.recent-crashes-entry", ["line" => $crashLine], "- {line}");
			}
		}
		$recentAsyncThreadFailures = self::readRecentMatchingLines(
			self::logsPath(self::fileName("thread", "thread.log")),
			15,
			[
				"thread-error",
				"result-error",
				"fatal error",
				"uncaught error",
				"stack trace",
			]
		);
		$lines[] = "";
		$lines[] = self::tr("commands.report.content.recent-async-header", ["count" => 15], "Recent async thread failures (latest {count} lines):");
		if ($recentAsyncThreadFailures === []) {
			$lines[] = self::tr("commands.report.content.recent-async-none", [], "- none");
		} else {
			foreach ($recentAsyncThreadFailures as $threadLine) {
				$lines[] = self::tr("commands.report.content.recent-async-entry", ["line" => $threadLine], "- {line}");
			}
		}
		$file = self::logsPath(self::fileName("report", "report.txt"));
		self::appendLine($file, implode(PHP_EOL, $lines) . PHP_EOL . str_repeat("-", 72));
		return $file;
	}

	/**
	 * Initializes logging folders and crash shutdown handlers lazily.
	 */
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
			self::crash(self::tr(LangKeys::DEBUG_AUDIT_HARD_CRASH, [
				"message" => $message,
				"file" => $file,
				"line" => $line,
			], "Hard-crash captured: {message} at {file}:{line}"));
		});
	}

	/** @param array<string,string|int|float|bool> $details */
	/**
	 * Writes one chained audit line and mirrors it to the enabled channel.
	 *
	 * @param string $category Audit category.
	 * @param string $action Action label.
	 * @param string $actor Actor identifier.
	 * @param array<string,string|int|float|bool> $details Structured details.
	 */
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
		Server::getInstance()->getLogger()->debug(self::tr(LangKeys::DEBUG_AUDIT_LINE, [
			"category" => $category,
			"actor" => $actor,
			"action" => $action,
			"details" => $detailText,
			"chain" => $currentHash,
		], "[DEBUG] AUDIT ({category}) actor={actor}, action={action}{details} [chain={chain}]"));
		$line = self::timestamp() . " " . self::tr(LangKeys::DEBUG_AUDIT_FILE_LINE, [
			"category" => $category,
			"actor" => $actor,
			"action" => $action,
			"details" => $detailText,
			"chain" => $currentHash,
		], "category={category}, actor={actor}, action={action}{details}, chain={chain}");
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

	/**
	 * Returns a bracketed wall-clock timestamp prefix.
	 */
	private static function timestamp() : string {
		return "[" . date("Y-m-d H:i:s") . "]";
	}

	/**
	 * Resolves an absolute path inside configured logs folder.
	 *
	 * @param string $file File name.
	 */
	private static function logsPath(string $file) : string {
		$folderRaw = ConfigManager::getData("zuri.logging.folders.logs", "logs");
		$folder = is_string($folderRaw) && $folderRaw !== "" ? $folderRaw : "logs";
		return ZuriAC::getInstance()->getDataFolder() . $folder . DIRECTORY_SEPARATOR . $file;
	}

	/**
	 * Resolves an absolute path inside configured crashes folder.
	 *
	 * @param string $file File name.
	 */
	private static function crashesPath(string $file) : string {
		$folderRaw = ConfigManager::getData("zuri.logging.folders.crashes", "crashes");
		$folder = is_string($folderRaw) && $folderRaw !== "" ? $folderRaw : "crashes";
		return ZuriAC::getInstance()->getDataFolder() . $folder . DIRECTORY_SEPARATOR . $file;
	}

	/**
	 * Creates directory path recursively when it does not exist.
	 *
	 * @param string $path Directory path.
	 */
	private static function ensureDirectory(string $path) : void {
		if ($path === "") {
			return;
		}
		if (is_dir($path)) {
			return;
		}
		@mkdir($path, 0777, true);
	}

	/**
	 * Appends one sanitized log line to a file with locking.
	 *
	 * @param string $file Destination file path.
	 * @param string $line Raw line text.
	 */
	private static function appendLine(string $file, string $line) : void {
		$dir = pathinfo($file, PATHINFO_DIRNAME);
		if (is_string($dir) && $dir !== "") {
			self::ensureDirectory($dir);
		}
		$sanitized = self::stripFormattingCodes($line);
		@file_put_contents($file, $sanitized . PHP_EOL, FILE_APPEND | LOCK_EX);
	}

	/**
	 * Removes Minecraft formatting codes from log text.
	 *
	 * @param string $text Raw formatted text.
	 */
	private static function stripFormattingCodes(string $text) : string {
		// Strip Bedrock color/style codes like §a, &c, and hex forms.
		$text = (string) preg_replace('/(?:§|&)x(?:(?:§|&)[0-9A-Fa-f]){6}/', '', $text);
		$text = (string) preg_replace('/(?:§|&)[0-9A-FK-ORa-fk-or]/', '', $text);
		return trim($text);
	}

	/** @param array<string,int|float|bool> $metrics */
	/**
	 * Reads integer-like metric values from mixed metrics map.
	 *
	 * @param array<string,int|float|bool> $metrics Metrics map.
	 * @param string $key Metric key.
	 * @param int $default Default value.
	 */
	private static function metricInt(array $metrics, string $key, int $default = 0) : int {
		$value = $metrics[$key] ?? $default;
		if (is_int($value)) {
			return $value;
		}
		if (is_numeric($value)) {
			return (int) $value;
		}
		return $default;
	}

	/** @param array<string,int|float|bool> $metrics */
	/**
	 * Reads float-like metric values from mixed metrics map.
	 *
	 * @param array<string,int|float|bool> $metrics Metrics map.
	 * @param string $key Metric key.
	 * @param float $default Default value.
	 */
	private static function metricFloat(array $metrics, string $key, float $default = 0.0) : float {
		$value = $metrics[$key] ?? $default;
		if (is_float($value)) {
			return $value;
		}
		if (is_int($value)) {
			return (float) $value;
		}
		return $default;
	}

	/**
	 * Formats boolean values using localized true/false labels.
	 *
	 * @param mixed $value Value to format.
	 */
	private static function formatBool(mixed $value) : string {
		if (is_bool($value)) {
			return self::tr(
				$value ? "commands.report.content.bool-true" : "commands.report.content.bool-false",
				[],
				$value ? "true" : "false"
			);
		}
		return self::tr("commands.report.content.bool-false", [], "false");
	}

	/**
	 * Formats a decimal ratio as percentage text.
	 *
	 * @param float $value Ratio value from 0..1.
	 */
	private static function formatPercent(float $value) : string {
		return round($value * 100.0, 2) . "%";
	}

	/**
	 * Reads config value as string.
	 *
	 * @param string $path Config path.
	 * @param string $default Default value.
	 */
	private static function cfgString(string $path, string $default) : string {
		$value = ConfigManager::getData($path, $default);
		return is_string($value) ? $value : $default;
	}

	/**
	 * Reads config value as integer with numeric coercion.
	 *
	 * @param string $path Config path.
	 * @param int $default Default value.
	 */
	private static function cfgInt(string $path, int $default) : int {
		$value = ConfigManager::getData($path, $default);
		if (is_int($value)) {
			return $value;
		}
		return is_numeric($value) ? (int) $value : $default;
	}

	/**
	 * Reads config value as float with numeric coercion.
	 *
	 * @param string $path Config path.
	 * @param float $default Default value.
	 */
	private static function cfgFloat(string $path, float $default) : float {
		$value = ConfigManager::getData($path, $default);
		if (is_float($value)) {
			return $value;
		}
		if (is_int($value) || is_numeric($value)) {
			return (float) $value;
		}
		return $default;
	}

	/** @param array<string,string|int|float> $replacements */
	/**
	 * Localized translation shorthand for report and audit strings.
	 *
	 * @param string $key Translation key.
	 * @param array<string,string|int|float> $replacements Placeholder replacements.
	 * @param string $default Default message.
	 */
	private static function tr(string $key, array $replacements = [], string $default = "") : string {
		return Lang::get($key, $replacements, $default);
	}

	/**
	 * Resolves whether a logging bucket is enabled globally and per-channel.
	 *
	 * @param string $bucket Channel bucket name.
	 */
	private static function isEnabled(string $bucket) : bool {
		$globalRaw = ConfigManager::getData("zuri.logging.enable", true);
		$global = !is_bool($globalRaw) || $globalRaw;
		if (!$global) {
			return false;
		}
		$bucketRaw = ConfigManager::getData("zuri.logging.channels.{$bucket}.enable", true);
		return !is_bool($bucketRaw) || $bucketRaw;
	}

	/**
	 * Resolves configured file name for a logging bucket.
	 *
	 * @param string $bucket Channel bucket name.
	 * @param string $fallback Default file name.
	 */
	private static function fileName(string $bucket, string $fallback) : string {
		$fileRaw = ConfigManager::getData("zuri.logging.channels.{$bucket}.file", $fallback);
		return is_string($fileRaw) && $fileRaw !== "" ? $fileRaw : $fallback;
	}

	/**
	 * Deletes aged log files based on cleanup retention settings.
	 */
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

	/** @return list<string> */
	/**
	 * Returns recent non-empty lines from a log file tail.
	 *
	 * @param string $filePath File path to read.
	 * @param int $maxLines Maximum tail lines to inspect.
	 * @return list<string>
	 */
	private static function readRecentLines(string $filePath, int $maxLines) : array {
		if ($maxLines <= 0 || !file_exists($filePath) || !is_readable($filePath)) {
			return [];
		}
		$rawLines = file($filePath, FILE_IGNORE_NEW_LINES);
		if (!is_array($rawLines) || $rawLines === []) {
			return [];
		}
		$tail = array_slice($rawLines, -$maxLines);
		$result = [];
		foreach ($tail as $line) {
			if (is_string($line) && trim($line) !== "") {
				$result[] = $line;
			}
		}
		return $result;
	}

	/**
	 * Returns recent lines from a log file that match any of the given case-insensitive tokens.
	 *
	 * @param string $filePath File path to read.
	 * @param int $maxLines Maximum matched lines to return.
	 * @param list<string> $needles Match tokens.
	 * @return list<string>
	 */
	private static function readRecentMatchingLines(string $filePath, int $maxLines, array $needles) : array {
		if ($maxLines <= 0 || !file_exists($filePath) || !is_readable($filePath) || $needles === []) {
			return [];
		}
		$rawLines = file($filePath, FILE_IGNORE_NEW_LINES);
		if (!is_array($rawLines) || $rawLines === []) {
			return [];
		}
		$result = [];
		foreach ($rawLines as $line) {
			if (!is_string($line)) {
				continue;
			}
			$trimmed = trim($line);
			if ($trimmed === "") {
				continue;
			}
			foreach ($needles as $needle) {
				if ($needle !== "" && stripos($trimmed, $needle) !== false) {
					$result[] = $trimmed;
					break;
				}
			}
		}
		return array_slice($result, -$maxLines);
	}
}
