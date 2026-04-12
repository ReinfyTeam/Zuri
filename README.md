# LibVapmPMMP

Async/Promise/Coroutine/Thread utilities for PocketMine-MP (virion-ready).

## Requirements
- PMMP API: `5.0.0`
- PHP: `8.1 - 8.4`

## Installation

### Composer
```bash
composer require reinfyteam/libvapm-pmmp
```

### Virion
- Download from Poggit: https://poggit.pmmp.io/ci/ReinfyTeam/LibVapmPMMP
- Place the virion in your project virions folder.

## Quick setup

Initialize once in your plugin:

```php
use pocketmine\plugin\PluginBase;
use vennv\vapm\VapmPMMP;

final class Main extends PluginBase{
    protected function onEnable() : void{
        VapmPMMP::init($this);
    }
}
```

`VapmPMMP::init()` registers the repeating event-loop tick task.
Calling it more than once (or with a different plugin instance) is guarded and will emit a warning.

## Recommended imports

```php
use vennv\vapm\VapmPMMP;
use vennv\vapm\io\Io;
use vennv\vapm\ct\Ct;
use vennv\vapm\Promise;
use vennv\vapm\System;
```

## Usage

### 1) Async + await (Io facade)

```php
$job = Io::async(function () : string {
    return "done";
});

$result = Io::await($job); // "done"
```

### 2) Delay / timers

```php
Io::setTimeout(function () : void {
    // run once after delay
}, 50);

Io::setInterval(function () : void {
    // run repeatedly
}, 20);

Io::delay(100)->then(function () : void {
    // delayed promise flow
});
```

### 3) Coroutines (Ct facade)

```php
use Generator;

Ct::c(function () : Generator {
    yield from Ct::cDelay(10);
    // coroutine work
});

Ct::cBlock(function () : Generator {
    yield from Ct::cDelay(5);
    // blocking coroutine run
});
```

### 4) Promise composition

```php
$all = Promise::all([
    Io::async(fn() => 1),
    Io::async(fn() => 2),
]);

$values = Io::await($all); // [1, 2]
```

Also available: `Promise::allSettled()`, `Promise::any()`, `Promise::race()`.

### 5) HTTP/file helpers (System)

```php
System::fetch("https://example.com")->then(function ($response) : void {
    // InternetRequestResult
});

System::read("/path/to/file.txt")->then(function (string $content) : void {
    // file contents
});
```

<<<<<<< HEAD
## Quick-start recipes

### Timeout + fallback
```php
Io::delay(100)->then(function () : void {
    // do primary operation
})->finally(function () : void {
    // cleanup
});
```

### Repeating interval with stop
```php
$task = System::setInterval(function () : void {
    // heartbeat
}, 20);

System::setTimeout(function () use ($task) : void {
    System::clearInterval($task);
}, 200);
```

### Parallel awaits
```php
$result = Io::await(Promise::all([
    Io::async(fn() => "A"),
    Io::async(fn() => "B"),
]));
```

### Fetch with explicit timeout
```php
System::fetch("https://example.com", [
    "method" => "GET",
    "timeout" => 5,
])->then(function ($response) : void {
    // InternetRequestResult
});
```

## High-volume queue and backpressure notes

1. Promise queue de-duplicates enqueues by Promise ID.
2. Scheduler fairness is backlog-aware across Promise, coroutine, microtask, and macrotask queues.
3. For large producers, tune chunking with `Settings::setWorkDrainChunkSize()` and `Settings::setWorkerProducerChunkSize()`.
4. For low-end hosts, cap per-tick work with `Settings::setEventLoopLimits()`, `Settings::setMacroTaskLimits()`, and `Settings::setCoroutineLimits()`.

## Scheduler metrics and diagnostics

```php
use vennv\vapm\EventLoop;
use vennv\vapm\Settings;

Settings::setSchedulerDebug(true);
Settings::setDebugLogIntervalTicks(20);
Settings::setHealthWarnBacklogThreshold(10000);
Settings::setHealthWarnDropThreshold(100);

$snapshot = EventLoop::getMetricsSnapshot();
// queueDepth, coroutineBacklog, microTaskBacklog, macroTaskBacklog, drops, processed counters...
```

Static analysis gates:

```bash
composer analyse-src
composer analyse-scheduler
```

## PocketMine-MP API 5 compatibility notes

1. `VapmPMMP::init()` is designed for API 5 tick scheduling (`scheduleRepeatingTask(..., 1)`).
2. In PMMP-managed mode, system-level tick/shutdown hooks are not auto-registered; PMMP scheduler drives the loop.
3. In non-PMMP contexts, runtime hooks are attempted; if unavailable, LibVapmPMMP emits warnings and expects manual loop runs.

Compatibility check script:

```bash
composer compat-api5
```

## Stress scripts and benchmark comparison notes

Run deterministic stress workloads:

```bash
composer benchmark-queue
composer benchmark-mixed
```

For before/after comparisons:

```bash
# before
git checkout <baseline-tag-or-commit>
composer benchmark-compare

# after
git checkout <your-branch>
composer benchmark-compare
```

Capture both JSON outputs and compare:
- `metrics.totalBacklog`, `metrics.queueDepth`, `metrics.processed*`
- `metrics.droppedReturns`, `metrics.droppedDuplicateQueue`

For optimization roadmap and upcoming improvements, see [`WATCHLIST.md`](WATCHLIST.md).
=======
## Notes for high-throughput workloads
- Keep long operations async/coroutine-based.
- Prefer batching and composition (`Promise::all`, channels, await groups) over deep nested callbacks.
- For optimization roadmap and upcoming improvements, see [`WATCHLIST.md`](WATCHLIST.md).
>>>>>>> a01e0333b855038f1e6535c0724a400d2f919db9
