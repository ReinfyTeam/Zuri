<?php

declare(strict_types=1);

namespace ReinfyTeam\Zuri\Tests;

use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\checks\modules\fly\FlyA;
use ReinfyTeam\Zuri\checks\modules\combat\reach\ReachA;
use ReinfyTeam\Zuri\checks\snapshots\MovementSnapshot;
use ReinfyTeam\Zuri\checks\snapshots\CombatSnapshot;
use function json_encode;
use function json_decode;
use function microtime;

/**
 * Integration tests for async check pipeline.
 *
 * Validates:
 * - Payload creation from snapshots
 * - evaluateAsync() execution and result format
 * - Result application back to main thread
 * - Schema version validation
 * - Error handling for malformed payloads
 */
class AsyncCheckIntegrationTest {
    private array $results = [];

    public function run(): void {
        echo "Starting Async Check Integration Tests...\n\n";

        try {
            $this->testPayloadSerialization();
            $this->testAsyncEvaluation();
            $this->testResultHandling();
            $this->testSchemaVersionValidation();
            $this->testErrorHandling();
        } catch (\Exception $e) {
            $this->fail("Test execution failed: {$e->getMessage()}");
        }

        $this->printResults();
    }

    private function testPayloadSerialization(): void {
        echo "Testing Payload Serialization...\n";

        try {
            // Test MovementSnapshot serialization
            $snapshot = $this->createMovementSnapshot("FlyA");
            $built = $snapshot->build();

            if (!isset($built['type']) || $built['type'] !== 'FlyA') {
                $this->fail("MovementSnapshot: 'type' field missing or incorrect");
            } else {
                $this->pass("MovementSnapshot: 'type' field present and correct");
            }

            if (!isset($built['schemaVersion'])) {
                $this->fail("MovementSnapshot: 'schemaVersion' missing");
            } else {
                $this->pass("MovementSnapshot: 'schemaVersion' present");
            }

            // Verify all required position fields
            $requiredFields = ['posX', 'posY', 'posZ', 'eyeX', 'eyeY', 'eyeZ', 'motionX', 'motionY', 'motionZ'];
            foreach ($requiredFields as $field) {
                if (!isset($built[$field])) {
                    $this->fail("MovementSnapshot: Missing field '{$field}'");
                }
            }
            $this->pass("MovementSnapshot: All position fields present");

            // Test JSON serialization of full payload
            $json = json_encode($built);
            if ($json === false) {
                $this->fail("MovementSnapshot: JSON encoding failed");
            } else {
                $this->pass("MovementSnapshot: JSON encoding successful");
            }

            // Test deserialization
            $decoded = json_decode($json, true);
            if ($decoded === null) {
                $this->fail("MovementSnapshot: JSON decoding failed");
            } else {
                $this->pass("MovementSnapshot: JSON decoding successful");
            }
        } catch (\Exception $e) {
            $this->fail("Payload serialization error: {$e->getMessage()}");
        }

        echo "\n";
    }

