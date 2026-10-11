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

use pmmp\thread\ThreadSafeArray;
use Throwable;
use function count;
use function is_array;
use function is_string;

final class CheckWorker {
	private ThreadSafeArray $jobs;

	public function __construct(
		private CheckResults $results,
		private int $capacity
	) {
		$this->jobs = new ThreadSafeArray();
	}

	public function enqueue(string $serializedJob) : bool {
		if (!$this->canAccept()) {
			return false;
		}
		$this->jobs[] = $serializedJob;
		return true;
	}

	public function canAccept() : bool {
		return count($this->jobs) < $this->capacity;
	}

	public function getQueueSize() : int {
		return count($this->jobs);
	}

	public function processNext() : bool {
		$serializedJob = $this->jobs->shift();
		if (!is_string($serializedJob)) {
			return false;
		}

		try {
			$job = CheckJob::unserialize($serializedJob);
		} catch (Throwable $e) {
			$this->results->addResult([
				"error" => [
					"message" => $e->getMessage(),
					"file" => $e->getFile(),
					"line" => $e->getLine(),
					"trace" => $e->getTraceAsString()
				]
			], CheckJob::class, null);
			return true;
		}

		$this->runJob($job);
		return true;
	}

	/** @param array<string,mixed> $data */
	private static function getPlayerName(array $data) : ?string {
		$playerData = $data["playerData"] ?? null;
		$name = is_array($playerData) ? ($playerData["name"] ?? null) : null;
		return is_string($name) ? $name : null;
	}

	private function runJob(CheckJob $job) : void {
		try {
			$check = $job->getCheck();
			$data = $job->getData();
			$result = $check::check($data);
			$player = self::getPlayerName($data);
			$this->results->addResult($result, $check, $player);
		} catch(Throwable $e) {
			$data = isset($data) ? $data : [];
			$this->results->addResult([
				"error" => [
					"message" => $e->getMessage(),
					"file" => $e->getFile(),
					"line" => $e->getLine(),
					"trace" => $e->getTraceAsString()
				]
			], $job->getCheck(), self::getPlayerName($data));
		}
	}
}
