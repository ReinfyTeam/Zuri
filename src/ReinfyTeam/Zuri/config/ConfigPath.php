<?php

namespace ReinfyTeam\Zuri\config;

interface ConfigPath {
	
	public const CONFIG_VERSION = "2.0.0";

	public const CURRENT_CONFIG_VERSION = "zuri.config-version";
	public const ASYNC_BATCH_SIZE = "zuri.async.batch_size";
	public const ASYNC_MAX_WORKER = "zuri.async.max_worker";
}