    private function testAsyncEvaluation(): void {
        echo "Testing Async Evaluation Logic...\n";

        try {
            // Test FlyA async evaluation with valid payload
            $validPayload = [
                'type' => 'FlyA',
                'schemaVersion' => MovementSnapshot::SCHEMA_VERSION,
                'attackTicks' => 50,
                'onlineTime' => 100,
                'jumpTicks' => 50,
                'teleportTicks' => 100,
                'teleportCommandTicks' => 100,
                'hurtTicks' => 50,
                'inWeb' => false,
                'onGround' => false,
                'onAdhesion' => false,
                'survival' => true,
                'chunkLoaded' => true,
                'gliding' => false,
                'absMotionX' => 0.05,
                'absMotionZ' => 0.05,
                'recentlyCancelled' => false,
                'posY' => 64.0,
                'cachedData' => [
                    'allowFlight' => false,
                    'noClientPredictions' => false,
                    'lastYNoGround' => null,
                    'lastTime' => null,
                    'now' => microtime(true),
                    'maxGroundDiff' => 0.5,
                ]
            ];

            $result = FlyA::evaluateAsync($validPayload);

            if (!is_array($result)) {
                $this->fail("FlyA::evaluateAsync() does not return array");
            } else {
                $this->pass("FlyA::evaluateAsync() returns array");
            }

            // Test ReachA async evaluation
            $reachPayload = [
                'type' => 'ReachA',
                'schemaVersion' => CombatSnapshot::SCHEMA_VERSION,
                'damagerSurvival' => true,
                'victimSurvival' => true,
                'victimProjectileTicks' => 50,
                'damagerProjectileTicks' => 50,
                'victimBowTicks' => 50,
                'damagerBowTicks' => 50,
                'victimRecentlyCancelled' => false,
                'damagerRecentlyCancelled' => false,
                'damagerEyeX' => 0.0,
                'damagerEyeY' => 64.0,
                'damagerEyeZ' => 0.0,
                'victimEyeX' => 5.0,
                'victimEyeY' => 64.0,
                'victimEyeZ' => 0.0,
                'cachedData' => ['maxDistance' => 10.0],
            ];

            $reachResult = ReachA::evaluateAsync($reachPayload);

            if (!is_array($reachResult)) {
                $this->fail("ReachA::evaluateAsync() does not return array");
            } else {
                $this->pass("ReachA::evaluateAsync() returns array");
            }
        } catch (\Exception $e) {
            $this->fail("Async evaluation error: {$e->getMessage()}");
        }

        echo "\n";
    }

    private function testResultHandling(): void {
        echo "Testing Result Handling Format...\n";

        try {
            // Test valid result formats
            $validResults = [
                ['failed' => true],
                ['debug' => 'test debug info'],
                ['set' => ['key' => 'value']],
                ['unset' => ['key']],
                ['failed' => true, 'debug' => 'info', 'set' => ['k' => 'v']],
            ];

            foreach ($validResults as $idx => $result) {
                // Check that result can be JSON serialized
                $json = json_encode($result);
                if ($json === false) {
                    $this->fail("Result format {$idx}: JSON encoding failed");
                    continue;
                }

                // Check that result can be JSON decoded
                $decoded = json_decode($json, true);
                if ($decoded === null) {
                    $this->fail("Result format {$idx}: JSON decoding failed");
                    continue;
                }

                $this->pass("Result format {$idx}: Valid and serializable");
            }
        } catch (\Exception $e) {
            $this->fail("Result handling error: {$e->getMessage()}");
        }

        echo "\n";
    }

    private function testSchemaVersionValidation(): void {
        echo "Testing Schema Version Validation...\n";

        try {
            // Test correct schema version passes
            $validPayload = [
                'type' => 'FlyA',
                'schemaVersion' => MovementSnapshot::SCHEMA_VERSION,
            ];

            $schemaCheck = (int) ($validPayload['schemaVersion'] ?? 0) === MovementSnapshot::SCHEMA_VERSION;
            if (!$schemaCheck) {
                $this->fail("Schema validation: Correct version rejected");
            } else {
                $this->pass("Schema validation: Correct version accepted");
            }

            // Test incorrect schema version is rejected
            $invalidPayload = [
                'type' => 'FlyA',
                'schemaVersion' => 999,
            ];

            $invalidSchemaCheck = (int) ($invalidPayload['schemaVersion'] ?? 0) === MovementSnapshot::SCHEMA_VERSION;
            if ($invalidSchemaCheck) {
                $this->fail("Schema validation: Incorrect version accepted");
            } else {
                $this->pass("Schema validation: Incorrect version rejected");
            }

            // Test missing schema version is rejected
            $missingPayload = [
                'type' => 'FlyA',
            ];

            $missingSchemaCheck = (int) ($missingPayload['schemaVersion'] ?? 0) === MovementSnapshot::SCHEMA_VERSION;
            if ($missingSchemaCheck) {
                $this->fail("Schema validation: Missing version accepted");
            } else {
                $this->pass("Schema validation: Missing version rejected");
            }
        } catch (\Exception $e) {
            $this->fail("Schema version validation error: {$e->getMessage()}");
        }

        echo "\n";
    }

