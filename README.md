# Zuri

Zuri is a beta anticheat plugin for PocketMine-MP API 5. It observes player
movement, combat and block interactions, evaluates checks in worker threads,
and reports violations to the server or configured moderation commands.

[![PHPStan](https://github.com/ReinfyTeam/Zuri/actions/workflows/phpstan.yml/badge.svg)](https://github.com/ReinfyTeam/Zuri/actions/workflows/phpstan.yml)
[![Build](https://github.com/ReinfyTeam/Zuri/actions/workflows/build-phar.yml/badge.svg)](https://github.com/ReinfyTeam/Zuri/actions/workflows/build-phar.yml)
[![Latest release](https://img.shields.io/github/v/release/ReinfyTeam/Zuri)](https://github.com/ReinfyTeam/Zuri/releases/latest)
[![License](https://img.shields.io/github/license/ReinfyTeam/Zuri)](LICENSE)

> [!WARNING]
> Zuri is published as `1.4.2-BETA`. An anticheat can produce false positives
> when the server is lagging, a player has unusual latency, or another plugin
> changes movement or combat behavior. Test the configuration on a staging
> server before enabling automatic punishments on a live server.

## Requirements and compatibility

| Requirement | Supported scope |
| --- | --- |
| PocketMine-MP or Axolotl-PM | API `5.0.0` as declared in `plugin.yml` |
| PHP | `8.2` or newer in Composer metadata and CI |
| Runtime dependencies | Bundled into the built PHAR by the project build |

The repository pins Axolotl-PM `5.51.1` for development and PHPStan analysis.
The CI workflow checks PHP syntax, PHPStan at maximum level, code style and the
PHAR build. These checks do not certify every API 5 server revision or prove
that a check is accurate for every Bedrock client.

## Installation

1. Download a verified `Zuri.phar` from [GitHub Releases](https://github.com/ReinfyTeam/Zuri/releases).
2. Copy the PHAR to the server's `plugins/` directory.
3. Start the server once to create `plugin_data/Zuri/config.yml`.
4. Review the check thresholds, world bypass list, alerts and punishment commands.
5. Start with punishments disabled or set to a non-destructive test command.
6. Observe the alerts and violation history before enabling kicks or bans.

Zuri registers the command aliases `/zuri`, `/anticheat`, `/ac` and `/zuriac`.
The command permission is `zuri.command` by default.

## Current checks

The current source registers these check groups:

| Group | Checks |
| --- | --- |
| Movement | `SpeedA`, `SpeedB`, `FlyA`, `FlyB`, `NoSlowA` |
| Combat | `ReachA`, `ReachB`, `HitboxA`, `HitboxB`, `VelocityA`, `VelocityB` |
| Blocks | `FastBreakA`, `NukeA`, `ScaffoldA`, `TowerA` |
| Items | `FastUseA` |

The names describe the current implementation files. They are not a promise
that every check detects every client modification, and they may change during
the beta rewrite.

## Commands and permissions

The `/zuri` command currently exposes the debug subcommand through Commando.
The command aliases use the same handler.

| Command | Purpose |
| --- | --- |
| `/zuri` | Run the main Zuri command |
| `/zuri debug` | Toggle or inspect the debug mode exposed by the current build |
| `/anticheat debug` | Use the same command through the compatibility alias |
| `/ac debug` | Use the same command through the compatibility alias |
| `/zuriac debug` | Use the same command through the compatibility alias |

The configured permissions are:

| Permission | Default use |
| --- | --- |
| `zuri.command` | Use the Zuri command as an operator |
| `zuri.bypass` | Bypass checks for selected players |
| `zuri.alerts` | Receive violation and pre-violation alerts |

Permission names can be changed in `zuri.permissions` inside the configuration.

## Configuration

Zuri creates `plugin_data/Zuri/config.yml`. The important sections are:

| Section | Purpose |
| --- | --- |
| `zuri.language` | Select the active language and message prefix |
| `zuri.threads` | Set thread count, worker count and queue capacity |
| `zuri.metrics` | Set the metrics update delay |
| `zuri.results_handler` | Set the result-processing delay |
| `zuri.thresholds` | Adjust ping and TPS sensitivity multipliers |
| `zuri.punishments` | Configure native or command-based kicks and bans |
| `zuri.world_bypass` | Use a blacklist or whitelist of bypass worlds |
| `zuri.alerts` | Enable pre-violation and violation messages |
| `zuri.checks` | Enable checks and set pre-violation, maximum violation and punishment values |

The loader checks `zuri.config_version` and refreshes an outdated configuration
from the bundled resource. Back up the file before changing it, and keep the
version key managed by the plugin.

Native punishment settings use the server's kick or ban system. Command-based
settings dispatch the configured command with `{player}` replaced by the
affected player's name. Treat those commands as trusted server configuration.

## Integrations

Zuri can use the bundled DiscordWebhookAPI dependency for webhook reporting.
Commando handles the command parser and SimplePacketHandler provides packet
hooks used by the plugin. These dependencies are prepared by the build and are
not separate plugins to copy into the server's `plugins/` directory.

## Build and verification

The repository uses Composer and Pharynx for the plugin build. The CI workflows
run the following checks:

```sh
composer install --prefer-dist --ignore-platform-req=ext-webrtc
composer run-script format:check
composer run-script analyze
composer run-script build
```

The build workflow uploads `build/Zuri.phar`. The release workflow downloads the
successful artifact from the same commit before creating a GitHub Release. A
local build or CI result does not replace a live false-positive test on the
target server.

## License and support

Zuri is released under the LGPL-2.1 license in [LICENSE](LICENSE). Report a
false positive with the server version, Zuri version, relevant configuration,
latency and a complete log excerpt. Do not publish private player data in an
issue. Use [GitHub Issues](https://github.com/ReinfyTeam/Zuri/issues) for bug
reports and feature requests.
