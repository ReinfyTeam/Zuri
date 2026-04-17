<?php

/*
 *
 *  ____           _            __           _____
 * |  _ \    ___  (_)  _ __    / _|  _   _  |_   _|   ___    __ _   _ __ ___
 * | |_) |  / _ \ | | | '_ \  | |_  | | | |   | |    / _ \  / _` | | '_ ` _ \
 * |  _ <  |  __/ | | | | | | |  _| | |_| |   | |   |  __/ | (_| | | | | | | |
 * |_| \_\  \___| |_| |_| |_| |_|    \__, |   |_|    \___|  \__,_| |_| |_| |_|
 *                                   |___/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Zuri attempts to enforce "vanilla Minecraft" mechanics, as well as preventing
 * players from abusing weaknesses in Minecraft or its protocol, making your server
 * more safe. Organized in different sections, various checks are performed to test
 * players doing, covering a wide range including flying and speeding, fighting
 * hacks, fast block breaking and nukers, inventory hacks, chat spam and other types
 * of malicious behaviour.
 *
 * @author ReinfyTeam
 * @link https://github.com/ReinfyTeam/
 *
 *
 */

declare(strict_types=1);

namespace ReinfyTeam\Zuri\config;

interface ConfigPath {
	public const CONFIG_VERSION = "2.0.0";

	public const CURRENT_CONFIG_VERSION = "zuri.config_version";

	public const ASYNC_BATCH_SIZE = "zuri.async.batch_size";
	public const ASYNC_MAX_WORKER = "zuri.async.max_worker";

	public const THRESHOLDS_PING = "zuri.thresholds.ping";
	public const THRESHOLDS_TPS = "zuri.thresholds.tps";
	public const THRESHOLD_PING_DEFAULT_MULTIPLIER = "zuri.thresholds.ping.default";
	public const THRESHOLD_TPS_DEFAULT_MULTIPLIER = "zuri.thresholds.tps.default";

	public const CHECKS = "zuri.checks";

	public const PUNISHMENT_BAN_DURATION = "zuri.punishment.ban.duration";
	
}