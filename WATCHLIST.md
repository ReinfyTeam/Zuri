# Watchlist / Development Phase

- [x] Create snapshot/builder infrastructure for asynchronous checking.
- [x] Migrate all modules to asynchronous checking.
- [x] Implement batch queueing system intended for asynchronous threads.
- [x] Test and validate all asynchronous checks and ensure all snapshots are working and returning to the main thread.

## Detection Quality

- [x] Add confidence scoring to every violation result to reduce hard binary flagging.
- [x] Introduce per-check dynamic thresholds based on ping, TPS, and server load.
- [x] Add automatic false-positive cooldown windows after lag spikes and world transfers.
- [ ] Build cross-check correlation (movement + combat + packet timing) before escalating punishments.

## Async and Data Pipeline

- [x] Add strict snapshot schema versioning to prevent thread desync when fields change.
- [x] Implement snapshot integrity guards (required fields, value bounds, sanity assertions).
- [x] Add bounded queue backpressure handling with dropped-task telemetry.
- [x] Implement worker health checks and auto-restart for stuck async workers.
- [x] Add latency budget metrics for snapshot build, queue wait, worker processing, and main-thread merge.
- [x] Add graceful fallback path to sync-safe mode if async pipeline degrades.

## Performance and Stability

- [ ] Add continuous hot-path profiling for checks, packet handlers, and violation processing.
- [ ] Introduce object pooling for frequently allocated packet analysis structures.
- [ ] Add memory pressure watchdog and rolling cache eviction strategy.
- [ ] Add startup self-diagnostics for missing config keys, invalid values, and stale cache data.
- [ ] Add panic-safe exception boundaries around all scheduled tasks and event listeners.

## Punishment and Action System

- [ ] Add policy engine for progressive punishments (notify, flag, setback, kick, ban).
- [ ] Add staff acknowledgment flow before high-impact automatic punishments.
- [ ] Add temporary quarantine mode that limits suspicious players without immediate bans.

## Security and Abuse Resistance

- [ ] Harden webhook pipeline with signed payload validation and replay protection.
- [ ] Add command permission audit for every admin and debug command.
- [ ] Add anti-spam protections for alert dispatch and webhook posting.
- [ ] Add tamper-evident audit logs for punishments, config edits, and override actions.
- [ ] Add protections against crafted packet floods targeting check bottlenecks.

## Configuration and UX

- [ ] Add config migration engine for automatic upgrades between plugin versions.
- [ ] Add config schema validation with clear startup error reporting.
- [ ] Add in-game diagnostic command set (pipeline status, queue depth, worker health).
- [ ] Add per-world and per-gamemode check toggles.
- [ ] Add temporary player exemptions with auto-expiry support.
- [ ] Add localization support for staff-facing messages and alerts.

## Observability and Tooling

- [ ] Add structured logging format with correlation IDs per player/session.
- [ ] Add metrics export endpoint (check runtime, queue depth, violations per check, false-positive ratio).
- [ ] Add dashboard-ready timeseries aggregation for operational visibility.
- [ ] Add deterministic debug replay mode from packet/session traces.
- [ ] Add safe redaction rules for sensitive player and server metadata.

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
- [ ] Add release channel strategy (nightly, beta, stable) with staged rollout guidance.
- [ ] Add contribution guidelines for check design, naming conventions, and testing expectations.