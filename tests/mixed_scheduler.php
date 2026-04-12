<?php

declare(strict_types=1);

use Generator;
use vennv\vapm\coroutine\CoroutineGen;
use vennv\vapm\system\event\EventLoop;
use vennv\vapm\io\Io;
use vennv\vapm\system\System;

require_once __DIR__ . '\..\..\vendor\autoload.php';

$promiseCount = max(1, (int) ($argv[1] ?? 2000));
$coroutineCount = max(1, (int) ($argv[2] ?? 500));
$timerCount = max(1, (int) ($argv[3] ?? 500));

$jobs = [];
for ($i = 0; $i < $promiseCount; $i++) {
	$jobs[] = Io::async(static fn() : int => $i * 2);
}

$timerHits = 0;
for ($i = 0; $i < $timerCount; $i++) {
	System::setTimeout(static function () use (&$timerHits) : void {
		$timerHits++;
	}, 0);
}

$coroutineHits = 0;
for ($i = 0; $i < $coroutineCount; $i++) {
	CoroutineGen::runNonBlocking(static function () use (&$coroutineHits) : Generator {
		yield from CoroutineGen::delay(1);
		$coroutineHits++;
		return $coroutineHits;
	});
}

System::runSingleEventLoop();

$sum = 0;
foreach ($jobs as $job) {
	$promise = EventLoop::getReturn($job->getId());
	if ($promise !== null) {
		$sum += (int) $promise->getResult();
	}
}

$metrics = EventLoop::getMetricsSnapshot();
echo json_encode([
	"type" => "mixed-scheduler",
	"promises" => $promiseCount,
	"coroutines" => $coroutineCount,
	"timers" => $timerCount,
	"promiseSum" => $sum,
	"timerHits" => $timerHits,
	"coroutineHits" => $coroutineHits,
	"metrics" => $metrics,
], JSON_PRETTY_PRINT) . PHP_EOL;
