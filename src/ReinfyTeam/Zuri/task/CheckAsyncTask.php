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

namespace ReinfyTeam\Zuri\task;

use pocketmine\Server;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use Throwable;
use vennv\vapm\ClosureThread;
use function array_slice;
use function count;
use function function_exists;
use function ini_get;
use function is_array;
use function is_numeric;
use function is_string;
use function json_decode;
use function max;
use function memory_get_peak_usage;
use function memory_get_usage;
use function method_exists;
use function microtime;
use function min;
use function rtrim;
use function strtoupper;
use function substr;
use function sys_getloadavg;

class CheckAsyncTask {
	/** @var list<array{0:string,1:string,2:array<string,mixed>,3:int,4:float,5:float,6:int,7:string}> */
	private static array $queue = [];
	private static int $queueHead = 0;
	/** @var array<string,int> */
	private static array $pendingTaskIndexesByKey = [];
	private static int $inFlight = 0;
	private static int $maxConcurrentWorkers = 1;
	private static int $configuredMaxConcurrentWorkers = 1;
	private static int $minConcurrentWorkers = 1;
	private static int $batchSize = 16;
	private static int $configuredBatchSize = 16;
	private static int $maxQueueSize = 2048;
	private static float $workerTimeoutSeconds = 3.0;
	private static float $degradedCooldownSeconds = 6.0;
	private static float $degradedUntil = 0.0;
	private static float $lastQueueOptimizationAt = 0.0;
	private static float $queueOptimizationIntervalSeconds = 2.0;

	/** @var array<string,array{startedAt:float,queuedAt:float,capturedAt:float,checkClass:string,playerName:string,payload:array<string,mixed>,sequence:int,attempt:int,batchId:string,dedupeKey:string}> */
	private static array $activeTasks = [];
	/** @var array<string,array{startedAt:float,taskIds:list<string>}> */
	private static array $activeBatches = [];

	private static int $totalDispatched = 0;
	private static int $totalCompleted = 0;
	private static int $totalDropped = 0;
	private static int $totalCoalesced = 0;
	private static int $totalRecoveredStuck = 0;
	private static int $totalAutoRestarts = 0;
	private static int $totalLateCompletions = 0;
	private static int $totalSyncFallback = 0;
	private static int $totalFallbackErrors = 0;
	private static float $lastDispatchAt = 0.0;
	private static float $lastCompleteAt = 0.0;
	private static float $lastHealthCheckAt = 0.0;
	private static float $totalWorkerTime = 0.0;
	private static float $totalQueueWaitTime = 0.0;
	private static float $totalBuildDelayTime = 0.0;
	private static float $totalMergeTime = 0.0;
	private static int $totalQueueOptimizations = 0;
	private static int $totalWorkerScaleUps = 0;
	private static int $totalWorkerScaleDowns = 0;
	private static int $totalOverloadAlerts = 0;
	private static int $totalThreadErrors = 0;
	private static int $totalThreadResultErrors = 0;
	private static int $totalThreadRetries = 0;
	private static int $maxAllowedSequenceLag = 4;
	private static float $maxAllowedResultAgeSeconds = 0.75;
	private static bool $overloadActive = false;
	private static float $lastOverloadAlertAt = 0.0;
	private static float $overloadAlertCooldownSeconds = 10.0;

