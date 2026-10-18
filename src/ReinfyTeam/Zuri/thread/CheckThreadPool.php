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

namespace ReinfyTeam\Zuri\thread;

use function count;

/**
 * Manages a pool of CheckThread instances.
 *
 * All threads share the same CheckQueue and CheckResults so jobs are
 * distributed and results collected in a single place.
 */
final class CheckThreadPool {
	/** @var CheckThread[] */
	private array $threads = [];

	/**
	 * @param CheckQueue   $jobs           Shared job queue.
	 * @param CheckResults $results        Shared results queue.
	 * @param int          $threadCount    Number of threads to spawn.
	 * @param int          $workerCount    Workers per thread.
	 * @param int          $workerCapacity Maximum queued jobs per worker.
	 */
	public function __construct(
		private CheckQueue $jobs,
		private CheckResults $results,
		int $threadCount,
		int $workerCount,
		int $workerCapacity
	) {
		for ($i = 0; $i < $threadCount; ++$i) {
			$this->threads[] = new CheckThread($jobs, $results, $workerCount, $workerCapacity);
		}
	}

	/**
	 * Stops all threads gracefully.
	 */
	public function quit() : void {
		foreach ($this->threads as $thread) {
			$thread->quit();
		}
	}

	/**
	 * Returns all managed CheckThread instances.
	 *
	 * @return CheckThread[]
	 */
	public function getThreads() : array {
		return $this->threads;
	}

	/**
	 * Returns the number of threads in the pool.
	 */
	public function getThreadCount() : int {
		return count($this->threads);
	}

	/**
	 * Returns a specific thread by index.
	 */
	public function getThread(int $index) : ?CheckThread {
		return $this->threads[$index] ?? null;
	}

	/**
	 * Returns the shared CheckResults instance.
	 */
	public function getResults() : CheckResults {
		return $this->results;
	}

	/**
	 * Returns the total number of jobs submitted across all threads.
	 */
	public function getTotalJobsSubmitted() : int {
		$total = 0;
		foreach ($this->threads as $thread) {
			$total += $thread->getTotalJobsSubmitted();
		}
		return $total;
	}

	/**
	 * Returns the total number of completed jobs across all threads.
	 */
	public function getTotalCompletedJobs() : int {
		$total = 0;
		foreach ($this->threads as $thread) {
			$total += $thread->getCompletedJobs();
		}
		return $total;
	}

	/**
	 * Returns the total number of failed jobs across all threads.
	 */
	public function getTotalFailedJobs() : int {
		$total = 0;
		foreach ($this->threads as $thread) {
			$total += $thread->getFailedJobs();
		}
		return $total;
	}

	/**
	 * Returns the total number of workers across all threads.
	 */
	public function getTotalWorkerCount() : int {
		$total = 0;
		foreach ($this->threads as $thread) {
			$total += $thread->getWorkerCount();
		}
		return $total;
	}

	/**
	 * Returns the total number of currently queued jobs across all threads.
	 */
	public function getTotalCurrentQueuedJobs() : int {
		$total = 0;
		foreach ($this->threads as $thread) {
			$total += $thread->getCurrentQueuedJobs();
		}
		return $total;
	}

	/**
	 * Returns the total capacity across all threads (workers × capacity per worker).
	 */
	public function getTotalCapacity() : int {
		$total = 0;
		foreach ($this->threads as $thread) {
			$total += $thread->getTotalCapacity();
		}
		return $total;
	}

	/**
	 * Returns the total available capacity across all threads.
	 */
	public function getTotalAvailableCapacity() : int {
		$total = 0;
		foreach ($this->threads as $thread) {
			$total += $thread->getAvailableCapacity();
		}
		return $total;
	}

	/**
	 * Returns the number of threads that are currently running.
	 */
	public function getRunningThreadCount() : int {
		$count = 0;
		foreach ($this->threads as $thread) {
			if ($thread->isRunning()) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * Returns the aggregate throughput in jobs per second across all threads.
	 */
	public function getAggregateJobsPerSecond() : float {
		$total = 0.0;
		foreach ($this->threads as $thread) {
			$total += $thread->getJobsPerSecond();
		}
		return $total;
	}

	/**
	 * Returns the weighted average job time in microseconds across all threads.
	 * Returns 0.0 if no jobs have been completed.
	 */
	public function getAverageJobTimeUs() : float {
		$totalTime = 0;
		$totalCompleted = 0;
		foreach ($this->threads as $thread) {
			$stats = $thread->getStats();
			$totalTime += $stats->totalProcessingTimeUs;
			$totalCompleted += $stats->completedJobs;
		}
		return $totalCompleted > 0 ? $totalTime / $totalCompleted : 0.0;
	}

	/**
	 * Returns the native thread IDs of all threads in the pool.
	 *
	 * @return int[]
	 */
	public function getNativeThreadIds() : array {
		$ids = [];
		foreach ($this->threads as $thread) {
			$ids[] = $thread->getNativeThreadId();
		}
		return $ids;
	}

	/**
	 * Returns per-thread status snapshots as an array of associative arrays.
	 *
	 * @return array<int, array{
	 *     threadIndex: int,
	 *     nativeThreadId: int,
	 *     running: bool,
	 *     elapsedSeconds: float,
	 *     workerCount: int,
	 *     workerCapacity: int,
	 *     totalCapacity: int,
	 *     totalJobsSubmitted: int,
	 *     completedJobs: int,
	 *     failedJobs: int,
	 *     currentQueuedJobs: int,
	 *     availableCapacity: int,
	 *     jobsPerSecond: float,
	 *     averageJobTimeUs: float,
	 *     lastJobTimeUs: int,
	 *     minJobTimeUs: int,
	 *     maxJobTimeUs: int
	 * }>
	 */
	public function getPerThreadStatus() : array {
		$statuses = [];
		foreach ($this->threads as $index => $thread) {
			$statuses[] = [
				"threadIndex" => $index,
				"nativeThreadId" => $thread->getNativeThreadId(),
				"running" => $thread->isRunning(),
				"elapsedSeconds" => $thread->getElapsedTime(),
				"workerCount" => $thread->getWorkerCount(),
				"workerCapacity" => $thread->getWorkerCapacity(),
				"totalCapacity" => $thread->getTotalCapacity(),
				"totalJobsSubmitted" => $thread->getTotalJobsSubmitted(),
				"completedJobs" => $thread->getCompletedJobs(),
				"failedJobs" => $thread->getFailedJobs(),
				"currentQueuedJobs" => $thread->getCurrentQueuedJobs(),
				"availableCapacity" => $thread->getAvailableCapacity(),
				"jobsPerSecond" => $thread->getJobsPerSecond(),
				"averageJobTimeUs" => $thread->getAverageJobTimeUs(),
				"lastJobTimeUs" => $thread->getLastJobTimeUs(),
				"minJobTimeUs" => $thread->getMinJobTimeUs(),
				"maxJobTimeUs" => $thread->getMaxJobTimeUs(),
			];
		}
		return $statuses;
	}
}

