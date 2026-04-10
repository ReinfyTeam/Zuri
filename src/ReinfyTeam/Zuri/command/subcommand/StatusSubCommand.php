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

namespace ReinfyTeam\Zuri\command\subcommand;

use CortexPE\Commando\BaseSubCommand;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use ReinfyTeam\Zuri\checks\DynamicThreshold;
use ReinfyTeam\Zuri\lang\Lang;
use ReinfyTeam\Zuri\lang\LangKeys;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\task\CheckAsyncTask;
use ReinfyTeam\Zuri\utils\AuditLogger;
use ReinfyTeam\Zuri\utils\HotPathProfiler;
use function count;
use function implode;
use function max;
use function round;

class StatusSubCommand extends BaseSubCommand {
	public function __construct(PluginBase $plugin) {
		parent::__construct($plugin, "status", "Show async pipeline status.", ["async"]);
	}

	protected function prepare() : void {
	}

	/** @param array<string,mixed> $args */
	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void {
		AuditLogger::command($sender, "zuri status");
		$metrics = CheckAsyncTask::getMetrics();
		$server = Server::getInstance();
		$onlinePlayers = count($server->getOnlinePlayers());
		$maxPlayers = $server->getMaxPlayers();
		$monitoredPlayers = count(PlayerAPI::$players);
		$tps = DynamicThreshold::getTps();
		$loadFactor = DynamicThreshold::getLoadFactor();
		$performanceState = DynamicThreshold::isServerLagging() ? "lagging" : (DynamicThreshold::isServerStressed() ? "stressed" : "stable");
		$queueUtil = round((float) ($metrics["queueUtilization"] ?? 0.0) * 100.0, 1);
		$workerUtil = round((float) ($metrics["workerUtilization"] ?? 0.0) * 100.0, 1);
		$memoryUtil = round((float) ($metrics["memoryUtilization"] ?? 0.0) * 100.0, 1);
		$cpuLoad = round((float) ($metrics["cpuLoad"] ?? 0.0), 2);
		$asyncTps = round((float) ($metrics["tps"] ?? 20.0), 2);
		$loadPercent = round($loadFactor * 100.0, 1);
		$profileMetricCount = HotPathProfiler::getMetricCount();
		$profileTotalMs = round(HotPathProfiler::getTotalMillis(), 3);
		$packetAvgMs = round(HotPathProfiler::getAverageMillis("packet.handler.receive"), 3);
		$stateColor = match ($performanceState) {
			"lagging" => TextFormat::RED,
			"stressed" => TextFormat::YELLOW,
			default => TextFormat::GREEN,
		};
		$performanceStateText = $stateColor . $performanceState . TextFormat::RESET;
		$overloadActive = (bool) ($metrics["overloadActive"] ?? false);
		$overloadActiveText = $overloadActive ? TextFormat::RED . "yes" . TextFormat::RESET : TextFormat::GREEN . "no" . TextFormat::RESET;
		$syncFallbackActive = (bool) ($metrics["syncFallbackActive"] ?? false);
		$syncFallbackActiveText = $syncFallbackActive ? TextFormat::YELLOW . "yes" . TextFormat::RESET : TextFormat::GREEN . "no" . TextFormat::RESET;
		$queueUtilText = ($queueUtil >= 85.0 ? TextFormat::RED : ($queueUtil >= 65.0 ? TextFormat::YELLOW : TextFormat::GREEN)) . max(0.0, $queueUtil) . "%" . TextFormat::RESET;
		$workerUtilText = ($workerUtil >= 85.0 ? TextFormat::RED : ($workerUtil >= 65.0 ? TextFormat::YELLOW : TextFormat::GREEN)) . max(0.0, $workerUtil) . "%" . TextFormat::RESET;
		$loadText = ($loadPercent >= 85.0 ? TextFormat::RED : ($loadPercent >= 65.0 ? TextFormat::YELLOW : TextFormat::GREEN)) . max(0.0, $loadPercent) . "%" . TextFormat::RESET;
		$memoryUtilText = ($memoryUtil >= 85.0 ? TextFormat::RED : ($memoryUtil >= 65.0 ? TextFormat::YELLOW : TextFormat::GREEN)) . max(0.0, $memoryUtil) . "%" . TextFormat::RESET;
		$cpuLoadText = ($cpuLoad >= 85.0 ? TextFormat::RED : ($cpuLoad >= 65.0 ? TextFormat::YELLOW : TextFormat::GREEN)) . max(0.0, $cpuLoad) . "%" . TextFormat::RESET;
		$asyncTpsText = (($asyncTps <= 12.0) ? TextFormat::RED : (($asyncTps <= 17.5) ? TextFormat::YELLOW : TextFormat::GREEN)) . max(0.0, $asyncTps) . TextFormat::RESET;

		$lines = [
			Lang::get(LangKeys::ASYNC_STATUS_HEADER),
			Lang::get(LangKeys::ASYNC_STATUS_QUEUE, ["queue" => (string) $metrics["queueSize"], "maxQueue" => (string) $metrics["maxQueueSize"]]),
			Lang::get(LangKeys::ASYNC_STATUS_WORKERS, ["inFlight" => (string) $metrics["inFlight"], "maxWorkers" => (string) $metrics["maxConcurrentWorkers"]]),
			Lang::get(LangKeys::ASYNC_STATUS_UTILIZATION, [
				"queueUtil" => $queueUtilText,
				"workerUtil" => $workerUtilText,
			]),
			Lang::get(LangKeys::ASYNC_STATUS_PLAYERS, [
				"monitored" => (string) $monitoredPlayers,
				"online" => (string) $onlinePlayers,
				"maxPlayers" => (string) $maxPlayers,
			]),
			Lang::get(LangKeys::ASYNC_STATUS_SERVER, [
				"tps" => (string) round($tps, 2),
				"load" => $loadText,
			]),
			Lang::get(LangKeys::ASYNC_STATUS_PERFORMANCE, ["state" => $performanceStateText]),
			Lang::get(LangKeys::ASYNC_STATUS_RESOURCES, [
				"memory" => $memoryUtilText,
				"cpu" => $cpuLoadText,
				"asyncTps" => $asyncTpsText,
			]),
			Lang::get(LangKeys::ASYNC_STATUS_OVERLOAD, [
				"active" => $overloadActiveText,
				"alerts" => (string) ($metrics["totalOverloadAlerts"] ?? 0),
			]),
			Lang::get(LangKeys::ASYNC_STATUS_PROFILE, [
				"metrics" => (string) $profileMetricCount,
				"totalMs" => (string) $profileTotalMs,
				"packetAvgMs" => (string) $packetAvgMs,
			]),
			Lang::get(LangKeys::ASYNC_STATUS_TOTALS, [
				"dispatched" => (string) $metrics["totalDispatched"],
				"completed" => (string) $metrics["totalCompleted"],
				"dropped" => (string) $metrics["totalDropped"],
			]),
			Lang::get(LangKeys::ASYNC_STATUS_HEALTH, [
				"stuck" => (string) $metrics["totalRecoveredStuck"],
				"restarts" => (string) $metrics["totalAutoRestarts"],
				"late" => (string) $metrics["totalLateCompletions"],
				"timeout" => (string) round((float) $metrics["workerTimeoutSeconds"], 2),
			]),
			Lang::get(LangKeys::ASYNC_STATUS_THREADS, [
				"errors" => (string) ($metrics["totalThreadErrors"] ?? 0),
				"resultErrors" => (string) ($metrics["totalThreadResultErrors"] ?? 0),
				"retries" => (string) ($metrics["totalThreadRetries"] ?? 0),
			]),
			Lang::get(LangKeys::ASYNC_STATUS_FALLBACK, [
				"active" => $syncFallbackActiveText,
				"count" => (string) $metrics["totalSyncFallback"],
				"errors" => (string) $metrics["totalFallbackErrors"],
			]),
			Lang::get(LangKeys::ASYNC_STATUS_LATENCY, [
				"build" => (string) round((float) $metrics["avgBuildDelay"], 4),
				"queue" => (string) round((float) $metrics["avgQueueWait"], 4),
				"worker" => (string) round((float) $metrics["avgWorkerTime"], 4),
				"merge" => (string) round((float) $metrics["avgMergeTime"], 4),
			]),
			Lang::get(LangKeys::ASYNC_STATUS_AVG, ["avg" => (string) round((float) $metrics["avgWorkerTime"], 4)]),
		];

		$sender->sendMessage(implode("\n", $lines));
	}
}
