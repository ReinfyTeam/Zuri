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
use ReinfyTeam\Zuri\lang\Lang;
use ReinfyTeam\Zuri\lang\LangKeys;
use ReinfyTeam\Zuri\task\CheckAsyncTask;
use function implode;
use function round;

class AsyncStatusSubCommand extends BaseSubCommand {
	public function __construct(PluginBase $plugin) {
		parent::__construct($plugin, "async", "Show async pipeline status.");
	}

	protected function prepare() : void {
	}

	/** @param array<string,mixed> $args */
	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void {
		$metrics = CheckAsyncTask::getMetrics();

		$lines = [
			Lang::get(LangKeys::ASYNC_STATUS_HEADER),
			Lang::get(LangKeys::ASYNC_STATUS_QUEUE, ["queue" => (string) $metrics["queueSize"], "maxQueue" => (string) $metrics["maxQueueSize"]]),
			Lang::get(LangKeys::ASYNC_STATUS_WORKERS, ["inFlight" => (string) $metrics["inFlight"], "maxWorkers" => (string) $metrics["maxConcurrentWorkers"]]),
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
			Lang::get(LangKeys::ASYNC_STATUS_FALLBACK, [
				"active" => ((bool) $metrics["syncFallbackActive"]) ? "yes" : "no",
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