	/** @return array<string,int|float|bool> */
	public static function getMetrics() : array {
		$now = microtime(true);
		self::runHealthCheck($now);
		self::monitorResourcePressure($now);

		$completed = self::$totalCompleted > 0 ? self::$totalCompleted : 1;
		$pendingQueue = self::pendingQueueSize();
		$queueUtilization = self::$maxQueueSize > 0 ? ($pendingQueue / self::$maxQueueSize) : 0.0;
		$workerUtilization = self::$maxConcurrentWorkers > 0 ? (self::$inFlight / self::$maxConcurrentWorkers) : 0.0;
		$memoryLimit = self::parseMemoryLimitBytes();
		$memoryUsage = memory_get_usage(true);
		$memoryPeak = memory_get_peak_usage(true);
		$memoryUtilization = $memoryLimit > 0 ? ($memoryUsage / $memoryLimit) : 0.0;
		$cpuLoad = function_exists("sys_getloadavg") ? (sys_getloadavg()[0] ?? 0.0) : 0.0;
		return [
			"queueSize" => $pendingQueue,
			"inFlight" => self::$inFlight,
			"maxConcurrentWorkers" => self::$maxConcurrentWorkers,
			"minConcurrentWorkers" => self::$minConcurrentWorkers,
			"batchSize" => self::$batchSize,
			"maxQueueSize" => self::$maxQueueSize,
			"workerTimeoutSeconds" => self::$workerTimeoutSeconds,
			"totalDispatched" => self::$totalDispatched,
			"totalCompleted" => self::$totalCompleted,
			"totalDropped" => self::$totalDropped,
			"totalCoalesced" => self::$totalCoalesced,
			"totalRecoveredStuck" => self::$totalRecoveredStuck,
			"totalAutoRestarts" => self::$totalAutoRestarts,
			"totalLateCompletions" => self::$totalLateCompletions,
			"totalSyncFallback" => self::$totalSyncFallback,
			"totalFallbackErrors" => self::$totalFallbackErrors,
			"totalQueueOptimizations" => self::$totalQueueOptimizations,
			"totalWorkerScaleUps" => self::$totalWorkerScaleUps,
			"totalWorkerScaleDowns" => self::$totalWorkerScaleDowns,
			"totalOverloadAlerts" => self::$totalOverloadAlerts,
			"totalThreadErrors" => self::$totalThreadErrors,
			"totalThreadResultErrors" => self::$totalThreadResultErrors,
			"totalThreadRetries" => self::$totalThreadRetries,
			"maxAllowedSequenceLag" => self::$maxAllowedSequenceLag,
			"maxAllowedResultAgeSeconds" => self::$maxAllowedResultAgeSeconds,
			"lastDispatchAt" => self::$lastDispatchAt,
			"lastCompleteAt" => self::$lastCompleteAt,
			"lastHealthCheckAt" => self::$lastHealthCheckAt,
			"syncFallbackActive" => self::isSyncFallbackActive($now),
			"degradedUntil" => self::$degradedUntil,
			"queueUtilization" => $queueUtilization,
			"workerUtilization" => $workerUtilization,
			"memoryUsageBytes" => $memoryUsage,
			"memoryPeakBytes" => $memoryPeak,
			"memoryLimitBytes" => $memoryLimit,
			"memoryUtilization" => $memoryUtilization,
			"cpuLoad" => (float) $cpuLoad,
			"tps" => Server::getInstance()->getTicksPerSecond(),
			"overloadActive" => self::$overloadActive,
			"avgBuildDelay" => self::$totalBuildDelayTime / $completed,
			"avgQueueWait" => self::$totalQueueWaitTime / $completed,
			"avgWorkerTime" => self::$totalWorkerTime / $completed,
			"avgMergeTime" => self::$totalMergeTime / $completed,
		];
	}

	public static function configure(int $maxConcurrentWorkers, int $maxQueueSize, float $workerTimeoutSeconds = 3.0, float $degradedCooldownSeconds = 6.0, int $batchSize = 16) : void {
		self::$configuredMaxConcurrentWorkers = max(1, min(16, $maxConcurrentWorkers));
		self::$maxConcurrentWorkers = self::$configuredMaxConcurrentWorkers;
		self::$minConcurrentWorkers = 1;
		self::$configuredBatchSize = max(1, min(128, $batchSize));
		self::$batchSize = self::$configuredBatchSize;
		self::$maxQueueSize = $maxQueueSize > 0 ? $maxQueueSize : 1;
		self::$workerTimeoutSeconds = $workerTimeoutSeconds > 0.1 ? $workerTimeoutSeconds : 0.1;
		self::$degradedCooldownSeconds = $degradedCooldownSeconds > 0.1 ? $degradedCooldownSeconds : 0.1;
	}

