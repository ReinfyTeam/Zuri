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
use function usleep;

final class CheckThread extends Thread {
	public function __construct(
		private CheckQueue $jobs,
		private CheckResults $results,
		private int $workerCount,
		private int $workerCapacity
	) {
		$this->start();
	}

	protected function onRun() : void {
		/** @var CheckWorker[] $workers */
		$workers = [];
		for ($i = 0; $i < $this->workerCount; ++$i) {
			$workers[] = new CheckWorker($this->results, $this->workerCapacity);
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
					$queued = true;
				}
			}

			$processed = false;
			foreach ($workers as $worker) {
				$processed = $worker->processNext() || $processed;
			}

			if (!$queued && !$processed) {
				usleep(1000);
			}
		}
	}

	public function getResults() : CheckResults {
		return $this->results;
	}
}
