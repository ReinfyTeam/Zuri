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

namespace ReinfyTeam\Zuri\checks;

use function max;
use function microtime;
use function min;
use function round;

/**
 * Represents a violation result with confidence scoring.
 *
 * Instead of binary pass/fail, violations now carry a confidence score (0.0-1.0)
 * indicating how certain we are that this is a real cheat vs false positive.
 *
 * Confidence factors:
 * - Base confidence from detection logic
 * - Ping adjustment (higher ping = lower confidence)
 * - Online time adjustment (new players = lower confidence)
 * - Multiple violations correlation (repeated = higher confidence)
 * - Environmental factors (lag, chunk loading, etc.)
 */
class ViolationResult {
	public const CONFIDENCE_VERY_LOW = 0.2;
	public const CONFIDENCE_LOW = 0.4;
	public const CONFIDENCE_MEDIUM = 0.6;
	public const CONFIDENCE_HIGH = 0.8;
	public const CONFIDENCE_CERTAIN = 1.0;

	private float $baseConfidence;
	private float $adjustedConfidence;
	private string $checkName;
	private string $subType;
	private string $debugInfo;
	private float $timestamp;

	/** @var array<string, float> */
	private array $factors = [];

	/**
	 * Creates a new violation result with an initial confidence score.
	 *
	 * @param string $checkName Name of the check that generated the violation.
	 * @param string $subType Subtype/variant identifier of the check.
	 * @param float $baseConfidence Initial confidence from raw detection logic.
	 * @param string $debugInfo Optional debug context associated with the violation.
	 * @return void
	 */
	public function __construct(
		string $checkName,
		string $subType,
		float $baseConfidence = self::CONFIDENCE_MEDIUM,
		string $debugInfo = ""
	) {
		$this->checkName = $checkName;
		$this->subType = $subType;
		$this->baseConfidence = self::clamp($baseConfidence);
		$this->adjustedConfidence = $this->baseConfidence;
		$this->debugInfo = $debugInfo;
		$this->timestamp = microtime(true);
	}

	/**
	 * Apply ping-based confidence adjustment.
	 * Higher ping = lower confidence (more likely false positive).
	 *
	 * @param int $ping Player ping in milliseconds.
	 * @return self Current instance for fluent chaining.
	 */
	public function applyPingFactor(int $ping) : self {
		$factor = match (true) {
			$ping < 50 => 1.0,
			$ping < 100 => 0.95,
			$ping < 150 => 0.85,
			$ping < 250 => 0.7,
			$ping < 400 => 0.5,
			default => 0.3,
		};
		$this->factors['ping'] = $factor;
		$this->recalculate();
		return $this;
	}

	/**
	 * Apply online time factor.
	 * New players get benefit of doubt (lower confidence).
	 *
	 * @param int $onlineTimeTicks Player online time in server ticks.
	 * @return self Current instance for fluent chaining.
	 */
	public function applyOnlineTimeFactor(int $onlineTimeTicks) : self {
		$factor = match (true) {
			$onlineTimeTicks < 100 => 0.5,   // <5 seconds
			$onlineTimeTicks < 400 => 0.7,   // <20 seconds
			$onlineTimeTicks < 1200 => 0.85, // <1 minute
			$onlineTimeTicks < 6000 => 0.95, // <5 minutes
			default => 1.0,
		};
		$this->factors['onlineTime'] = $factor;
		$this->recalculate();
		return $this;
	}

	/**
	 * Apply repeated violations factor.
	 * Multiple violations in short time = higher confidence.
	 *
	 * @param int $recentViolations Number of recent matching violations.
	 * @return self Current instance for fluent chaining.
	 */
	public function applyRepeatFactor(int $recentViolations) : self {
		$factor = match ($recentViolations) {
			0 => 1.0,
			1 => 1.1,
			2 => 1.2,
			default => 1.3,
		};
		$this->factors['repeat'] = $factor;
		$this->recalculate();
		return $this;
	}