	/** @param array<string,mixed> $payload */
	public static function dispatch(string $checkClass, string $playerName, array $payload, int $sequence) : void {
		$now = microtime(true);
		self::runHealthCheck($now);
		self::optimizeQueueIfNeeded($now);
		self::monitorResourcePressure($now);

		if (self::isSyncFallbackActive($now)) {
			self::executeSyncFallback($checkClass, $playerName, $payload, $sequence);
			return;
		}

		$dedupeKey = $checkClass . ":" . $playerName;
		$replaceIndex = self::$pendingTaskIndexesByKey[$dedupeKey] ?? -1;
		if ($replaceIndex >= self::$queueHead) {
			$existing = self::$queue[$replaceIndex] ?? null;
			if (is_array($existing)) {
				$queuedAt = $now;
				$capturedAtValue = $payload["captureTime"] ?? $queuedAt;
				$capturedAt = is_numeric($capturedAtValue) ? (float) $capturedAtValue : $queuedAt;
				self::$queue[$replaceIndex] = [$checkClass, $playerName, $payload, $sequence, $queuedAt, $capturedAt, 0, $dedupeKey];
				self::$totalCoalesced++;
				self::$lastDispatchAt = $queuedAt;
				self::drain();
				return;
			}
		}

		if (self::pendingQueueSize() >= self::$maxQueueSize) {
			// Drop oldest pending task to keep newest snapshots flowing and avoid sync fallback lag.
			if (self::$queueHead < count(self::$queue)) {
				$dropped = self::$queue[self::$queueHead] ?? null;
				if (is_array($dropped)) {
					$droppedKey = $dropped[7];
					if ($droppedKey !== "" && (self::$pendingTaskIndexesByKey[$droppedKey] ?? -1) === self::$queueHead) {
						unset(self::$pendingTaskIndexesByKey[$droppedKey]);
					}
				}
				self::$queueHead++;
			}
			self::$totalDropped++;
			self::compactQueueIfNeeded();
		}

		$queuedAt = $now;
		$capturedAtValue = $payload["captureTime"] ?? $queuedAt;
		$capturedAt = is_numeric($capturedAtValue) ? (float) $capturedAtValue : $queuedAt;
		$queueIndex = count(self::$queue);
		self::$queue[] = [$checkClass, $playerName, $payload, $sequence, $queuedAt, $capturedAt, 0, $dedupeKey];
		self::$pendingTaskIndexesByKey[$dedupeKey] = $queueIndex;
		self::$lastDispatchAt = $queuedAt;
		self::drain();
	}

	private static function drain() : void {
		self::runHealthCheck(microtime(true));

		while (self::$inFlight < self::$maxConcurrentWorkers && self::pendingQueueSize() > 0) {
			$pendingQueue = self::pendingQueueSize();
			$targetBatchSize = self::$batchSize;
			if ($pendingQueue >= ($targetBatchSize * 8)) {
				$targetBatchSize = min(256, $targetBatchSize * 8);
			} elseif ($pendingQueue >= ($targetBatchSize * 4)) {
				$targetBatchSize = min(256, $targetBatchSize * 4);
			} elseif ($pendingQueue >= ($targetBatchSize * 2)) {
				$targetBatchSize = min(256, $targetBatchSize * 2);
			}

			$batch = [];
			while (count($batch) < $targetBatchSize) {
				$currentIndex = self::$queueHead;
				$next = self::$queue[$currentIndex] ?? null;
				if (!is_array($next)) {
					break;
				}
				self::$queueHead++;
				$key = $next[7];
				if ($key !== "" && (self::$pendingTaskIndexesByKey[$key] ?? -1) === $currentIndex) {
					unset(self::$pendingTaskIndexesByKey[$key]);
				}
				$batch[] = $next;
			}
			if ($batch !== []) {
				self::startBatch($batch);
			}
		}
		self::compactQueueIfNeeded();
	}

