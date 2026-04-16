<?php

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
}