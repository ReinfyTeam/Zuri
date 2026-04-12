<?php

declare(strict_types=1);

use vennv\vapm\system\event\EventLoop;
use vennv\vapm\io\Io;
use vennv\vapm\system\System;

require_once __DIR__ . '\..\..\vendor\autoload.php';

$total = max(1, (int) ($argv[1] ?? 5000));
$jobs = [];

for ($i = 1; $i <= $total; $i++) {
	$jobs[] = Io::async(static fn() : int => $i);
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
	"type" => "stress-queue",
	"jobs" => $total,
	"sum" => $sum,
	"metrics" => $metrics,
], JSON_PRETTY_PRINT) . PHP_EOL;