	/**
	 * @param list<array{0:string,1:string,2:array<string,mixed>,3:int,4:float,5:float,6:int,7:string}> $batch
	 */
	private static function startBatch(array $batch) : void {
		$startedAt = microtime(true);
		$batchId = "batch:" . self::$totalDispatched . ":" . $startedAt;
		$batchPayload = [];
		$taskIds = [];
		foreach ($batch as [$checkClass, $playerName, $payload, $sequence, $queuedAt, $capturedAt, $attempt, $dedupeKey]) {
			$id = self::taskId($checkClass, $playerName, $sequence, $attempt);
			$taskIds[] = $id;
			$batchPayload[] = [
				"id" => $id,
				"checkClass" => $checkClass,
				"payload" => $payload,
			];
			self::$activeTasks[$id] = [
				"startedAt" => $startedAt,
				"queuedAt" => $queuedAt,
				"capturedAt" => $capturedAt,
				"checkClass" => $checkClass,
				"playerName" => $playerName,
				"payload" => $payload,
				"sequence" => $sequence,
				"attempt" => $attempt,
				"batchId" => $batchId,
				"dedupeKey" => $dedupeKey,
			];
			self::$totalQueueWaitTime += max(0.0, $startedAt - $queuedAt);
			self::$totalBuildDelayTime += max(0.0, $queuedAt - $capturedAt);
		}
		self::$activeBatches[$batchId] = [
			"startedAt" => $startedAt,
			"taskIds" => $taskIds,
		];
		self::$inFlight++;
		self::$totalDispatched += count($taskIds);

		$thread = new ClosureThread(
			static function (array $tasks) : array {
				$results = [];
				/** @var array<string,list<array{id:string,payload:array<string,mixed>}>> $grouped */
				$grouped = [];
				foreach ($tasks as $task) {
					$checkClass = is_string($task["checkClass"] ?? null) ? $task["checkClass"] : "";
					$id = is_string($task["id"] ?? null) ? $task["id"] : "";
					$payload = is_array($task["payload"] ?? null) ? $task["payload"] : [];
					if ($checkClass === "" || $id === "") {
						continue;
					}
					$grouped[$checkClass][] = [
						"id" => $id,
						"payload" => $payload,
					];
				}

				foreach ($grouped as $checkClass => $entries) {
					if (method_exists($checkClass, "evaluateAsyncBatch")) {
						try {
							$batchResults = $checkClass::evaluateAsyncBatch($entries);
							foreach ($entries as $entry) {
								$id = $entry["id"];
								$result = $batchResults[$id] ?? ["error" => "Missing batch result"];
								$results[$id] = is_array($result) ? $result : ["error" => "Invalid batch result"];
							}
							continue;
						} catch (Throwable $throwable) {
							foreach ($entries as $entry) {
								$results[$entry["id"]] = ["error" => $throwable->getMessage()];
							}
							continue;
						}
					}

					if (!method_exists($checkClass, "evaluateAsync")) {
						foreach ($entries as $entry) {
							$results[$entry["id"]] = ["error" => "Missing evaluateAsync()"];
						}
						continue;
					}

					foreach ($entries as $entry) {
						try {
							$results[$entry["id"]] = $checkClass::evaluateAsync($entry["payload"]);
						} catch (Throwable $throwable) {
							$results[$entry["id"]] = ["error" => $throwable->getMessage()];
						}
					}
				}
				return ["results" => $results];
			},
			[$batchPayload]
		);
		$thread->start()
			->then(function(mixed $output) use ($batchId) : mixed {
				self::handleBatchCompletion($batchId, $output);
				return $output;
			})
			->catch(function(mixed $reason) use ($batchId) : mixed {
				self::handleBatchCompletion($batchId, null, $reason);
				return $reason;
			});
	}

