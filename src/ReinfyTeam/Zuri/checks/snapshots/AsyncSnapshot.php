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
use ReinfyTeam\Zuri\lang\Lang;
use ReinfyTeam\Zuri\lang\LangKeys;
use function array_key_exists;
use function gettype;
use function implode;
use function is_array;
use function is_null;
use function is_numeric;
use function is_scalar;
use function is_string;
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
	 * Schema version for this snapshot type. Increment when fields change.
	 * Subclasses must define their own SCHEMA_VERSION constant.
	 */
	public const SCHEMA_VERSION = 1;

	/**
	 * The check type identifier (e.g., "FlyA", "ReachD", "SpeedB").
	 * Used to validate the payload in evaluateAsync() methods.
	 */
	protected string $checkType;

	/** Timestamp when snapshot was captured. */
	protected float $captureTime;

	/**
	 * Creates a new async snapshot with the given check type.
	 *
	 * @param string $checkType Check type identifier for downstream validation.
	 * @return void
	 */
	public function __construct(string $checkType) {
		$this->checkType = $checkType;
		$this->captureTime = microtime(true);
	}

	/**
	 * Get the check type identifier.
	 *
	 * @return string Snapshot check type identifier.
	 */
	public function getCheckType() : string {
		return $this->checkType;
	}

	/**
	 * Get the capture timestamp.
	 *
	 * @return float Snapshot capture time as a Unix timestamp with microseconds.
	 */
	public function getCaptureTime() : float {
		return $this->captureTime;
	}

	/**
	 * Build the complete payload array for async dispatch.
	 * Must return only JSON-serializable values.
	 *
	 * @return array<string,mixed>
	 */
	abstract public function build() : array;

	/**
	 * Validate that required snapshot fields are present.
	 * Should throw if validation fails.
	 *
	 * @throws SnapshotException If snapshot data is invalid.
	 */
	abstract public function validate() : void;

	/**
	 * JsonSerializable interface implementation.
	 *
	 * @return mixed JSON-serializable snapshot payload.
	 */
	public function jsonSerialize() : mixed {
		return $this->build();
	}

	/**
	 * Validate schema version from a deserialized payload.
	 * Call this at the start of evaluateAsync() to prevent desync.
	 *
	 * @param array<string,mixed> $payload The deserialized payload
	 * @param int $expectedVersion The expected schema version
	 * @return bool True if version matches, false if mismatch
	 */
	public static function validateSchemaVersion(array $payload, int $expectedVersion) : bool {
		$version = $payload['schemaVersion'] ?? 0;
		return $version === $expectedVersion;
	}

	/**
	 * Assert that a payload has the expected schema version.
	 * Throws SnapshotException if version mismatches.
	 *
	 * @param array<string,mixed> $payload The deserialized payload
	 * @param int $expectedVersion The expected schema version
	 * @throws SnapshotException If version doesn't match
	 */
	public static function assertSchemaVersion(array $payload, int $expectedVersion) : void {
		$version = $payload['schemaVersion'] ?? 0;
		if ($version !== $expectedVersion) {
			$versionString = is_numeric($version) ? (string) $version : gettype($version);
			throw new SnapshotException(
				Lang::get(LangKeys::DEBUG_SNAPSHOT_SCHEMA_MISMATCH, [
					"expected" => $expectedVersion,
					"actual" => $versionString,
				])
			);
		}
	}

	/**
	 * Validate that required fields exist in a payload.
	 *
	 * @param array<string,mixed> $payload The payload to validate
	 * @param string[] $requiredFields List of required field names
	 * @throws SnapshotException If any required field is missing
	 */
	public static function assertRequiredFields(array $payload, array $requiredFields) : void {
		$missing = [];
		foreach ($requiredFields as $field) {
			if (!array_key_exists($field, $payload)) {
				$missing[] = $field;
			}
		}
		if ($missing !== []) {
			throw new SnapshotException(
				Lang::get(LangKeys::DEBUG_SNAPSHOT_MISSING_FIELDS, [
					"fields" => implode(", ", $missing),
				])
			);
		}
	}

	/**
	 * Validate that a numeric field is within bounds.
	 *
	 * @param mixed $value The value to check
	 * @param float $min Minimum allowed value
	 * @param float $max Maximum allowed value
	 * @param string $fieldName Name for error message
	 * @throws SnapshotException If value is out of bounds
	 */
	public static function assertBounds(mixed $value, float $min, float $max, string $fieldName) : void {
		if (!is_numeric($value)) {
			throw new SnapshotException(Lang::get(LangKeys::DEBUG_SNAPSHOT_NON_NUMERIC_FIELD, [
				"field" => $fieldName,
				"type" => gettype($value),
			]));
		}
		if ($value < $min || $value > $max) {
			throw new SnapshotException(
				Lang::get(LangKeys::DEBUG_SNAPSHOT_BOUNDS_FIELD, [
					"field" => $fieldName,
					"value" => $value,
					"min" => $min,
					"max" => $max,
				])
			);
		}
	}

	/**
	 * Assert that a payload matches the expected type, schema, and required fields.
	 *
	 * @param array<string,mixed> $payload The payload to validate
	 * @param string $expectedType Expected payload type
	 * @param int $expectedVersion Expected schema version
	 * @param string[] $requiredFields Required field names
	 * @param array<string,array{0:float,1:float}> $boundedFields Field bounds map (field => [min,max])
	 * @throws SnapshotException If any assertion fails
	 */
	public static function assertPayload(
		array $payload,
		string $expectedType,
		int $expectedVersion,
		array $requiredFields = [],
		array $boundedFields = []
	) : void {
		if (($payload['type'] ?? null) !== $expectedType) {
			$actualTypeValue = $payload['type'] ?? 'unknown';
			$actualType = is_string($actualTypeValue) ? $actualTypeValue : gettype($actualTypeValue);
			throw new SnapshotException(Lang::get(LangKeys::DEBUG_SNAPSHOT_TYPE_MISMATCH, [
				"expected" => $expectedType,
				"actual" => $actualType,
			]));
		}

		self::assertSchemaVersion($payload, $expectedVersion);
		self::assertRequiredFields($payload, $requiredFields);

		foreach ($boundedFields as $field => $bounds) {
			self::assertBounds($payload[$field] ?? null, $bounds[0], $bounds[1], $field);
		}
	}

	/**
	 * Safe payload validation helper for evaluateAsync() methods.
	 * Returns false instead of throwing on malformed payloads.
	 *
	 * @param array<string,mixed> $payload The payload to validate
	 * @param string $expectedType Expected payload type
	 * @param int $expectedVersion Expected schema version
	 * @param string[] $requiredFields Required field names
	 * @param array<string,array{0:float,1:float}> $boundedFields Field bounds map (field => [min,max])
	 * @return bool True when payload validation succeeds.
	 */
	public static function validatePayload(
		array $payload,
		string $expectedType,
		int $expectedVersion,
		array $requiredFields = [],
		array $boundedFields = []
	) : bool {
		try {
			self::assertPayload($payload, $expectedType, $expectedVersion, $requiredFields, $boundedFields);
			return true;
		} catch (SnapshotException) {
			return false;
		}
	}

	/**
	 * Sanitizes a value for thread-safe payload transport.
	 *
	 * Keeps only scalar/null values and recursively sanitized arrays.
	 * Any object/resource/unsupported value is replaced with null.
	 */
	public static function sanitizeSerializableValue(mixed $value, int $depth = 0) : mixed {
		if ($depth > 16) {
			return null;
		}

		if (is_null($value) || is_scalar($value)) {
			return $value;
		}

		if (is_array($value)) {
			$sanitized = [];
			foreach ($value as $key => $nestedValue) {
				$sanitized[$key] = self::sanitizeSerializableValue($nestedValue, $depth + 1);
			}
			return $sanitized;
		}

		return null;
	}

	/**
	 * Sanitizes a payload array for thread-safe transport.
	 *
	 * @param array<string,mixed> $payload
	 * @return array<string,mixed>
	 */
	public static function sanitizeSerializablePayload(array $payload) : array {
		$sanitized = [];
		foreach ($payload as $key => $value) {
			if (!is_string($key)) {
				continue;
			}
			$sanitized[$key] = self::sanitizeSerializableValue($value);
		}
		return $sanitized;
	}

	/**
	 * Validate payload or throw SnapshotException if invalid
	 *
	 * @param array<string,mixed> $payload
	 * @throws SnapshotException
	 */
	public static function validatePayloadOrThrow(array $payload) : void {
		$sanitized = self::sanitizeSerializablePayload($payload);
		$json = json_encode($sanitized);
		if ($json === false) {
			throw new SnapshotException('Payload is not JSON serializable');
		}
	}
}
