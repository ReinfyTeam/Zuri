# Zuri PocketMine-MP Anticheat 🛡️

[![](https://poggit.pmmp.io/shield.state/Zuri)](https://poggit.pmmp.io/p/Zuri) [![](https://poggit.pmmp.io/shield.api/Zuri)](https://poggit.pmmp.io/p/Zuri) [![](https://poggit.pmmp.io/shield.dl.total/Zuri)](https://poggit.pmmp.io/p/Zuri)

Zuri is a movement anticheat for PocketMine-MP. The current `main` branch is the **1.4.0-BETA rewrite** and registers two checks: **SpeedA** and **SpeedB**. This document describes that branch; older releases have a different feature set.

The rewrite is intended for a **pre-release**. Test it on a staging server with your protection plugins and normal player movement before enabling kick or ban punishments.

## Current checks and behavior

| Check | Input | Behavior |
| --- | --- | --- |
| SpeedA | `PlayerAuthInputPacket` | Compares horizontal movement with the expected movement allowance. |
| SpeedB | `PlayerMoveEvent` | Tracks horizontal and vertical movement with accumulating detection buffers and movement-state allowances. |

Player movement and terrain state are captured on the main thread. Serialized snapshots are processed by check workers within one background coordinator thread, and results return to the main thread for violation handling. `zuri.threads.max_worker` controls the number of check workers within that coordinator.

Checks skip movement in terrain that has not loaded and resume after it loads. Cancelled movement and denied block interactions receive a short movement grace period. The legacy Phase trapdoor correction from [issue #76](https://github.com/ReinfyTeam/Zuri/issues/76) is absent from this rewrite; Zuri does not teleport players onto the highest block in a column.

Server metrics are initialized when the plugin enables and refreshed periodically. TPS, ping and player load feed the punishment threshold calculation. The metrics delay is measured in seconds, with a minimum of one second.

**Legacy features:** combat, fly, reach, scaffold, inventory, chat, proxy/VPN, IP limits, cross-check correlation, tuning presets, and the `/zuri` commands/UI are not implemented in the current rewrite. The old list of 40+ checks and wiki examples do not describe its current capabilities. Configuration entries that mention another module do not register that module.

## Requirements and installation

- Official PocketMine-MP 5 and its compatible PHP runtime; PHP 8.2 or newer is required. Development dependencies target PocketMine-MP 5.42 or newer.
- A compiled `Zuri.phar`. Virion dependencies are bundled by the build; Composer is required to build from source.
- Forks of PocketMine-MP are unsupported.

1. Back up the existing Zuri PHAR and its data folder, especially when upgrading from 1.3.x.
2. Stop the server, place `Zuri.phar` in `plugins/`, and restart.
3. Review the generated files in `plugin_data/Zuri/`, then restart after making configuration changes.
4. Test regular movement, chunk loading, teleports and interactions with protection plugins on a staging server.

Configuration files include `config.yml`, `constants.yml` and language files. An older configuration version can be copied to a `-old.yml` backup and replaced with the bundled defaults. Review and reapply relevant custom settings after upgrading; the 1.3.x configuration and plugin API are not a drop-in match for this rewrite.

`config.yml` controls check worker capacity, metrics collection and punishment thresholds. Per-check options are read under `zuri.checks.speed`, including `pre-vl.a`, `pre-vl.b`, `maxvl`, `a.punishment` and `b.punishment`. Punishments default to an internal flag; `kick` and `ban` use the configured native or command action. Adjust movement constants in `constants.yml` only after reproducing a detection issue.

## Build and validation

Use the PHP runtime supplied for PocketMine-MP, including its required extensions, and Composer:

```sh
composer install --prefer-dist
composer test
composer analyze
composer format:check
composer build
```

The build produces `build/Zuri.phar`, verifies required resources and PHP syntax inside the archive, and runs movement regressions against the packaged classes. Source tests cover queued terrain snapshots, interaction cancellation, detection grace, violations and release metadata.

A runtime smoke test starts the compiled plugin on a real PocketMine server, verifies metrics at startup and after two scheduled refreshes, and checks clean shutdown. It covers the default metrics delay and zero/negative delays. Supply a compatible PocketMine-MP PHAR:

```sh
composer test:runtime -- /path/to/PocketMine-MP.phar
```

The build workflow runs this test on PocketMine-MP **5.44.3**. Runtime smoke tests and synthetic movement regressions do not replace gameplay testing with actual Bedrock clients.

## Releases

The release workflow accepts a version through manual dispatch or a commit subject such as `release: v1.4.0-BETA`. It marks versions with a suffix as **Pre-release** and sets `make_latest=false`. A plain version such as `1.4.0` is treated as stable and may become Latest.

Use `v1.4.0-BETA` for the current rewrite. A stable release requires further gameplay and compatibility validation for the supported checks.

## Feedback and contributions

Report bugs at [ReinfyTeam/Zuri issues](https://github.com/ReinfyTeam/Zuri/issues). Include the Zuri version, PocketMine-MP version, relevant check, configuration, other plugins and the steps needed to reproduce the behavior. You can also join the [community Discord](https://discord.com/invite/7u7qKsvSxg).

The [wiki](https://github.com/ReinfyTeam/Zuri/wiki) contains historical documentation; verify its API examples against the current source before using them with 1.4.0-BETA.

The project originated as a continuation and rewrite of [ReallyCheat](https://github.com/hachkingtohach1/ReallyCheat/) by [hachkingtohach1](https://github.com/hachkingtohach1/). Current bundled dependencies are listed in [composer.json](composer.json) and pinned in [composer.lock](composer.lock).

Support the maintainers through [Ko-Fi](https://ko-fi.com/xqwtxon) or [Patreon](https://patreon.com/xwertxy). Stars and reproducible bug reports are also appreciated.
