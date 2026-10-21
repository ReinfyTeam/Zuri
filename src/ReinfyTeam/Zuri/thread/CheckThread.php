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

use pocketmine\thread\Thread;
use function getmypid;
use function microtime;
use function usleep;

final class CheckThread extends Thread {
	private CheckThreadStats $stats;

	public function __construct(
		private CheckQueue $jobs,
		private CheckResults $results,
		private int $workerCount,
		private int $workerCapacity
	) {
		$this->stats = new CheckThreadStats();
		$this->stats->workerCount = $workerCount;
		$this->stats->workerCapacity = $workerCapacity;
		$this->start();
	}

	protected function onRun() : void {
		$this->stats->startTimeSeconds = microtime(true);
		$this->stats->running = true;

		$pid = getmypid();
		$this->stats->nativeThreadId = $pid !== false ? $pid : 0;

		/** @var CheckWorker[] $workers */
		$workers = [];
		for ($i = 0; $i < $this->workerCount; ++$i) {
			$workers[] = new CheckWorker($this->results, $this->workerCapacity, $this->stats);
		}

		while (!$this->isKilled) {
			$queued = false;
			foreach ($workers as $worker) {
				while ($worker->canAccept()) {
					$job = $this->jobs->getNextCheck();
					if ($job === null) {
						break;
					}
					$worker->enqueue($job);
					$this->stats->totalJobsSubmitted++;
					$queued = true;
				}
			}

			$processed = false;
			foreach ($workers as $worker) {
				$processed = $worker->processNext() || $processed;
			}

			// Snapshot current queue sizes for the stats.
			$totalQueued = 0;
			foreach ($workers as $worker) {
				$totalQueued += $worker->getJobSize();
			}
			$this->stats->currentQueuedJobs = $totalQueued;

			if (!$queued && !$processed) {
				usleep(1000);
			}
		}

		$this->stats->running = false;
	}

	public function getResults() : CheckResults {
		return $this->results;
	}

	/**
	 * Returns the thread-safe stats object for this thread.
	 * Can be read from the main thread at any time.
	 */
	public function getStats() : CheckThreadStats {
		return $this->stats;
	}
	
	/**
	 * Returns the total number of jobs that have been submitted to workers in this thread.
	 */
	public function getTotalJobsSubmitted() : int {
		return $this->stats->totalJobsSubmitted;
	}

	/**
	 * Returns the number of jobs that completed successfully.
	 */
	public function getCompletedJobs() : int {
		return $this->stats->completedJobs;
	}

	/**
	 * Returns the number of jobs that failed to return results (threw exceptions).
	 */
	public function getFailedJobs() : int {
		return $this->stats->failedJobs;
	}

	/**
	 * Returns the elapsed time in seconds since this thread started running.
	 */
	public function getElapsedTime() : float {
		return $this->stats->getElapsedSeconds();
	}

	/**
	 * Returns the average processing time per completed job in microseconds.
	 */
	public function getAverageJobTimeUs() : float {
		return $this->stats->getAverageJobTimeUs();
	}

	/**
	 * Returns the processing time of the most recent completed job in microseconds.
	 */
	public function getLastJobTimeUs() : int {
		return $this->stats->lastJobTimeUs;
	}

	/**
	 * Returns the minimum single-job processing time in microseconds.
	 */
	public function getMinJobTimeUs() : int {
		return $this->stats->minJobTimeUs;
	}

	/**
	 * Returns the maximum single-job processing time in microseconds.
	 */
	public function getMaxJobTimeUs() : int {
		return $this->stats->maxJobTimeUs;
	}

	/**
	 * Returns the current throughput in jobs per second.
	 */
	public function getJobsPerSecond() : float {
		return $this->stats->getJobsPerSecond();
	}

	/**
	 * Returns the native process/thread ID reported by the child thread.
	 * Returns 0 if the thread has not started yet.
	 */
	public function getNativeThreadId() : int {
		return $this->stats->nativeThreadId;
	}

	/**
	 * Returns the number of jobs currently queued across all workers in this thread.
	 */
	public function getCurrentQueuedJobs() : int {
		return $this->stats->currentQueuedJobs;
	}

	/**
	 * Returns the number of workers in this thread.
	 */
	public function getWorkerCount() : int {
		return $this->stats->workerCount;
	}

	/**
	 * Returns the maximum capacity per worker.
	 */
	public function getWorkerCapacity() : int {
		return $this->stats->workerCapacity;
	}

	/**
	 * Returns how many more jobs this thread's workers can accept.
	 */
	public function getAvailableCapacity() : int {
		return $this->stats->getAvailableCapacity();
	}

	/**
	 * Returns the total capacity across all workers in this thread.
	 */
	public function getTotalCapacity() : int {
		return $this->stats->getTotalCapacity();
	}

	/**
	 * Returns whether the thread is currently running its main processing loop.
	 */
	public function isRunning() : bool {
		return $this->stats->running;
	}
}
