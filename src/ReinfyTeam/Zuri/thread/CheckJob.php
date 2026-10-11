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

use ReinfyTeam\Zuri\check\Check;
use function is_a;
use function is_array;
use function is_string;
use function serialize;
use function unserialize;

final class CheckJob {
	/**
	 * @param class-string<Check> $check
	 * @param array<string,mixed> $data
	 */
	public function __construct(
		private string $check,
		private array $data
	) {
	}

	public function serialize() : string {
		return serialize([
			"check" => $this->check,
			"data" => $this->data
		]);
	}

	/** @return class-string<Check> */
	public function getCheck() : string {
		return $this->check;
	}

	/** @return array<string,mixed> */
	public function getData() : array {
		return $this->data;
	}

	public static function unserialize(string $serialized) : self {
		$job = unserialize($serialized, ["allowed_classes" => false]);
		if (
			!is_array($job) ||
			!isset($job["check"], $job["data"]) ||
			!is_string($job["check"]) ||
			!is_array($job["data"]) ||
			!is_a($job["check"], Check::class, true)
		) {
			throw new \UnexpectedValueException("Invalid serialized check job");
		}
		$data = [];
		foreach ($job["data"] as $key => $value) {
			if (!is_string($key)) {
				throw new \UnexpectedValueException("Invalid check payload key");
			}
			$data[$key] = $value;
		}
		return new self($job["check"], $data);
	}
}
