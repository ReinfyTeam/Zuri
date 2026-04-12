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
use ReinfyTeam\Zuri\lang\Lang;
use ReinfyTeam\Zuri\lang\LangKeys;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\AuditLogger;
use Throwable;
use vennv\vapm\thread\ClosureThread;
use function array_keys;
use function array_slice;
use function base64_decode;
use function base64_encode;
use function ceil;
use function count;
use function floor;
use function function_exists;
use function implode;
use function ini_get;
use function is_array;
use function is_bool;
use function is_int;
use function is_numeric;
use function is_string;
use function json_decode;
use function json_encode;
use function max;
use function memory_get_peak_usage;
use function memory_get_usage;
use function method_exists;
use function microtime;
use function min;
use function preg_split;
use function round;
use function rtrim;
use function str_replace;
use function stripcslashes;
use function strlen;
use function strpos;
use function strrpos;
use function strtoupper;
use function substr;
use function sys_getloadavg;
use function trim;

/**
 * Coordinates async check execution, batching, and fallback behavior.
 */
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
	private static float $workerTargetMilliseconds = 20.0;

	/** @var array<string,array{startedAt:float,queuedAt:float,capturedAt:float,checkClass:string,playerName:string,payload:array<string,mixed>,sequence:int,attempt:int,batchId:string,dedupeKey:string}> */
	private static array $activeTasks = [];
	/** @var array<string,array{startedAt:float,taskIds:list<string>,payloadKey:string}> */
	private static array $activeBatches = [];
	/** @var array<string,string> */
	private static array $threadBatchPayloads = [];

	private const THREAD_SHARED_BATCH_KEY = "__zuri_async_batches";

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

	/**
	 * Returns current dispatcher and runtime metrics.
	 *
	 * @return array<string,int|float|bool>
	 */
	public static function getMetrics() : array {
		$now = microtime(true);
		self::runHealthCheck($now);
		self::monitorResourcePressure($now);

		$completed = self::$totalCompleted > 0 ? self::$totalCompleted : 1;
		$pendingQueue = self::pendingQueueSize();
		$pendingClassGroups = self::pendingClassGroupCount();
		$pendingBatchUnits = self::pendingBatchUnitCount($pendingQueue);
		$queueUtilization = self::$maxQueueSize > 0 ? ($pendingQueue / self::$maxQueueSize) : 0.0;
		$workerUtilization = self::$maxConcurrentWorkers > 0 ? (self::$inFlight / self::$maxConcurrentWorkers) : 0.0;
		$memoryLimit = self::parseMemoryLimitBytes();
		$memoryUsage = memory_get_usage(true);
		$memoryPeak = memory_get_peak_usage(true);
		$memoryUtilization = $memoryLimit > 0 ? ($memoryUsage / $memoryLimit) : 0.0;
		$cpuLoad = function_exists("sys_getloadavg") ? (sys_getloadavg()[0] ?? 0.0) : 0.0;
		return [
			"queueSize" => $pendingQueue,
			"queueClassGroups" => $pendingClassGroups,
			"queueBatchUnits" => $pendingBatchUnits,
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
			"workerTargetMs" => self::$workerTargetMilliseconds,
		];
	}

	/**
	 * Configures the async dispatcher capacity and performance thresholds.
	 */
	public static function configure(int $maxConcurrentWorkers, int $maxQueueSize, float $workerTimeoutSeconds = 3.0, float $degradedCooldownSeconds = 6.0, int $batchSize = 16, float $workerTargetMilliseconds = 20.0) : void {
		self::$configuredMaxConcurrentWorkers = max(1, min(16, $maxConcurrentWorkers));
		self::$maxConcurrentWorkers = self::$configuredMaxConcurrentWorkers;
		self::$minConcurrentWorkers = 1;
		self::$configuredBatchSize = max(1, min(128, $batchSize));
		self::$batchSize = self::$configuredBatchSize;
		self::$maxQueueSize = $maxQueueSize > 0 ? $maxQueueSize : 1;
		self::$workerTimeoutSeconds = $workerTimeoutSeconds > 0.1 ? $workerTimeoutSeconds : 0.1;
		self::$degradedCooldownSeconds = $degradedCooldownSeconds > 0.1 ? $degradedCooldownSeconds : 0.1;
		self::$workerTargetMilliseconds = $workerTargetMilliseconds > 1.0 ? min(250.0, $workerTargetMilliseconds) : 20.0;
	}

	/**
	 * Enqueues a check payload for async processing.
	 *
	 * @param array<string,mixed> $payload
	 */
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

	/**
	 * Drains queued tasks into runnable batches while worker capacity is available.
	 */
	private static function drain() : void {
		self::runHealthCheck(microtime(true));

		while (self::$inFlight < self::$maxConcurrentWorkers && self::pendingQueueSize() > 0) {
			$pendingQueue = self::pendingQueueSize();
			$targetBatchSize = self::computeTargetBatchSize($pendingQueue);
			if ($pendingQueue >= ($targetBatchSize * 8)) {
				$targetBatchSize = min(128, $targetBatchSize * 4);
			} elseif ($pendingQueue >= ($targetBatchSize * 4)) {
				$targetBatchSize = min(128, $targetBatchSize * 2);
			} elseif ($pendingQueue >= ($targetBatchSize * 2)) {
				$targetBatchSize = min(128, $targetBatchSize + max(1, (int) ($targetBatchSize / 2)));
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
	 * Converts queued task entries into one worker batch and starts the async thread.
	 *
	 * @param list<array{0:string,1:string,2:array<string,mixed>,3:int,4:float,5:float,6:int,7:string}> $batch Batched queue entries selected by drain().
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
			"payloadKey" => $batchId,
		];
		self::$inFlight++;
		self::$totalDispatched += count($taskIds);
		$encodedBatchPayloadRaw = json_encode(
			$batchPayload,
			JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
		);
		$encodedBatchPayload = is_string($encodedBatchPayloadRaw) ? base64_encode($encodedBatchPayloadRaw) : "";
		self::storeBatchPayload($batchId, $encodedBatchPayload);

		$thread = new ClosureThread(
			static function (string $batchPayloadKey) : string {
				return CheckAsyncTask::runThreadBatch($batchPayloadKey);
			},
			[$batchId]
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

	/**
	 * Handles thread batch completion, retry/fallback logic, and result merge.
	 *
	 * @param string $batchId Internal batch identifier created in startBatch().
	 * @param mixed $output Raw completion payload received from the worker thread.
	 * @param mixed $reason Rejection reason when the thread promise fails.
	 */
	private static function handleBatchCompletion(string $batchId, mixed $output, mixed $reason = null) : void {
		$batchMeta = self::$activeBatches[$batchId] ?? null;
		if (!is_array($batchMeta)) {
			self::$totalLateCompletions++;
			return;
		}
		unset(self::$activeBatches[$batchId]);
		self::releaseBatchPayload($batchMeta["payloadKey"]);
		self::$inFlight = self::$inFlight > 0 ? self::$inFlight - 1 : 0;
		self::$lastCompleteAt = microtime(true);
		$batchDuration = max(0.0, self::$lastCompleteAt - $batchMeta["startedAt"]);

		self::drain();

		if ($reason !== null) {
			$reasonText = self::normalizeErrorReason($reason);
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
				self::logTaskFailure("thread-error", $batchId, $taskMeta, $reasonText);
				if (self::retryTask($taskMeta)) {
					continue;
				}
				self::logTaskFailure("thread-error-fallback", $batchId, $taskMeta, $reasonText);
				self::executeSyncFallback($taskMeta["checkClass"], $taskMeta["playerName"], $taskMeta["payload"], $taskMeta["sequence"]);
			}
			self::activateDegradedMode(self::$lastCompleteAt);
			self::drain();
			return;
		}

		$decoded = self::decodeThreadOutput($output);
		$results = is_array($decoded) ? self::normalizeThreadResults($decoded) : [];
		$topLevelError = is_array($decoded) && is_string($decoded["error"] ?? null) ? $decoded["error"] : null;
		if (($topLevelError !== null && $results === []) || !is_array($decoded)) {
			$reasonText = $topLevelError !== null ? "Thread output error: " . $topLevelError : "Invalid thread output payload";
			if ($topLevelError === null && is_string($output) && $output !== "") {
				$reasonText .= " (rawPreview=" . self::summarizeThreadOutput($output) . ")";
			}
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
				self::logTaskFailure("thread-error", $batchId, $taskMeta, $reasonText);
				if (self::retryTask($taskMeta)) {
					continue;
				}
				self::logTaskFailure("thread-error-fallback", $batchId, $taskMeta, $reasonText);
				self::executeSyncFallback($taskMeta["checkClass"], $taskMeta["playerName"], $taskMeta["payload"], $taskMeta["sequence"]);
			}
			self::activateDegradedMode(self::$lastCompleteAt);
			self::drain();
			return;
		}

		$rawResultsList = is_array($decoded) && is_array($decoded["results"] ?? null) ? $decoded["results"] : [];
		$orderedIndex = 0;

		foreach ($batchMeta["taskIds"] as $id) {
			$taskMeta = self::$activeTasks[$id] ?? null;
			if (!is_array($taskMeta)) {
				self::$totalLateCompletions++;
				continue;
			}
			unset(self::$activeTasks[$id]);
			self::$totalCompleted++;
			self::$totalWorkerTime += $batchDuration;

			$normalizedId = trim($id, " \t\n\r\0\x0B\"'");
			$result = $results[$normalizedId] ?? null;
			if (!is_array($result)) {
				$result = self::extractIndexedFallbackResult($rawResultsList, $orderedIndex);
			}
			$orderedIndex++;
			if (!is_array($result)) {
				self::$totalThreadResultErrors++;
				$reasonText = "Invalid or missing thread result payload";
				self::logTaskFailure("result-error", $batchId, $taskMeta, $reasonText);
				if (self::retryTask($taskMeta)) {
					continue;
				}
				self::logTaskFailure("result-error-fallback", $batchId, $taskMeta, $reasonText);
				self::executeSyncFallback($taskMeta["checkClass"], $taskMeta["playerName"], $taskMeta["payload"], $taskMeta["sequence"]);
				continue;
			}

			if (isset($result["error"])) {
				self::$totalThreadResultErrors++;
				$reasonText = is_string($result["error"]) ? $result["error"] : "Invalid or missing thread result payload";
				self::logTaskFailure("result-error", $batchId, $taskMeta, $reasonText);
				if (self::retryTask($taskMeta)) {
					continue;
				}
				self::logTaskFailure("result-error-fallback", $batchId, $taskMeta, $reasonText);
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
	 * Attempts to decode worker output into the normalized thread payload shape.
	 *
	 * @param mixed $output Raw output returned by the ClosureThread promise chain.
	 * @return array<string,mixed>|null Decoded payload, or null when output cannot be parsed.
	 */
	private static function decodeThreadOutput(mixed $output) : ?array {
		if (is_array($output)) {
			return $output;
		}
		if (!is_string($output) || $output === "") {
			return null;
		}
		$output = trim($output);
		if ($output === "") {
			return null;
		}

		$decoded = self::decodeThreadOutputCandidate($output);
		if (is_array($decoded)) {
			return $decoded;
		}

		$lines = preg_split('/\R+/', $output);
		if (is_array($lines)) {
			for ($i = count($lines) - 1; $i >= 0; --$i) {
				$line = trim($lines[$i]);
				if ($line === "") {
					continue;
				}
				foreach (["postThread=>", "postMainThread=>"] as $marker) {
					$markerPos = strpos($line, $marker);
					if (is_int($markerPos)) {
						$line = substr($line, $markerPos + strlen($marker));
						$line = trim($line);
						break;
					}
				}
				$decodedLine = self::decodeThreadOutputCandidate($line);
				if (is_array($decodedLine)) {
					return $decodedLine;
				}
				foreach (self::extractJsonCandidates($line) as $candidate) {
					$decodedCandidate = self::decodeThreadOutputCandidate($candidate);
					if (is_array($decodedCandidate)) {
						return $decodedCandidate;
					}
				}
			}
		}
		foreach (self::extractJsonCandidates($output) as $candidate) {
			$decodedCandidate = self::decodeThreadOutputCandidate($candidate);
			if (is_array($decodedCandidate)) {
				return $decodedCandidate;
			}
		}

		return null;
	}

	/**
	 * Executes a grouped batch in the worker thread and returns a JSON payload.
	 *
	 * @param string $batchPayloadKey Shared-storage key used to retrieve the encoded task list.
	 * @return string JSON-encoded batch result payload.
	 */
	public static function runThreadBatch(string $batchPayloadKey) : string {
		$results = [];
		$sharedData = ClosureThread::getSharedData();
		$payloadMap = is_array($sharedData[self::THREAD_SHARED_BATCH_KEY] ?? null) ? $sharedData[self::THREAD_SHARED_BATCH_KEY] : [];
		$encodedTasks = is_string($payloadMap[$batchPayloadKey] ?? null) ? $payloadMap[$batchPayloadKey] : "";
		$decodedPayload = base64_decode($encodedTasks, true);
		$tasks = is_string($decodedPayload) ? json_decode($decodedPayload, true) : null;
		if (!is_array($tasks)) {
			$failed = json_encode(
				[
					"error" => $encodedTasks === "" ? "Missing shared batch payload" : "Unable to decode batch payload",
					"results" => [],
					"encodedLength" => (string) strlen($encodedTasks),
					"payloadKey" => $batchPayloadKey,
				],
				JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
			);
			return is_string($failed) ? $failed : "{\"results\":{}}";
		}

		/** @var array<string,list<array{id:string,payload:array<string,mixed>}>> $grouped */
		$grouped = [];
		foreach ($tasks as $task) {
			$checkClass = is_string($task["checkClass"] ?? null) ? trim($task["checkClass"], " \t\n\r\0\x0B\"'") : "";
			$id = is_string($task["id"] ?? null) ? trim($task["id"], " \t\n\r\0\x0B\"'") : "";
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
			try {
				if (method_exists($checkClass, "evaluateAsyncBatch")) {
					$batchResults = $checkClass::evaluateAsyncBatch($entries);
					foreach ($entries as $entry) {
						$id = $entry["id"];
						$result = $batchResults[$id] ?? ["error" => "Missing batch result"];
						$results[$id] = is_array($result) ? $result : ["error" => "Invalid batch result"];
					}
					continue;
				}

				if (!method_exists($checkClass, "evaluateAsync")) {
					foreach ($entries as $entry) {
						$results[$entry["id"]] = ["error" => "Missing evaluateAsync()"];
					}
					continue;
				}

				foreach ($entries as $entry) {
					$results[$entry["id"]] = $checkClass::evaluateAsync($entry["payload"]);
				}
			} catch (Throwable $throwable) {
				foreach ($entries as $entry) {
					$results[$entry["id"]] = ["error" => $throwable->getMessage()];
				}
			}
		}

		$encoded = json_encode(
			["results" => $results],
			JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
		);
		return is_string($encoded) ? $encoded : "{\"results\":{}}";
	}

	/**
	 * Tries multiple decoding strategies for a single candidate thread output string.
	 *
	 * @param string $value Candidate string that may contain JSON payload data.
	 * @return array<string,mixed>|null Parsed payload or null when no strategy succeeds.
	 */
	private static function decodeThreadOutputCandidate(string $value) : ?array {
		$decoded = json_decode($value, true);
		if (is_array($decoded)) {
			return $decoded;
		}
		if (is_string($decoded)) {
			$decodedString = json_decode($decoded, true);
			if (is_array($decodedString)) {
				return $decodedString;
			}
		}

		$unescaped = stripcslashes($value);
		if ($unescaped !== $value) {
			$decodedUnescaped = json_decode($unescaped, true);
			if (is_array($decodedUnescaped)) {
				return $decodedUnescaped;
			}
		}

		$base64Decoded = base64_decode($value, true);
		if (is_string($base64Decoded)) {
			$decodedBase64 = json_decode($base64Decoded, true);
			if (is_array($decodedBase64)) {
				return $decodedBase64;
			}
		}

		$start = strpos($value, "{");
		$end = strrpos($value, "}");
		if (is_int($start) && is_int($end) && $end >= $start) {
			$fragment = substr($value, $start, ($end - $start) + 1);
			$decodedFragment = json_decode($fragment, true);
			if (is_array($decodedFragment)) {
				return $decodedFragment;
			}
			$decodedFragmentUnescaped = json_decode(stripcslashes($fragment), true);
			if (is_array($decodedFragmentUnescaped)) {
				return $decodedFragmentUnescaped;
			}
		}

		return null;
	}

	/**
	 * Extracts balanced JSON object fragments from noisy text output.
	 *
	 * @param string $value Text that may embed one or more JSON object strings.
	 * @return list<string> Candidate JSON fragments ready for decode attempts.
	 */
	private static function extractJsonCandidates(string $value) : array {
		$candidates = [];
		$inString = false;
		$escaped = false;
		$depth = 0;
		$start = -1;
		$length = strlen($value);

		for ($i = 0; $i < $length; ++$i) {
			$char = $value[$i];
			if ($inString) {
				if ($escaped) {
					$escaped = false;
					continue;
				}
				if ($char === "\\") {
					$escaped = true;
					continue;
				}
				if ($char === "\"") {
					$inString = false;
				}
				continue;
			}

			if ($char === "\"") {
				$inString = true;
				continue;
			}
			if ($char === "{") {
				if ($depth === 0) {
					$start = $i;
				}
				$depth++;
				continue;
			}
			if ($char === "}" && $depth > 0) {
				$depth--;
				if ($depth === 0 && $start >= 0) {
					$fragment = substr($value, $start, ($i - $start) + 1);
					if ($fragment !== "") {
						$candidates[] = $fragment;
					}
					$start = -1;
				}
			}
		}

		return $candidates;
	}

	/**
	 * Normalizes decoded thread results into an ID-keyed map consumed by merge logic.
	 *
	 * @param array<string,mixed> $decoded
	 * @return array<string,array<string,mixed>> Map of task IDs to result payload arrays.
	 */
	private static function normalizeThreadResults(array $decoded) : array {
		$source = is_array($decoded["results"] ?? null) ? $decoded["results"] : $decoded;
		$normalized = [];

		foreach ($source as $id => $value) {
			if (is_string($id) && is_array($value)) {
				$normalized[trim($id, " \t\n\r\0\x0B\"'")] = $value;
				continue;
			}
			if (is_array($value)) {
				$entryId = is_string($value["id"] ?? null) ? $value["id"] : null;
				$entryResult = is_array($value["result"] ?? null)
					? $value["result"]
					: (is_array($value["payload"] ?? null) ? $value["payload"] : null);
				if ($entryId !== null && is_array($entryResult)) {
					$normalized[trim($entryId, " \t\n\r\0\x0B\"'")] = $entryResult;
				}
			}
		}

		return $normalized;
	}

	/**
	 * Requeues a failed task once before forcing synchronous fallback handling.
	 *
	 * @param array{startedAt:float,queuedAt:float,capturedAt:float,checkClass:string,playerName:string,payload:array<string,mixed>,sequence:int,attempt:int,batchId:string,dedupeKey:string} $taskMeta
	 * @return bool True when the task was requeued; false when retries are exhausted.
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
		self::logTaskFailure("retry-scheduled", $taskMeta["batchId"], $taskMeta, "retry attempt=" . ($taskMeta["attempt"] + 1));
		return true;
	}

	/**
	 * Runs check evaluation synchronously when thread execution is unavailable or failed.
	 *
	 * @param string $checkClass Check class to execute.
	 * @param string $playerName Player name used to resolve the online target.
	 * @param array<string,mixed> $payload Captured check input payload.
	 * @param int $sequence Async sequence number used for stale-result protection.
	 */
	private static function executeSyncFallback(string $checkClass, string $playerName, array $payload, int $sequence) : void {
		self::$totalSyncFallback++;
		$context = "check={$checkClass}, player={$playerName}, sequence={$sequence}";

		try {
			if (!method_exists($checkClass, "evaluateAsync")) {
				self::$totalFallbackErrors++;
				$reason = Lang::get(LangKeys::DEBUG_ASYNC_REASON_MISSING_EVALUATE, [], "Missing evaluateAsync()");
				self::logWarning(Lang::get(LangKeys::DEBUG_ASYNC_FALLBACK_FAILED, [
					"context" => $context,
					"reason" => $reason,
				], "Async fallback failed ({context}): {reason}"));
				return;
			}

			$result = $checkClass::evaluateAsync($payload);
			if (!is_array($result)) {
				self::$totalFallbackErrors++;
				$reason = Lang::get(LangKeys::DEBUG_ASYNC_REASON_INVALID_FALLBACK_PAYLOAD, [], "Invalid fallback result payload");
				self::logWarning(Lang::get(LangKeys::DEBUG_ASYNC_FALLBACK_FAILED, [
					"context" => $context,
					"reason" => $reason,
				], "Async fallback failed ({context}): {reason}"));
				return;
			}

			if (isset($result["error"])) {
				self::$totalFallbackErrors++;
				$reason = is_string($result["error"])
					? $result["error"]
					: Lang::get(LangKeys::DEBUG_ASYNC_REASON_INVALID_FALLBACK_PAYLOAD, [], "Invalid fallback result payload");
				self::logWarning(Lang::get(LangKeys::DEBUG_ASYNC_FALLBACK_FAILED, [
					"context" => $context,
					"reason" => $reason,
				], "Async fallback failed ({context}): {reason}"));
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
		} catch (Throwable $throwable) {
			self::$totalFallbackErrors++;
			self::logWarning(Lang::get(LangKeys::DEBUG_ASYNC_FALLBACK_EXCEPTION, [
				"context" => $context,
				"error" => $throwable->getMessage(),
			], "Async fallback exception ({context}): {error}"));
		}
	}

	/**
	 * Emits a structured async-task failure record into the debug logging pipeline.
	 *
	 * @param string $kind Failure category identifier.
	 * @param string $batchId Batch identifier where the failure happened.
	 * @param array{startedAt:float,queuedAt:float,capturedAt:float,checkClass:string,playerName:string,payload:array<string,mixed>,sequence:int,attempt:int,batchId:string,dedupeKey:string} $taskMeta
	 * @param string $reason Human-readable failure reason.
	 */
	private static function logTaskFailure(string $kind, string $batchId, array $taskMeta, string $reason) : void {
		self::logWarning(Lang::get(LangKeys::DEBUG_ASYNC_TASK_FAILURE, [
			"kind" => $kind,
			"check" => $taskMeta["checkClass"],
			"player" => $taskMeta["playerName"],
			"sequence" => $taskMeta["sequence"],
			"attempt" => $taskMeta["attempt"],
			"batch" => $batchId,
			"reason" => $reason,
		], "Async {kind}: check={check}, player={player}, sequence={sequence}, attempt={attempt}, batch={batch}, reason={reason}"));
	}

	/**
	 * Emits warning logs to both server logger and thread audit logger.
	 *
	 * @param string $message Log message content.
	 */
	private static function logWarning(string $message) : void {
		Server::getInstance()->getLogger()->warning($message);
		AuditLogger::thread($message);
	}

	/**
	 * Converts mixed thread rejection reasons into a stable loggable string.
	 *
	 * @param mixed $reason Promise rejection reason from worker completion.
	 * @return string Stable reason string for diagnostics.
	 */
	private static function normalizeErrorReason(mixed $reason) : string {
		if ($reason instanceof Throwable) {
			$text = $reason::class
				. ": " . $reason->getMessage()
				. " at " . $reason->getFile()
				. ":" . $reason->getLine();
			$trace = trim($reason->getTraceAsString());
			if ($trace !== "") {
				$text .= " | trace=" . str_replace(["\r", "\n"], ["", " | "], $trace);
			}
			return $text;
		}
		if (is_string($reason)) {
			return $reason;
		}
		if (is_numeric($reason) || is_bool($reason)) {
			return (string) $reason;
		}
		if (is_array($reason)) {
			$encoded = json_encode($reason);
			return is_string($encoded) ? $encoded : "array";
		}
		return "unknown async error reason";
	}

	/**
	 * Summarizes raw worker output while keeping fatal messages and stack traces readable.
	 *
	 * @param string $output Raw worker output text.
	 * @return string Compact summary containing the error headline and relevant stack lines.
	 */
	private static function summarizeThreadOutput(string $output) : string {
		$lines = preg_split('/\R+/', trim(str_replace("\r", "\n", $output)));
		if (!is_array($lines) || $lines === []) {
			$fallback = trim($output);
			return strlen($fallback) > 1200 ? substr($fallback, 0, 1200) : $fallback;
		}

		$headline = "";
		$stackLines = [];
		foreach ($lines as $rawLine) {
			if (!is_string($rawLine)) {
				continue;
			}
			$line = trim($rawLine);
			if ($line === "") {
				continue;
			}
			if ($headline === "") {
				$headline = $line;
				continue;
			}
			if (strpos($line, "Stack trace:") !== false || strpos($line, "#") === 0 || strpos($line, "thrown in") !== false || strpos($line, "Next ") === 0) {
				$stackLines[] = $line;
				if (count($stackLines) >= 8) {
					break;
				}
			}
		}

		if ($headline === "") {
			$headline = "Unknown worker output";
		}
		$summary = $headline;
		if ($stackLines !== []) {
			$summary .= " || " . implode(" || ", $stackLines);
		}
		return strlen($summary) > 1200 ? substr($summary, 0, 1200) : $summary;
	}

	/**
	 * Extracts a compatible fallback result payload from positional worker output.
	 *
	 * @param array<int|string,mixed> $rawResultsList
	 * @return array<string,mixed>|null
	 */
	private static function extractIndexedFallbackResult(array $rawResultsList, int $index) : ?array {
		$entry = $rawResultsList[$index] ?? null;
		if (!is_array($entry)) {
			return null;
		}
		if (is_array($entry["result"] ?? null)) {
			return $entry["result"];
		}
		if (is_array($entry["payload"] ?? null)) {
			return $entry["payload"];
		}
		return $entry;
	}

	/**
	 * Dynamically optimize worker count based on queue load and performance metrics.
	 * Scales up workers when queue is filling, scales down when underutilized.
	 *
	 * @param float $now Current timestamp used for throttling optimization passes.
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

		$targetWorkerSeconds = self::$workerTargetMilliseconds / 1000.0;
		$avgWorkerSeconds = self::$totalCompleted > 0 ? (self::$totalWorkerTime / self::$totalCompleted) : 0.0;
		if ($avgWorkerSeconds > 0.0 && $avgWorkerSeconds > ($targetWorkerSeconds * 1.2)) {
			self::$batchSize = max(1, (int) floor(self::$batchSize * 0.75));
		} elseif ($queueUtilization >= 0.75) {
			self::$batchSize = min(128, max(self::$configuredBatchSize, self::$configuredBatchSize * 2));
		} elseif ($queueUtilization >= 0.40) {
			self::$batchSize = min(128, max(self::$configuredBatchSize, self::$configuredBatchSize + (int) floor(self::$configuredBatchSize / 2)));
		} else {
			self::$batchSize = self::$configuredBatchSize;
		}

		if ($avgWorkerSeconds > 0.0 && $avgWorkerSeconds > ($targetWorkerSeconds * 2.0) && self::$batchSize > 1) {
			self::$batchSize = max(1, (int) floor(self::$batchSize / 2));
		}
	}

	/**
	 * Computes adaptive batch size from queue pressure and worker timings.
	 *
	 * @param int $pendingQueue Current pending queue depth.
	 * @return int Target batch size for the next drain cycle.
	 */
	private static function computeTargetBatchSize(int $pendingQueue) : int {
		$base = max(1, self::$batchSize);
		$completed = self::$totalCompleted;
		if ($completed <= 0 || self::$totalWorkerTime <= 0.0) {
			return $base;
		}
		$avgWorkerSeconds = self::$totalWorkerTime / $completed;
		$targetSeconds = max(0.001, self::$workerTargetMilliseconds / 1000.0);
		if ($avgWorkerSeconds <= 0.0) {
			return $base;
		}
		$ratio = $targetSeconds / $avgWorkerSeconds;
		if ($ratio < 1.0) {
			$base = max(1, (int) floor($base * max(0.1, $ratio)));
		} elseif ($pendingQueue > ($base * 2) && $ratio > 1.2) {
			$scaleUp = min(2.0, $ratio);
			$base = min(128, max($base, (int) ceil($base * $scaleUp)));
		}
		return max(1, min(128, $base));
	}

	/**
	 * Health check for stuck workers. Reclaims slots and requeues one retry attempt.
	 *
	 * @param float|null $now Optional timestamp override for deterministic tests.
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
			self::releaseBatchPayload($batchMeta["payloadKey"]);
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

	/**
	 * Returns current number of pending queued tasks.
	 *
	 * @return int Number of queued tasks that have not yet been started.
	 */
	private static function pendingQueueSize() : int {
		$pending = count(self::$queue) - self::$queueHead;
		return $pending > 0 ? $pending : 0;
	}

	/**
	 * Counts unique check-class groups currently present in the pending queue.
	 *
	 * @return int Number of distinct check classes waiting in queue.
	 */
	private static function pendingClassGroupCount() : int {
		$groups = [];
		$count = count(self::$queue);
		for ($i = self::$queueHead; $i < $count; $i++) {
			$entry = self::$queue[$i] ?? null;
			if (!is_array($entry)) {
				continue;
			}
			$groups[$entry[0]] = true;
		}
		return count($groups);
	}

	/**
	 * Estimates pending batch units for the given queue depth.
	 *
	 * @param int $pendingQueue Current pending queue depth.
	 * @return int Estimated number of batches required to drain queue.
	 */
	private static function pendingBatchUnitCount(int $pendingQueue) : int {
		if ($pendingQueue <= 0) {
			return 0;
		}
		$size = self::$batchSize > 0 ? self::$batchSize : 1;
		return (int) ceil($pendingQueue / $size);
	}

	/**
	 * Compacts queue storage and rebuilds key indexes after head advancement.
	 */
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

	/**
	 * Parses PHP memory_limit into bytes. Returns 0 for unlimited/unknown.
	 *
	 * @return int Parsed memory limit in bytes, or 0 when unlimited/unknown.
	 */
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

	/**
	 * Tracks overload signals and emits throttled overload diagnostics.
	 *
	 * @param float $now Current timestamp used for cooldown throttling.
	 */
	private static function monitorResourcePressure(float $now) : void {
		$queueUtilization = self::$maxQueueSize > 0 ? (self::pendingQueueSize() / self::$maxQueueSize) : 0.0;
		$workerUtilization = self::$maxConcurrentWorkers > 0 ? (self::$inFlight / self::$maxConcurrentWorkers) : 0.0;
		$memoryLimit = self::parseMemoryLimitBytes();
		$memoryUsage = memory_get_usage(true);
		$memoryUtilization = $memoryLimit > 0 ? ($memoryUsage / $memoryLimit) : 0.0;
		$tps = Server::getInstance()->getTicksPerSecond();

		$queueOverloaded = $queueUtilization >= 0.90;
		$memoryOverloaded = $memoryLimit > 0 && $memoryUtilization >= 0.85;
		$tpsOverloaded = $tps < 15.0;
		// Full workers alone can be healthy; require meaningful backlog before treating it as overload.
		$workerOverloaded = $workerUtilization >= 0.98 && $queueUtilization >= 0.25;
		$overloaded = $queueOverloaded || $workerOverloaded || $memoryOverloaded || $tpsOverloaded;

		self::$overloadActive = $overloaded;
		if (!$overloaded) {
			return;
		}
		if (($now - self::$lastOverloadAlertAt) < self::$overloadAlertCooldownSeconds) {
			return;
		}

		self::$lastOverloadAlertAt = $now;
		self::$totalOverloadAlerts++;
		self::logWarning(Lang::get(LangKeys::DEBUG_ASYNC_OVERLOAD, [
			"queue" => (string) self::pendingQueueSize(),
			"maxQueue" => (string) self::$maxQueueSize,
			"inFlight" => (string) self::$inFlight,
			"maxWorkers" => (string) self::$maxConcurrentWorkers,
			"tps" => (string) round($tps, 2),
			"memory" => (string) $memoryUsage . ($memoryLimit > 0 ? "/" . $memoryLimit : ""),
		]));
	}

	/**
	 * Activates temporary degraded mode to force synchronous fallback.
	 *
	 * @param float $now Current timestamp used to compute degraded-mode expiry.
	 */
	private static function activateDegradedMode(float $now) : void {
		self::$degradedUntil = max(self::$degradedUntil, $now + self::$degradedCooldownSeconds);
	}

	/**
	 * Returns whether synchronous fallback mode is currently active.
	 *
	 * @param float $now Current timestamp used to compare degraded expiry.
	 * @return bool True when synchronous fallback should be used.
	 */
	private static function isSyncFallbackActive(float $now) : bool {
		return self::$degradedUntil > $now;
	}

	/**
	 * Builds a deterministic task identifier for queue/active maps.
	 *
	 * @param string $checkClass Check class name.
	 * @param string $playerName Player name associated with the check.
	 * @param int $sequence Async sequence index.
	 * @param int $attempt Retry attempt number.
	 * @return string Deterministic task identifier.
	 */
	private static function taskId(string $checkClass, string $playerName, int $sequence, int $attempt) : string {
		return $checkClass . ":" . $playerName . ":" . $sequence . ":" . $attempt;
	}

	/**
	 * Validates whether an async result is still eligible to be merged.
	 *
	 * @param PlayerAPI $playerAPI Runtime player API wrapper.
	 * @param string $checkClass Check class associated with the result.
	 * @param int $sequence Sequence number captured at dispatch time.
	 * @param float $queuedAt Queue timestamp used for result age calculations.
	 * @return bool True when the result should be merged into player state.
	 */
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
	 *
	 * @internal
	 */
	public static function resetForTesting() : void {
		self::$queue = [];
		self::$queueHead = 0;
		self::$pendingTaskIndexesByKey = [];
		self::$activeTasks = [];
		self::$activeBatches = [];
		self::$threadBatchPayloads = [];
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
		self::$workerTargetMilliseconds = 20.0;

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
		self::syncSharedBatchPayloads();
	}

	/**
	 * Test helper: inject an active task to simulate stuck-worker recovery.
	 *
	 * @param string $checkClass Check class for the synthetic active task.
	 * @param string $playerName Player name for the synthetic active task.
	 * @param int $sequence Sequence number for the synthetic task.
	 * @param float $startedAt Simulated worker start timestamp.
	 * @param float $queuedAt Simulated queue timestamp.
	 * @param float $capturedAt Simulated snapshot capture timestamp.
	 * @param int $attempt Simulated retry attempt count.
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
			"payloadKey" => $id,
		];
		self::$inFlight++;
	}

	/**
	 * Stores encoded batch payload for worker-thread retrieval.
	 *
	 * @param string $batchPayloadKey Shared-data key used by the worker.
	 * @param string $encodedPayload Base64-encoded JSON payload.
	 */
	private static function storeBatchPayload(string $batchPayloadKey, string $encodedPayload) : void {
		self::$threadBatchPayloads[$batchPayloadKey] = $encodedPayload;
		self::pruneSharedBatchPayloads();
		self::syncSharedBatchPayloads();
	}

	/**
	 * Releases a stored batch payload after batch completion.
	 *
	 * @param string $batchPayloadKey Shared-data key to remove.
	 */
	private static function releaseBatchPayload(string $batchPayloadKey) : void {
		if (!isset(self::$threadBatchPayloads[$batchPayloadKey])) {
			return;
		}
		unset(self::$threadBatchPayloads[$batchPayloadKey]);
		self::pruneSharedBatchPayloads();
		self::syncSharedBatchPayloads();
	}

	/**
	 * Synchronizes payload map into LibVapm thread shared storage.
	 */
	private static function syncSharedBatchPayloads() : void {
		ClosureThread::addShared(self::THREAD_SHARED_BATCH_KEY, self::$threadBatchPayloads);
	}

	/**
	 * Prunes payload cache entries that are no longer referenced by active batches.
	 */
	private static function pruneSharedBatchPayloads() : void {
		foreach (array_keys(self::$threadBatchPayloads) as $batchPayloadKey) {
			if (!isset(self::$activeBatches[$batchPayloadKey])) {
				unset(self::$threadBatchPayloads[$batchPayloadKey]);
			}
		}
		if (count(self::$threadBatchPayloads) > 24) {
			self::$threadBatchPayloads = array_slice(self::$threadBatchPayloads, -24, null, true);
		}
	}

	/**
	 * Applies async check output fields onto the live player/check state.
	 *
	 * @param Check $check Check instance receiving debug/fail callbacks.
	 * @param PlayerAPI $playerAPI Player API instance updated by merge operations.
	 * @param array<string,mixed> $result Normalized async result payload.
	 */
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
				self::logWarning(
					"Async failed() merge exception in "
					. $check->getName() . ":" . $check->getSubType()
					. " for " . $playerAPI->getPlayer()->getName()
					. ": " . $throwable->getMessage()
				);
			}
		}
	}
}
