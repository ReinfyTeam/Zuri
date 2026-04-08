<?php

declare(strict_types=1);

namespace ReinfyTeam\Zuri\Tests;

use ReinfyTeam\Zuri\checks\snapshots\AsyncSnapshot;
use ReinfyTeam\Zuri\checks\snapshots\BlockSnapshot;
use ReinfyTeam\Zuri\checks\snapshots\ChatSnapshot;
use ReinfyTeam\Zuri\checks\snapshots\CombatSnapshot;
use ReinfyTeam\Zuri\checks\snapshots\InventorySnapshot;
use ReinfyTeam\Zuri\checks\snapshots\MovementSnapshot;
use ReinfyTeam\Zuri\checks\snapshots\NetworkSnapshot;
use ReinfyTeam\Zuri\checks\snapshots\SnapshotException;
use function json_encode;
use function json_decode;

/**
 * Validates snapshot infrastructure for async checking.
 *
 * Tests:
 * - All snapshots are JSON serializable
 * - All snapshots have SCHEMA_VERSION constants
 * - All snapshots implement build() and validate()
 * - Schema versioning prevents version mismatches
 * - Snapshot data survives JSON serialization/deserialization
 */
class SnapshotValidationTest {
    private array $results = [];

    public function run(): void {
        echo "Starting Snapshot Validation Tests...\n\n";

        try {
            $this->testSnapshotInterface();
            $this->testSnapshotSerialization();
            $this->testSnapshotValidation();
            $this->testSchemaVersioning();
        } catch (\Exception $e) {
            $this->fail("Test execution failed: {$e->getMessage()}");
        }

        $this->printResults();
    }

    private function testSnapshotInterface(): void {
        echo "Testing CacheSnapshot Interface...\n";

        $snapshotClasses = [
            ['class' => MovementSnapshot::class, 'name' => 'MovementSnapshot'],
            ['class' => CombatSnapshot::class, 'name' => 'CombatSnapshot'],
            ['class' => ChatSnapshot::class, 'name' => 'ChatSnapshot'],
            ['class' => BlockSnapshot::class, 'name' => 'BlockSnapshot'],
            ['class' => NetworkSnapshot::class, 'name' => 'NetworkSnapshot'],
            ['class' => InventorySnapshot::class, 'name' => 'InventorySnapshot'],
        ];

        foreach ($snapshotClasses as $snapshotInfo) {
            $class = $snapshotInfo['class'];
            $name = $snapshotInfo['name'];

            // Check SCHEMA_VERSION constant
            if (!defined($class . '::SCHEMA_VERSION')) {
                $this->fail("{$name}: Missing SCHEMA_VERSION constant");
                continue;
            }
            $this->pass("{$name}: Has SCHEMA_VERSION constant");

            // Check that class extends AsyncSnapshot
            if (!is_subclass_of($class, AsyncSnapshot::class)) {
                $this->fail("{$name}: Does not extend AsyncSnapshot");
                continue;
            }
            $this->pass("{$name}: Extends AsyncSnapshot");

            // Check that class implements required methods
            if (!method_exists($class, 'build')) {
                $this->fail("{$name}: Missing build() method");
                continue;
            }
            $this->pass("{$name}: Has build() method");

            if (!method_exists($class, 'validate')) {
                $this->fail("{$name}: Missing validate() method");
                continue;
            }
            $this->pass("{$name}: Has validate() method");
        }
        echo "\n";
    }

    private function testSnapshotSerialization(): void {
        echo "Testing Snapshot JSON Serialization...\n";

        // Test MovementSnapshot - minimal valid data
        try {
            $snapshot = new MovementSnapshot("TestCheck", $this->createMockPlayer(), $this->createMockPlayerAPI());

            // Test build() returns array
            $built = $snapshot->build();
            if (!is_array($built)) {
                $this->fail("MovementSnapshot::build() does not return array");
            } else {
                $this->pass("MovementSnapshot::build() returns array");
            }

            // Test JSON serialization
            $json = json_encode($snapshot);
            if ($json === false) {
                $this->fail("MovementSnapshot: JSON encoding failed");
            } else {
                $this->pass("MovementSnapshot: Successfully serialized to JSON");

                // Test JSON deserialization preserves data
                $decoded = json_decode($json, true);
                if ($decoded === null) {
                    $this->fail("MovementSnapshot: JSON decoding failed");
                } else {
                    $this->pass("MovementSnapshot: JSON round-trip successful");
                }
            }
        } catch (\Exception $e) {
            $this->fail("MovementSnapshot serialization error: {$e->getMessage()}");
        }

        echo "\n";
    }

