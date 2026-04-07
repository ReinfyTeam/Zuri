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

namespace ReinfyTeam\Zuri\checks\snapshots;

use JsonSerializable;
use function microtime;

/**
 * Base class for async check payload snapshots.
 *
 * This class provides a standardized way to capture player state for async
 * worker evaluation. Snapshots must be JSON-serializable and contain only
 * immutable, serializable data (no Player objects, closures, etc.).
 *
 * Pattern:
 *   1. Capture snapshot on main thread with immutable data
 *   2. Serialize to JSON for worker thread
 *   3. Worker evaluates and returns result dict
 *   4. Main thread applies result atomically
 */
abstract class AsyncSnapshot implements JsonSerializable {
	/**
	 * The check type identifier (e.g., "FlyA", "ReachD", "SpeedB").
	 * Used to validate the payload in evaluateAsync() methods.
	 */
	protected string $checkType;

	/** Timestamp when snapshot was captured. */
	protected float $captureTime;

	public function __construct(string $checkType) {
		$this->checkType = $checkType;
		$this->captureTime = microtime(true);
	}

	/**
	 * Get the check type identifier.
	 */
	public function getCheckType() : string {
		return $this->checkType;
	}

	/**
	 * Get the capture timestamp.
	 */
	public function getCaptureTime() : float {
		return $this->captureTime;
	}

	/**
	 * Build the complete payload array for async dispatch.
	 * Must return only JSON-serializable values.
	 */
	abstract public function build() : array;

	/**
	 * Validate that required snapshot fields are present.
	 * Should throw if validation fails.
	 */
	abstract public function validate() : void;

	/**
	 * JsonSerializable interface implementation.
	 */
	public function jsonSerialize() : mixed {
		return $this->build();
	}
}
