<?php

declare(strict_types=1);

namespace ReinfyTeam\Zuri\Tests;

require_once __DIR__ . '/../vendor/autoload.php';

use ReinfyTeam\Zuri\checks\CrossCheckCorrelation;

use function count;
use function microtime;
use function php_sapi_name;

final class CrossCheckCorrelationTest {
	/** @var list<string> */
	private array $failures = [];

	public function getFailureCount() : int {
		return count($this->failures);
	}

	public function run() : void {
		CrossCheckCorrelation::setGroupCacheForTesting([
			'speed' => CrossCheckCorrelation::GROUP_MOVEMENT,
			'killaura' => CrossCheckCorrelation::GROUP_COMBAT,
			'timer' => CrossCheckCorrelation::GROUP_PACKET_TIMING,
		]);

		$this->testCheckClassification();
		$this->testRecordAndCountAcrossDomains();
		$this->testRecordAndCountExpiresOldHits();
		$this->testNormalizeRequiredGroupsBounds();

		if ($this->failures === []) {
			echo "CrossCheckCorrelationTest: PASS\n";
			return;
		}

		echo "CrossCheckCorrelationTest: FAIL\n";
		foreach ($this->failures as $failure) {
			echo " - {$failure}\n";
		}
	}

	private function testCheckClassification() : void {
		if (CrossCheckCorrelation::classifyGroup('Speed') !== CrossCheckCorrelation::GROUP_MOVEMENT) {
			$this->fail('Speed should classify as movement');
		}
		if (CrossCheckCorrelation::classifyGroup('KillAura') !== CrossCheckCorrelation::GROUP_COMBAT) {
			$this->fail('KillAura should classify as combat');
		}
		if (CrossCheckCorrelation::classifyGroup('Timer') !== CrossCheckCorrelation::GROUP_PACKET_TIMING) {
			$this->fail('Timer should classify as packet_timing');
		}
		if (CrossCheckCorrelation::classifyGroup('Scaffold') !== null) {
			$this->fail('Scaffold should not classify into correlation groups');
		}
	}

	private function testRecordAndCountAcrossDomains() : void {
		$now = microtime(true);

		[$groups, $hits] = CrossCheckCorrelation::recordAndCount([], 'Speed', $now, 10.0);
		if ($groups !== 1) {
			$this->fail('Expected 1 correlated group after Speed hit');
		}

		[$groups, $hits] = CrossCheckCorrelation::recordAndCount($hits, 'KillAura', $now + 1.0, 10.0);
		if ($groups !== 2) {
			$this->fail('Expected 2 correlated groups after KillAura hit');
		}

		[$groups, $hits] = CrossCheckCorrelation::recordAndCount($hits, 'Timer', $now + 2.0, 10.0);
		if ($groups !== 3) {
			$this->fail('Expected 3 correlated groups after Timer hit');
		}
	}

	private function testRecordAndCountExpiresOldHits() : void {
		$now = microtime(true);
		$initialHits = [
			CrossCheckCorrelation::GROUP_MOVEMENT => $now - 30.0,
			CrossCheckCorrelation::GROUP_COMBAT => $now - 2.0,
		];

		[$groups, $hits] = CrossCheckCorrelation::recordAndCount($initialHits, 'Timer', $now, 10.0);
		if ($groups !== 2) {
			$this->fail('Expected 2 groups after pruning expired movement hit and adding timer hit');
		}

		if (!isset($hits[CrossCheckCorrelation::GROUP_COMBAT]) || !isset($hits[CrossCheckCorrelation::GROUP_PACKET_TIMING])) {
			$this->fail('Expected combat and packet_timing hits to remain after prune/record');
		}
	}

	private function testNormalizeRequiredGroupsBounds() : void {
		if (CrossCheckCorrelation::normalizeRequiredGroups(0) !== 1) {
			$this->fail('normalizeRequiredGroups(0) should clamp to 1');
		}
		if (CrossCheckCorrelation::normalizeRequiredGroups(2) !== 2) {
			$this->fail('normalizeRequiredGroups(2) should stay 2');
		}
		if (CrossCheckCorrelation::normalizeRequiredGroups(99) !== 3) {
			$this->fail('normalizeRequiredGroups(99) should clamp to 3');
		}
	}

	private function fail(string $message) : void {
		$this->failures[] = $message;
	}
}

if (php_sapi_name() === 'cli') {
	$test = new CrossCheckCorrelationTest();
	$test->run();

	if ($test->getFailureCount() > 0) {
		exit(1);
	}
}
