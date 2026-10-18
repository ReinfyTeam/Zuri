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

use pmmp\thread\ThreadSafe;
use function max;
use function microtime;

/**
 * Thread-safe container for performance statistics of a single CheckThread.
 *
 * Written from inside onRun() (the child thread), read from the main thread.
 * All scalar properties on a ThreadSafe object are individually atomic in pmmpthread,
 * so no explicit synchronized() is needed for simple reads/writes.
 *
 * @disregard this class were not on made in php
 */
final class CheckThreadStats extends ThreadSafe {
	/** Total number of jobs ever submitted to workers in this thread. */
	public int $totalJobsSubmitted = 0;

	/** Number of jobs that completed successfully (returned results). */
	public int $completedJobs = 0;

	/** Number of jobs that failed (threw an exception during processing). */
	public int $failedJobs = 0;

	/** Cumulative processing time of all completed jobs in microseconds. */
	public int $totalProcessingTimeUs = 0;

	/** Processing time of the most recent completed job in microseconds. */
	public int $lastJobTimeUs = 0;

	/** Minimum single-job processing time in microseconds (0 = no jobs yet). */
	public int $minJobTimeUs = 0;

	/** Maximum single-job processing time in microseconds. */
	public int $maxJobTimeUs = 0;

	/** Number of workers in this thread. */
	public int $workerCount = 0;

	/** Maximum capacity per worker. */
	public int $workerCapacity = 0;

	/** Current number of queued jobs across all workers (snapshot, may be stale). */
	public int $currentQueuedJobs = 0;

	/** The native thread ID (set once from inside onRun). */
	public int $nativeThreadId = 0;

	/** Timestamp (microtime) when this thread started running. */
	public float $startTimeSeconds = 0.0;

	/** Whether the thread is still running its main loop. */
	public bool $running = false;

	/**
	 * Records a successful job completion with its elapsed microseconds.
	 * Called from inside the worker thread.
	 */
	public function recordSuccess(int $elapsedUs) : void {
		$this->completedJobs++;
		$this->totalProcessingTimeUs += $elapsedUs;
		$this->lastJobTimeUs = $elapsedUs;
		if ($this->minJobTimeUs === 0 || $elapsedUs < $this->minJobTimeUs) {
			$this->minJobTimeUs = $elapsedUs;
		}
		if ($elapsedUs > $this->maxJobTimeUs) {
			$this->maxJobTimeUs = $elapsedUs;
		}
	}

	/**
	 * Records a failed job.
	 * Called from inside the worker thread.
	 */
	public function recordFailure() : void {
		$this->failedJobs++;
	}

	/**
	 * Returns the average processing time per completed job in microseconds.
	 * Returns 0.0 if no jobs have completed.
	 */
	public function getAverageJobTimeUs() : float {
		return $this->completedJobs > 0
			? $this->totalProcessingTimeUs / $this->completedJobs
			: 0.0;
	}

	/**
	 * Returns the elapsed time in seconds since the thread started.
	 * Returns 0.0 if the thread has not started yet.
	 */
	public function getElapsedSeconds() : float {
		if ($this->startTimeSeconds <= 0.0) {
			return 0.0;
		}
		return microtime(true) - $this->startTimeSeconds;
	}

	/**
	 * Returns the throughput in jobs per second (completed jobs / elapsed time).
	 * Returns 0.0 if the thread has not started yet or no time has elapsed.
	 */
	public function getJobsPerSecond() : float {
		$elapsed = $this->getElapsedSeconds();
		return $elapsed > 0.0 ? $this->completedJobs / $elapsed : 0.0;
	}

	/**
	 * Returns how many more jobs workers can accept across all workers in this thread.
	 * Calculated as (workerCount * workerCapacity) - currentQueuedJobs.
	 */
	public function getAvailableCapacity() : int {
		$total = $this->workerCount * $this->workerCapacity;
		return max(0, $total - $this->currentQueuedJobs);
	}

	/**
	 * Returns the total capacity across all workers in this thread.
	 */
	public function getTotalCapacity() : int {
		return $this->workerCount * $this->workerCapacity;
	}
}