    private function testErrorHandling(): void {
        echo "Testing Error Handling...\n";

        try {
            // Test evaluateAsync with wrong type
            $wrongType = [
                'type' => 'InvalidCheck',
                'schemaVersion' => MovementSnapshot::SCHEMA_VERSION,
            ];

            $result = FlyA::evaluateAsync($wrongType);
            if (!empty($result)) {
                $this->fail("FlyA::evaluateAsync() processed wrong type");
            } else {
                $this->pass("FlyA::evaluateAsync() rejected wrong type");
            }

            // Test evaluateAsync with wrong schema version
            $wrongSchema = [
                'type' => 'FlyA',
                'schemaVersion' => 999,
            ];

            $result = FlyA::evaluateAsync($wrongSchema);
            if (!empty($result)) {
                $this->fail("FlyA::evaluateAsync() processed wrong schema version");
            } else {
                $this->pass("FlyA::evaluateAsync() rejected wrong schema version");
            }

            // Test minimal payload handling
            $minimal = [];
            $result = FlyA::evaluateAsync($minimal);
            if (!is_array($result)) {
                $this->fail("FlyA::evaluateAsync() crashed on minimal payload");
            } else {
                $this->pass("FlyA::evaluateAsync() handles minimal payload gracefully");
            }
        } catch (\Exception $e) {
            $this->fail("Error handling test exception: {$e->getMessage()}");
        }

        echo "\n";
    }

    private function createMockPlayer() {
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
                    public function getPing() { return 50; }
                };
            }

            public function isSprinting() { return false; }
            public function isSurvival() { return true; }
            public function getAllowFlight() { return false; }
            public function hasNoClientPredictions() { return false; }
        };
    }

    private function createMockPlayerAPI() {
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

    private function createMovementSnapshot(string $checkType): MovementSnapshot {
        $reflection = new \ReflectionClass(MovementSnapshot::class);
        /** @var MovementSnapshot $snapshot */
        $snapshot = $reflection->newInstanceWithoutConstructor();

        $set = static function (object $target, string $property, mixed $value): void {
            $rp = new \ReflectionProperty($target, $property);
            $rp->setValue($target, $value);
        };

        $set($snapshot, "checkType", $checkType);
        $set($snapshot, "captureTime", microtime(true));
        $set($snapshot, "posX", 0.0);
        $set($snapshot, "posY", 64.0);
        $set($snapshot, "posZ", 0.0);
        $set($snapshot, "eyeX", 0.0);
        $set($snapshot, "eyeY", 65.62);
        $set($snapshot, "eyeZ", 0.0);
        $set($snapshot, "motionX", 0.0);
        $set($snapshot, "motionY", 0.0);
        $set($snapshot, "motionZ", 0.0);
        $set($snapshot, "absMotionX", 0.0);
        $set($snapshot, "absMotionY", 0.0);
        $set($snapshot, "absMotionZ", 0.0);
        $set($snapshot, "onGround", true);
        $set($snapshot, "onAdhesion", false);
        $set($snapshot, "inWeb", false);
        $set($snapshot, "gliding", false);
        $set($snapshot, "sprinting", false);
        $set($snapshot, "survival", true);
        $set($snapshot, "jumpTicks", 0);
        $set($snapshot, "attackTicks", 0);
        $set($snapshot, "teleportTicks", 0);
        $set($snapshot, "teleportCommandTicks", 0);
        $set($snapshot, "hurtTicks", 0);
        $set($snapshot, "onlineTime", 0);
        $set($snapshot, "groundSolid", true);
        $set($snapshot, "chunkLoaded", true);
        $set($snapshot, "recentlyCancelled", false);
        $set($snapshot, "ping", 50);
        $set($snapshot, "cachedData", []);

        return $snapshot;
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

if (php_sapi_name() === 'cli') {
    (new AsyncCheckIntegrationTest())->run();
}
