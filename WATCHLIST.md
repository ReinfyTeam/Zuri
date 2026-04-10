# Watchlist / Development Phase

- [x] Create snapshot/builder infrastructure for asynchronous checking.
- [x] Migrate all modules to asynchronous checking.
- [x] Implement batch queueing system intended for asynchronous threads.
- [x] Test and validate all asynchronous checks and ensure all snapshots are working and returning to the main thread.

## Detection Quality

- [x] Add confidence scoring to every violation result to reduce hard binary flagging.
- [x] Introduce per-check dynamic thresholds based on ping, TPS, and server load.
- [x] Add automatic false-positive cooldown windows after lag spikes and world transfers.
- [x] Build cross-check correlation (movement + combat + packet timing) before escalating punishments.

## Async and Data Pipeline

- [x] Add strict snapshot schema versioning to prevent thread desync when fields change.
- [x] Implement snapshot integrity guards (required fields, value bounds, sanity assertions).
- [x] Add bounded queue backpressure handling with dropped-task telemetry.
- [x] Implement worker health checks and auto-restart for stuck async workers.
- [x] Add latency budget metrics for snapshot build, queue wait, worker processing, and main-thread merge.
- [x] Add graceful fallback path to sync-safe mode if async pipeline degrades.

## Performance and Stability

- [x] Add continuous hot-path profiling for checks, packet handlers, and violation processing.
- [x] Add memory pressure watchdog and rolling cache eviction strategy.
- [x] Add startup self-diagnostics for missing config keys, invalid values, and stale cache data.
- [x] Add panic-safe exception boundaries around all scheduled tasks and event listeners.

## Punishment and Action System

- [ ] Add policy engine for progressive punishments (notify, flag, setback, kick, ban).
- [ ] Add staff acknowledgment flow before high-impact automatic punishments.
- [ ] Add temporary quarantine mode that limits suspicious players without immediate bans.

## Security and Abuse Resistance

- [x] Add command permission audit for every admin and debug command.
- [x] Add anti-spam protections for alert dispatch and webhook posting.
- [x] Add tamper-evident audit logs for punishments, config edits, and override actions.
- [x] Add protections against crafted packet floods targeting check bottlenecks.

## Configuration and UX

- [ ] Add config migration engine for automatic upgrades between plugin versions.
- [x] Add config schema validation with clear startup error reporting.
- [x] Add in-game diagnostic command set (pipeline status, queue depth, worker health).
- [x] Add per-world and per-gamemode check toggles.
- [ ] Add localization support for staff-facing messages and alerts.

## Observability and Tooling

- [ ] Add structured logging format with correlation IDs per player/session.
- [ ] Add metrics export endpoint (check runtime, queue depth, violations per check, false-positive ratio).

## Testing and QA

- [ ] Add unit tests for all check math, threshold logic, and decay behavior.
- [ ] Add integration tests for packet-to-violation flow across representative scenarios.
- [ ] Add async race-condition tests for snapshot/queue/merge lifecycle.
- [ ] Add regression suite from archived false-positive and bypass reports.
- [ ] Add load and soak tests with synthetic player traffic.
- [ ] Add CI quality gates for tests, static analysis, style checks, and packaging.

## Documentation and Release Operations

- [ ] Add architecture docs for thread model, data flow, and module boundaries.
- [ ] Add incident playbook for high false-positive events and emergency rollbacks.
- [ ] Add changelog discipline with migration notes and breaking-change callouts.
- [ ] Add contribution guidelines (CONTRIBUTING.md) for check design, naming conventions, and testing expectations.

## Low-End Server Optimization

- [ ] Add adaptive check scheduler that reduces heavy module frequency when TPS drops below configurable thresholds.
- [ ] Add per-check CPU budget caps with automatic cooldown/defer when a check exceeds runtime limits.
- [ ] Add dynamic packet sampling for non-critical checks during high player count or high packet pressure.
- [ ] Add max checks-per-tick cap with fair round-robin distribution across online players.
- [ ] Add incremental cache warm-up on startup to avoid lag spikes on first player joins.
- [ ] Add async worker auto-downscale during low activity and auto-upscale under controlled load.
- [ ] Add low-memory mode to disable non-essential profiling/history buffers automatically.
- [ ] Add configurable alert verbosity levels to reduce log and chat spam overhead.
- [ ] Add compact in-memory snapshot representation to reduce allocation and GC pressure.
- [ ] Add check batching windows for movement/combat packets to minimize redundant calculations.
