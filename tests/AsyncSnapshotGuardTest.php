<?php

declare(strict_types=1);

namespace ReinfyTeam\Zuri\Tests;

require_once __DIR__ . '/../vendor/autoload.php';

use ReinfyTeam\Zuri\checks\snapshots\AsyncSnapshot;
use ReinfyTeam\Zuri\checks\snapshots\SnapshotException;

use function count;
use function php_sapi_name;
use function sprintf;

/**
 * Tests strict payload guard helpers for async snapshots.
 */
final class AsyncSnapshotGuardTest {
	/** @var list<string> */
	private array $failures = [];

	public function getFailureCount() : int {
		return count($this->failures);
	}

	public function run() : void {
		$this->testValidatePayloadAcceptsValidPayload();
		$this->testValidatePayloadRejectsInvalidSchema();
		$this->testValidatePayloadRejectsMissingFields();
		$this->testAssertPayloadThrowsOnOutOfBounds();

		if ($this->failures === []) {
			echo "AsyncSnapshotGuardTest: PASS\n";
			return;
		}

		echo "AsyncSnapshotGuardTest: FAIL\n";
		foreach ($this->failures as $failure) {
			echo " - {$failure}\n";
		}
	}

	private function testValidatePayloadAcceptsValidPayload() : void {
		$payload = [
			"type" => "TestCheck",
			"schemaVersion" => 1,
			"tick" => 42,
		];

		$ok = AsyncSnapshot::validatePayload(
			$payload,
			"TestCheck",
			1,
			["type", "schemaVersion", "tick"],
			["tick" => [0.0, 200.0]]
		);

		if (!$ok) {
			$this->fail("validatePayload() rejected a valid payload");
		}
	}

	private function testValidatePayloadRejectsInvalidSchema() : void {
		$payload = [
			"type" => "TestCheck",
			"schemaVersion" => 999,
			"tick" => 42,
		];

		$ok = AsyncSnapshot::validatePayload(
			$payload,
			"TestCheck",
			1,
			["type", "schemaVersion", "tick"]
		);

		if ($ok) {
			$this->fail("validatePayload() accepted mismatched schema version");
		}
	}

	private function testValidatePayloadRejectsMissingFields() : void {
		$payload = [
			"type" => "TestCheck",
			"schemaVersion" => 1,
		];

		$ok = AsyncSnapshot::validatePayload(
			$payload,
			"TestCheck",
			1,
			["type", "schemaVersion", "tick"]
		);

		if ($ok) {
			$this->fail("validatePayload() accepted payload with missing fields");
		}
	}

	private function testAssertPayloadThrowsOnOutOfBounds() : void {
		$payload = [
			"type" => "TestCheck",
			"schemaVersion" => 1,
			"tick" => 500,
		];

		try {
			AsyncSnapshot::assertPayload(
				$payload,
				"TestCheck",
				1,
				["type", "schemaVersion", "tick"],
				["tick" => [0.0, 200.0]]
			);
			$this->fail("assertPayload() did not throw on out-of-bounds value");
		} catch (SnapshotException) {
			// expected
		}
	}

	private function fail(string $message) : void {
		$this->failures[] = $message;
	}
}

if (php_sapi_name() === 'cli') {
	$test = new AsyncSnapshotGuardTest();
	$test->run();

	if ($test->getFailureCount() > 0) {
		exit(1);
	}
}