	private static function handleBatchCompletion(string $batchId, mixed $output, mixed $reason = null) : void {
		$batchMeta = self::$activeBatches[$batchId] ?? null;
		if (!is_array($batchMeta)) {
			self::$totalLateCompletions++;
			return;
		}
		unset(self::$activeBatches[$batchId]);
		self::$inFlight = self::$inFlight > 0 ? self::$inFlight - 1 : 0;
		self::$lastCompleteAt = microtime(true);
		$batchDuration = max(0.0, self::$lastCompleteAt - $batchMeta["startedAt"]);

		self::drain();

		if ($reason !== null) {
			foreach ($batchMeta["taskIds"] as $id) {
				$taskMeta = self::$activeTasks[$id] ?? null;
				if (!is_array($taskMeta)) {
					self::$totalLateCompletions++;
					continue;
				}
				unset(self::$activeTasks[$id]);
				self::$totalCompleted++;
				self::$totalWorkerTime += $batchDuration;
				self::$totalThreadErrors++;
				if (self::retryTask($taskMeta)) {
					continue;
				}
				self::executeSyncFallback($taskMeta["checkClass"], $taskMeta["playerName"], $taskMeta["payload"], $taskMeta["sequence"]);
			}
			self::activateDegradedMode(self::$lastCompleteAt);
			self::drain();
			return;
		}

		$payload = is_string($output) ? json_decode($output, true) : null;
		$results = is_array($payload) && is_array($payload["results"] ?? null) ? $payload["results"] : [];

		foreach ($batchMeta["taskIds"] as $id) {
			$taskMeta = self::$activeTasks[$id] ?? null;
			if (!is_array($taskMeta)) {
				self::$totalLateCompletions++;
				continue;
			}
			unset(self::$activeTasks[$id]);
			self::$totalCompleted++;
			self::$totalWorkerTime += $batchDuration;

			$result = $results[$id] ?? null;
			if (!is_array($result) || isset($result["error"])) {
				self::$totalThreadResultErrors++;
				if (self::retryTask($taskMeta)) {
					continue;
				}

				self::executeSyncFallback($taskMeta["checkClass"], $taskMeta["playerName"], $taskMeta["payload"], $taskMeta["sequence"]);
				continue;
			}

			$player = Server::getInstance()->getPlayerExact($taskMeta["playerName"]);
			if ($player === null || !$player->isOnline() || !$player->isConnected()) {
				continue;
			}

			$playerAPI = PlayerAPI::getAPIPlayer($player);
			if (!self::canMergeResult($playerAPI, $taskMeta["checkClass"], $taskMeta["sequence"], $taskMeta["queuedAt"])) {
				continue;
			}

			$checkClass = $taskMeta["checkClass"];
			/** @var Check $check */
			$check = new $checkClass();
			$mergeStartedAt = microtime(true);
			self::applyResult($check, $playerAPI, $result);
			self::$totalMergeTime += max(0.0, microtime(true) - $mergeStartedAt);
		}

		self::drain();
	}

	/**
	 * @param array{startedAt:float,queuedAt:float,capturedAt:float,checkClass:string,playerName:string,payload:array<string,mixed>,sequence:int,attempt:int,batchId:string,dedupeKey:string} $taskMeta
	 */
	private static function retryTask(array $taskMeta) : bool {
		if ($taskMeta["attempt"] >= 1) {
			return false;
		}

		self::$queue[] = [
			$taskMeta["checkClass"],
			$taskMeta["playerName"],
			$taskMeta["payload"],
			$taskMeta["sequence"],
			microtime(true),
			$taskMeta["capturedAt"],
			$taskMeta["attempt"] + 1,
			$taskMeta["dedupeKey"],
		];
		self::$pendingTaskIndexesByKey[$taskMeta["dedupeKey"]] = count(self::$queue) - 1;
		self::$totalThreadRetries++;
		return true;
	}