	/**
	 * Apply environmental stability factor.
	 * Unstable conditions = lower confidence.
	 *
	 * @param bool $chunkLoaded Whether the relevant chunk was loaded.
	 * @param bool $recentTeleport Whether the player recently teleported.
	 * @param bool $recentDamage Whether the player recently took damage.
	 * @param bool $serverLagging Whether the server is currently lagging.
	 * @return self Current instance for fluent chaining.
	 */
	public function applyEnvironmentFactor(
		bool $chunkLoaded,
		bool $recentTeleport,
		bool $recentDamage,
		bool $serverLagging = false
	) : self {
		$factor = 1.0;
		if (!$chunkLoaded) {
			$factor *= 0.5;
		}
		if ($recentTeleport) {
			$factor *= 0.6;
		}
		if ($recentDamage) {
			$factor *= 0.8;
		}
		if ($serverLagging) {
			$factor *= 0.4;
		}
		$this->factors['environment'] = $factor;
		$this->recalculate();
		return $this;
	}

	/**
	 * Apply a custom factor.
	 *
	 * @param string $name Custom factor key.
	 * @param float $factor Multiplicative factor value.
	 * @return self Current instance for fluent chaining.
	 */
	public function applyCustomFactor(string $name, float $factor) : self {
		$this->factors[$name] = $factor;
		$this->recalculate();
		return $this;
	}

	/**
	 * Recomputes adjusted confidence after factor changes.
	 */
	private function recalculate() : void {
		$multiplier = 1.0;
		foreach ($this->factors as $factor) {
			$multiplier *= $factor;
		}
		$this->adjustedConfidence = self::clamp($this->baseConfidence * $multiplier);
	}

	/**
	 * Clamps confidence values into the valid range.
	 *
	 * @param float $value Confidence value to clamp.
	 * @return float Clamped confidence between 0.0 and 1.0.
	 */
	private static function clamp(float $value) : float {
		return max(0.0, min(1.0, $value));
	}

	/**
	 * Returns the unadjusted confidence score.
	 *
	 * @return float Base confidence value.
	 */
	public function getBaseConfidence() : float {
		return $this->baseConfidence;
	}

	/**
	 * Returns the adjusted confidence score.
	 *
	 * @return float Confidence after all factors are applied.
	 */
	public function getConfidence() : float {
		return $this->adjustedConfidence;
	}

	/**
	 * Returns the adjusted confidence as a percentage.
	 *
	 * @return int Confidence percentage rounded to the nearest whole number.
	 */
	public function getConfidencePercent() : int {
		return (int) round($this->adjustedConfidence * 100);
	}

	/**
	 * Returns the check name associated with this result.
	 *
	 * @return string Check name.
	 */
	public function getCheckName() : string {
		return $this->checkName;
	}

	/**
	 * Returns the check subtype associated with this result.
	 *
	 * @return string Check subtype.
	 */
	public function getSubType() : string {
		return $this->subType;
	}

	/**
	 * Returns debug information attached to this result.
	 *
	 * @return string Debug information string.
	 */
	public function getDebugInfo() : string {
		return $this->debugInfo;
	}

	/**
	 * Returns the creation timestamp for this result.
	 *
	 * @return float Unix timestamp with microseconds.
	 */
	public function getTimestamp() : float {
		return $this->timestamp;
	}

	/**
	 * Returns all applied confidence factors.
	 *
	 * @return array<string, float>
	 */
	public function getFactors() : array {
		return $this->factors;
	}

	/**
	 * Check if confidence meets threshold for action.
	 *
	 * @param float $threshold Required confidence threshold.
	 * @return bool True when adjusted confidence is at least the threshold.
	 */
	public function meetsThreshold(float $threshold) : bool {
		return $this->adjustedConfidence >= $threshold;
	}

	/**
	 * Check if this is high-confidence (likely real cheat).
	 *
	 * @return bool True when confidence is at least CONFIDENCE_HIGH.
	 */
	public function isHighConfidence() : bool {
		return $this->meetsThreshold(self::CONFIDENCE_HIGH);
	}

	/**
	 * Check if this is low-confidence (likely false positive).
	 *
	 * @return bool True when confidence is below CONFIDENCE_MEDIUM.
	 */
	public function isLowConfidence() : bool {
		return $this->adjustedConfidence < self::CONFIDENCE_MEDIUM;
	}

	/**
	 * Serialize for logging/storage.
	 *
	 * @return array{check:string, subType:string, baseConfidence:float, confidence:float, factors:array<string,float>, debug:string, timestamp:float} Serialized violation payload.
	 */
	public function toArray() : array {
		return [
			'check' => $this->checkName,
			'subType' => $this->subType,
			'baseConfidence' => $this->baseConfidence,
			'confidence' => $this->adjustedConfidence,
			'factors' => $this->factors,
			'debug' => $this->debugInfo,
			'timestamp' => $this->timestamp,
		];
	}
}
