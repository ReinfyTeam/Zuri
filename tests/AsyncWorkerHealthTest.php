<?php

declare(strict_types=1);

namespace ReinfyTeam\Zuri\Tests;

require_once __DIR__ . '/../vendor/autoload.php';

use ReinfyTeam\Zuri\task\CheckAsyncTask;

use function count;
use function microtime;
use function php_sapi_name;

final class AsyncWorkerHealthTest {
	/** @var list<string> */
	private array $failures = [];

	public function getFailureCount() : int {
		return count($this->failures);
	}

	public function run() : void {
		$this->testStuckWorkerRecoveryAndRestart();
		$this->testFallbackActivationAfterRecovery();
		$this->testLatencyBudgetMetricsExposed();

		if ($this->failures === []) {
			echo "AsyncWorkerHealthTest: PASS\n";
			return;
		}

		echo "AsyncWorkerHealthTest: FAIL\n";
		foreach ($this->failures as $failure) {
			echo " - {$failure}\n";
		}
	}

	private function testStuckWorkerRecoveryAndRestart() : void {
		CheckAsyncTask::resetForTesting();
		CheckAsyncTask::configure(2, 10, 0.25, 2.0);

		$now = microtime(true);
		CheckAsyncTask::injectActiveTaskForTesting(
			'Test\\Check',
			'Notch',
			1,
			$now - 1.0,
			$now - 1.1,
			$now - 1.2,
			0
		);

		$result = CheckAsyncTask::runHealthCheck($now);
		$metrics = CheckAsyncTask::getMetrics();

		if (($result['reclaimed'] ?? 0) < 1) {
			$this->fail('Health check did not reclaim stuck worker');
		}

		if (($result['restarted'] ?? 0) < 1) {
			$this->fail('Health check did not enqueue retry for stuck worker');
		}

		if (($metrics['totalRecoveredStuck'] ?? 0) < 1) {
			$this->fail('Recovered stuck metric did not increment');
		}

		if (($metrics['totalAutoRestarts'] ?? 0) < 1) {
			$this->fail('Auto restart metric did not increment');
		}
	}

	private function testFallbackActivationAfterRecovery() : void {
		CheckAsyncTask::resetForTesting();
		CheckAsyncTask::configure(2, 10, 0.1, 5.0);

		$now = microtime(true);
		CheckAsyncTask::injectActiveTaskForTesting(
			'Test\\Check',
			'Steve',
			2,
			$now - 1.0,
			$now - 1.0,
			$now - 1.0,
			0
		);

		CheckAsyncTask::runHealthCheck($now);
		$metrics = CheckAsyncTask::getMetrics();

		if (($metrics['syncFallbackActive'] ?? false) !== true) {
			$this->fail('Sync fallback mode was not activated after degraded health event');
		}
	}

	private function testLatencyBudgetMetricsExposed() : void {
		CheckAsyncTask::resetForTesting();
		$metrics = CheckAsyncTask::getMetrics();

		$required = [
			'avgBuildDelay',
			'avgQueueWait',
			'avgWorkerTime',
			'avgMergeTime',
			'workerTimeoutSeconds',
			'syncFallbackActive',
		];

		foreach ($required as $key) {
			if (!array_key_exists($key, $metrics)) {
				$this->fail("Missing latency/health metric key: {$key}");
			}
		}
	}

	private function fail(string $message) : void {
		$this->failures[] = $message;
	}
}

if (php_sapi_name() === 'cli') {
	$test = new AsyncWorkerHealthTest();
	$test->run();

	if ($test->getFailureCount() > 0) {
		exit(1);
	}
}