	/** @param array<string,mixed> $payload */
	private static function executeSyncFallback(string $checkClass, string $playerName, array $payload, int $sequence) : void {
		self::$totalSyncFallback++;

		try {
			if (!method_exists($checkClass, "evaluateAsync")) {
				self::$totalFallbackErrors++;
				return;
			}

			$result = $checkClass::evaluateAsync($payload);
			if (!is_array($result) || isset($result["error"])) {
				self::$totalFallbackErrors++;
				return;
			}

			$player = Server::getInstance()->getPlayerExact($playerName);
			if ($player === null || !$player->isOnline() || !$player->isConnected()) {
				return;
			}

			$playerAPI = PlayerAPI::getAPIPlayer($player);
			$queuedAtValue = $payload["captureTime"] ?? microtime(true);
			$queuedAt = is_numeric($queuedAtValue) ? (float) $queuedAtValue : microtime(true);
			if (!self::canMergeResult($playerAPI, $checkClass, $sequence, $queuedAt)) {
				return;
			}

			/** @var Check $check */
			$check = new $checkClass();
			$mergeStartedAt = microtime(true);
			self::applyResult($check, $playerAPI, $result);
			self::$totalMergeTime += max(0.0, microtime(true) - $mergeStartedAt);
		} catch (Throwable) {
			self::$totalFallbackErrors++;
		}
	}

	/**
	 * Dynamically optimize worker count based on queue load and performance metrics.
	 * Scales up workers when queue is filling, scales down when underutilized.
	 */
	private static function optimizeQueueIfNeeded(float $now) : void {
		if (($now - self::$lastQueueOptimizationAt) < self::$queueOptimizationIntervalSeconds) {
			return;
		}

		self::$lastQueueOptimizationAt = $now;
		self::$totalQueueOptimizations++;
		$pending = self::pendingQueueSize();
		$queueUtilization = self::$maxQueueSize > 0 ? ($pending / self::$maxQueueSize) : 0.0;

		if ($queueUtilization >= 0.75 && self::$maxConcurrentWorkers < self::$configuredMaxConcurrentWorkers) {
			self::$maxConcurrentWorkers++;
			self::$totalWorkerScaleUps++;
		} elseif ($queueUtilization <= 0.20 && self::$maxConcurrentWorkers > self::$minConcurrentWorkers) {
			self::$maxConcurrentWorkers--;
			self::$totalWorkerScaleDowns++;
		}

		if ($queueUtilization >= 0.75) {
			self::$batchSize = min(256, max(self::$configuredBatchSize, self::$configuredBatchSize * 4));
		} elseif ($queueUtilization >= 0.40) {
			self::$batchSize = min(256, max(self::$configuredBatchSize, self::$configuredBatchSize * 2));
		} else {
			self::$batchSize = self::$configuredBatchSize;
		}
	}

	/**
	 * Health check for stuck workers. Reclaims slots and requeues one retry attempt.
	 * @return array{reclaimed:int,restarted:int,degraded:bool}
	 */
	public static function runHealthCheck(?float $now = null) : array {
		$now ??= microtime(true);
		self::$lastHealthCheckAt = $now;

		$reclaimed = 0;
		$restarted = 0;
		$degraded = false;

		foreach (self::$activeBatches as $batchId => $batchMeta) {
			if (($now - $batchMeta["startedAt"]) <= self::$workerTimeoutSeconds) {
				continue;
			}
			unset(self::$activeBatches[$batchId]);
			self::$inFlight = self::$inFlight > 0 ? self::$inFlight - 1 : 0;
			foreach ($batchMeta["taskIds"] as $taskId) {
				$meta = self::$activeTasks[$taskId] ?? null;
				if (!is_array($meta)) {
					continue;
				}
				unset(self::$activeTasks[$taskId]);
				$reclaimed++;
				self::$totalRecoveredStuck++;
				if ($meta["attempt"] < 1) {
					self::$queue[] = [
						$meta["checkClass"],
						$meta["playerName"],
						$meta["payload"],
						$meta["sequence"],
						$now,
						$meta["capturedAt"],
						$meta["attempt"] + 1,
						$meta["dedupeKey"],
					];
					self::$pendingTaskIndexesByKey[$meta["dedupeKey"]] = count(self::$queue) - 1;
					$restarted++;
					self::$totalAutoRestarts++;
				}
			}
		}

		if ($reclaimed > 0) {
			$degraded = true;
			self::activateDegradedMode($now);
			self::drain();
		}

		return [
			"reclaimed" => $reclaimed,
			"restarted" => $restarted,
			"degraded" => $degraded,
		];
	}

	private static function pendingQueueSize() : int {
		$pending = count(self::$queue) - self::$queueHead;
		return $pending > 0 ? $pending : 0;
	}