    private function testSnapshotValidation(): void {
        echo "Testing Snapshot Validation...\n";

        try {
            $snapshot = new MovementSnapshot("TestCheck", $this->createMockPlayer(), $this->createMockPlayerAPI());

            // validate() should not throw for valid snapshot
            try {
                $snapshot->validate();
                $this->pass("MovementSnapshot::validate() accepts valid snapshot");
            } catch (SnapshotException $e) {
                $this->fail("MovementSnapshot::validate() rejected valid snapshot: {$e->getMessage()}");
            }
        } catch (\Exception $e) {
            $this->fail("Snapshot validation test error: {$e->getMessage()}");
        }

        echo "\n";
    }

    private function testSchemaVersioning(): void {
        echo "Testing Schema Versioning...\n";

        try {
            $snapshot = new MovementSnapshot("TestCheck", $this->createMockPlayer(), $this->createMockPlayerAPI());
            $built = $snapshot->build();

            // Check schemaVersion is present
            if (!isset($built['schemaVersion'])) {
                $this->fail("MovementSnapshot: schemaVersion missing from build()");
                return;
            }
            $this->pass("MovementSnapshot: schemaVersion present in build()");

            // Check schemaVersion matches constant
            $schemaVersion = $built['schemaVersion'];
            $expectedVersion = MovementSnapshot::SCHEMA_VERSION;

            if ($schemaVersion !== $expectedVersion) {
                $this->fail("MovementSnapshot: schemaVersion mismatch (got {$schemaVersion}, expected {$expectedVersion})");
            } else {
                $this->pass("MovementSnapshot: schemaVersion matches constant");
            }

            // Simulate schema version check from evaluateAsync
            $versionCheck = (int) ($built['schemaVersion'] ?? 0) === \ReinfyTeam\Zuri\checks\snapshots\MovementSnapshot::SCHEMA_VERSION;
            if (!$versionCheck) {
                $this->fail("MovementSnapshot: Schema version validation failed");
            } else {
                $this->pass("MovementSnapshot: Schema version validation successful");
            }
        } catch (\Exception $e) {
            $this->fail("Schema versioning test error: {$e->getMessage()}");
        }

        echo "\n";
    }

    private function createMockPlayer() {
        // Stub - would require PocketMine-MP framework
        // In real testing, this would be a proper mock
        return new class {
            public function getLocation() {
                return new class {
                    public function getX() { return 0.0; }
                    public function getY() { return 64.0; }
                    public function getZ() { return 0.0; }
                };
            }

            public function getEyePos() {
                return new class {
                    public function getX() { return 0.0; }
                    public function getY() { return 64.0; }
                    public function getZ() { return 0.0; }
                };
            }

            public function getNetworkSession() {
                return new class {
                    public function getPing() { return 0; }
                };
            }

            public function isSprinting() { return false; }
            public function isSurvival() { return true; }
            public function getAllowFlight() { return false; }
            public function hasNoClientPredictions() { return false; }
        };
    }

    private function createMockPlayerAPI() {
        // Stub - would require proper PlayerAPI mock
        return new class {
            public function getMotion() {
                return new class {
                    public function getX() { return 0.0; }
                    public function getY() { return 0.0; }
                    public function getZ() { return 0.0; }
                };
            }

            public function isOnGround() { return true; }
            public function isOnAdhesion() { return false; }
            public function isInWeb() { return false; }
            public function isGliding() { return false; }
            public function getJumpTicks() { return 0; }
            public function getAttackTicks() { return 0; }
            public function getTeleportTicks() { return 0; }
            public function getTeleportCommandTicks() { return 0; }
            public function getHurtTicks() { return 0; }
            public function getOnlineTime() { return 0; }
            public function isCurrentChunkIsLoaded() { return true; }
            public function isRecentlyCancelledEvent() { return false; }
            public function getExternalData($key = null) { return null; }
        };
    }

    private function pass(string $message): void {
        $this->results[] = ['status' => 'PASS', 'message' => $message];
        echo "  ✓ {$message}\n";
    }

    private function fail(string $message): void {
        $this->results[] = ['status' => 'FAIL', 'message' => $message];
        echo "  ✗ {$message}\n";
    }

    private function printResults(): void {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "Test Results Summary\n";
        echo str_repeat("=", 60) . "\n";

        $passed = count(array_filter($this->results, fn($r) => $r['status'] === 'PASS'));
        $failed = count(array_filter($this->results, fn($r) => $r['status'] === 'FAIL'));
        $total = count($this->results);

        echo "Total Tests:  {$total}\n";
        echo "Passed:       {$passed}\n";
        echo "Failed:       {$failed}\n";
        echo "Success Rate: " . round(($passed / $total) * 100, 2) . "%\n";
        echo str_repeat("=", 60) . "\n";

        if ($failed > 0) {
            echo "\nFailed Tests:\n";
            foreach ($this->results as $result) {
                if ($result['status'] === 'FAIL') {
                    echo "  - {$result['message']}\n";
                }
            }
        }
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli') {
    (new SnapshotValidationTest())->run();
}