	private static function compactQueueIfNeeded() : void {
		if (self::$queueHead <= 0) {
			return;
		}

		if (self::$queueHead < 256 && self::$queueHead < (count(self::$queue) / 2)) {
			return;
		}

		self::$queue = array_slice(self::$queue, self::$queueHead);
		self::$queueHead = 0;
		self::$pendingTaskIndexesByKey = [];
		foreach (self::$queue as $index => $entry) {
			if (is_array($entry)) {
				$key = $entry[7];
				if ($key !== "") {
					self::$pendingTaskIndexesByKey[$key] = $index;
				}
			}
		}
	}

	private static function parseMemoryLimitBytes() : int {
		$raw = ini_get("memory_limit");
		if (!is_string($raw) || $raw === "") {
			return 0;
		}
		if ($raw === "-1") {
			return 0;
		}

		$value = (int) $raw;
		$suffix = strtoupper(substr(rtrim($raw), -1));
		return match ($suffix) {
			"G" => $value * 1024 * 1024 * 1024,
			"M" => $value * 1024 * 1024,
			"K" => $value * 1024,
			default => $value
		};
	}

	private static function monitorResourcePressure(float $now) : void {
		$queueUtilization = self::$maxQueueSize > 0 ? (self::pendingQueueSize() / self::$maxQueueSize) : 0.0;
		$workerUtilization = self::$maxConcurrentWorkers > 0 ? (self::$inFlight / self::$maxConcurrentWorkers) : 0.0;
		$memoryLimit = self::parseMemoryLimitBytes();
		$memoryUsage = memory_get_usage(true);
		$memoryUtilization = $memoryLimit > 0 ? ($memoryUsage / $memoryLimit) : 0.0;
		$tps = Server::getInstance()->getTicksPerSecond();

		$overloaded = $queueUtilization >= 0.90
			|| $workerUtilization >= 0.95
			|| ($memoryLimit > 0 && $memoryUtilization >= 0.85)
			|| $tps < 15.0;

		self::$overloadActive = $overloaded;
		if (!$overloaded) {
			return;
		}
		if (($now - self::$lastOverloadAlertAt) < self::$overloadAlertCooldownSeconds) {
			return;
		}

		self::$lastOverloadAlertAt = $now;
		self::$totalOverloadAlerts++;
		Server::getInstance()->getLogger()->warning(
			"[Zuri] Async overload detected: queue="
			. self::pendingQueueSize() . "/" . self::$maxQueueSize
			. ", inFlight=" . self::$inFlight . "/" . self::$maxConcurrentWorkers
			. ", tps=" . $tps
			. ", memory=" . $memoryUsage
			. ($memoryLimit > 0 ? "/" . $memoryLimit : "")
		);
	}

	private static function activateDegradedMode(float $now) : void {
		self::$degradedUntil = max(self::$degradedUntil, $now + self::$degradedCooldownSeconds);
	}

	private static function isSyncFallbackActive(float $now) : bool {
		return self::$degradedUntil > $now;
	}

	private static function taskId(string $checkClass, string $playerName, int $sequence, int $attempt) : string {
		return $checkClass . ":" . $playerName . ":" . $sequence . ":" . $attempt;
	}

	private static function canMergeResult(PlayerAPI $playerAPI, string $checkClass, int $sequence, float $queuedAt) : bool {
		if ($playerAPI->isAsyncSequenceCurrent($checkClass, $sequence)) {
			return true;
		}

		$currentSequence = $playerAPI->getAsyncSequence($checkClass);
		$sequenceLag = $currentSequence - $sequence;
		$resultAge = microtime(true) - $queuedAt;
		return $sequenceLag >= 0
			&& $sequenceLag <= self::$maxAllowedSequenceLag
			&& $resultAge <= self::$maxAllowedResultAgeSeconds;
	}

	/**
	 * Test helper: reset static async pipeline state.
	 * @internal
	 */
	public static function resetForTesting() : void {
		self::$queue = [];
		self::$queueHead = 0;
		self::$pendingTaskIndexesByKey = [];
		self::$activeTasks = [];
		self::$activeBatches = [];
		self::$inFlight = 0;
		self::$maxConcurrentWorkers = 1;
		self::$configuredMaxConcurrentWorkers = 1;
		self::$minConcurrentWorkers = 1;
		self::$batchSize = 16;
		self::$configuredBatchSize = 16;
		self::$maxQueueSize = 2048;
		self::$workerTimeoutSeconds = 3.0;
		self::$degradedCooldownSeconds = 6.0;
		self::$degradedUntil = 0.0;
		self::$lastQueueOptimizationAt = 0.0;
		self::$overloadActive = false;
		self::$lastOverloadAlertAt = 0.0;

		self::$totalDispatched = 0;
		self::$totalCompleted = 0;
		self::$totalDropped = 0;
		self::$totalCoalesced = 0;
		self::$totalRecoveredStuck = 0;
		self::$totalAutoRestarts = 0;
		self::$totalLateCompletions = 0;
		self::$totalSyncFallback = 0;
		self::$totalFallbackErrors = 0;
		self::$totalQueueOptimizations = 0;
		self::$totalWorkerScaleUps = 0;
		self::$totalWorkerScaleDowns = 0;
		self::$totalOverloadAlerts = 0;
		self::$totalThreadErrors = 0;
		self::$totalThreadResultErrors = 0;
		self::$totalThreadRetries = 0;
		self::$maxAllowedSequenceLag = 4;
		self::$maxAllowedResultAgeSeconds = 0.75;
		self::$lastDispatchAt = 0.0;
		self::$lastCompleteAt = 0.0;
		self::$lastHealthCheckAt = 0.0;
		self::$totalWorkerTime = 0.0;
		self::$totalQueueWaitTime = 0.0;
		self::$totalBuildDelayTime = 0.0;
		self::$totalMergeTime = 0.0;
	}

	/**
	 * Test helper: inject an active task to simulate stuck-worker recovery.
	 * @internal
	 */
	public static function injectActiveTaskForTesting(
		string $checkClass,
		string $playerName,
		int $sequence,
		float $startedAt,
		float $queuedAt,
		float $capturedAt,
		int $attempt = 0
	) : void {
		$id = self::taskId($checkClass, $playerName, $sequence, $attempt);
		self::$activeTasks[$id] = [
			"startedAt" => $startedAt,
			"queuedAt" => $queuedAt,
			"capturedAt" => $capturedAt,
			"checkClass" => $checkClass,
			"playerName" => $playerName,
			"payload" => [],
			"sequence" => $sequence,
			"attempt" => $attempt,
			"batchId" => $id,
			"dedupeKey" => $checkClass . ":" . $playerName,
		];
		self::$activeBatches[$id] = [
			"startedAt" => $startedAt,
			"taskIds" => [$id],
		];
		self::$inFlight++;
	}

	/** @param array<string,mixed> $result */
	private static function applyResult(Check $check, PlayerAPI $playerAPI, array $result) : void {
		$setValues = $result['set'] ?? [];
		if (is_array($setValues)) {
			foreach ($setValues as $key => $value) {
				$playerAPI->setExternalData((string) $key, $value);
			}
		}

		$unsetValues = $result['unset'] ?? [];
		if (is_array($unsetValues)) {
			foreach ($unsetValues as $key) {
				$playerAPI->unsetExternalData((string) $key);
			}
		}

		if (isset($result['debug']) && is_string($result['debug']) && $result['debug'] !== '') {
			$check->debug($playerAPI, $result['debug']);
		}

		if (!empty($result['failed'])) {
			try {
				$check->failed($playerAPI);
			} catch (Throwable $throwable) {
				self::$totalFallbackErrors++;
				Server::getInstance()->getLogger()->warning(
					"[Zuri] Async failed() merge exception in "
					. $check->getName() . ":" . $check->getSubType()
					. " for " . $playerAPI->getPlayer()->getName()
					. ": " . $throwable->getMessage()
				);
			}
		}
	}
}
