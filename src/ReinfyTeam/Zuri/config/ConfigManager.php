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

use JsonException;
use pocketmine\utils\TextFormat;
use ReinfyTeam\Zuri\lang\Lang;
use ReinfyTeam\Zuri\lang\LangKeys;
use ReinfyTeam\Zuri\ZuriAC;
use function fclose;
use function file_exists;
use function implode;
use function is_array;
use function is_bool;
use function is_numeric;
use function is_resource;
use function is_string;
use function rename;
use function stream_get_contents;
use function yaml_parse;

class ConfigManager extends ConfigPaths {
	public static function getData(string $path, mixed $defaultValue = null) : mixed {
		return ZuriAC::getInstance()->getConfig()->getNested($path, $defaultValue);
	}

	/**
	 * @throws JsonException
	 */
	public static function setData(string $path, mixed $data) : void {
		ZuriAC::getInstance()->getConfig()->setNested($path, $data);
		ZuriAC::getInstance()->getConfig()->save();
	}

	public static function checkConfig() : void {
		if (!file_exists(ZuriAC::getInstance()->getDataFolder() . "config.yml")) {
			ZuriAC::getInstance()->saveResource("config.yml");
		}

		if (!file_exists(ZuriAC::getInstance()->getDataFolder() . "webhook.yml")) {
			ZuriAC::getInstance()->saveResource("webhook.yml");
		}

		if (!file_exists(ZuriAC::getInstance()->getDataFolder() . "lang/en_US.yml")) {
			ZuriAC::getInstance()->saveResource("lang/en_US.yml");
		}

		$pluginConfigResource = ZuriAC::getInstance()->getResource("config.yml");
		if (!is_resource($pluginConfigResource)) {
			$log = ZuriAC::getInstance()->getServer()->getLogger();
			$log->critical(self::getData(self::PREFIX) . TextFormat::RED . " Failed to load embedded config.yml resource.");
			ZuriAC::getInstance()->getServer()->getPluginManager()->disablePlugin(ZuriAC::getInstance());
			return;
		}

		$pluginConfigRaw = stream_get_contents($pluginConfigResource);
		fclose($pluginConfigResource);
		$pluginConfigParsed = $pluginConfigRaw === false ? false : yaml_parse($pluginConfigRaw);
		$config = ZuriAC::getInstance()->getConfig();
		$log = ZuriAC::getInstance()->getServer()->getLogger();
		if (!is_array($pluginConfigParsed)) {
			$log->critical(self::getData(self::PREFIX) . TextFormat::RED . " Invalid syntax. Currupted config.yml!");
			ZuriAC::getInstance()->getServer()->getPluginManager()->disablePlugin(ZuriAC::getInstance());
			return;
		}
		$pluginVersion = $pluginConfigParsed["zuri"]["version"] ?? null;
		if (is_string($pluginVersion) && $config->getNested("zuri.version") === $pluginVersion) {
			self::runStartupDiagnostics();
			return;
		}
		@rename(ZuriAC::getInstance()->getDataFolder() . "config.yml", ZuriAC::getInstance()->getDataFolder() . "old-config.yml");
		ZuriAC::getInstance()->saveResource("config.yml");
		$log->notice(self::getData(self::PREFIX) . TextFormat::RED . " Outdated configuration! Your config will be renamed as old-config.yml to backup your data.");
		self::runStartupDiagnostics();
	}

	public static function runStartupDiagnostics() : void {
		$logger = ZuriAC::getInstance()->getServer()->getLogger();
		$requiredPaths = [
			self::PREFIX,
			self::VERSION,
			self::ALERTS_ENABLE,
			self::BAN_ENABLE,
			self::KICK_ENABLE,
			self::CHECK,
			"zuri.async.max-concurrent-workers",
			"zuri.async.batch-size",
			"zuri.async.max-queue-size",
			"zuri.async.worker-timeout-seconds",
			"zuri.async.degraded-cooldown-seconds",
			"zuri.async.worker-target-ms",
		];

		$missing = [];
		foreach ($requiredPaths as $path) {
			if (ZuriAC::getInstance()->getConfig()->getNested($path, null) === null) {
				$missing[] = $path;
			}
		}
		if ($missing !== []) {
			$logger->warning(Lang::get(LangKeys::STARTUP_DIAG_MISSING_KEYS, ["keys" => implode(", ", $missing)]));
		}

		$maxWorkers = self::getData("zuri.async.max-concurrent-workers", 4);
		if (!is_numeric($maxWorkers) || (int) $maxWorkers < 1 || (int) $maxWorkers > 16) {
			$logger->warning(Lang::get(LangKeys::STARTUP_DIAG_MAX_WORKERS_RANGE));
		}

		$batchSize = self::getData("zuri.async.batch-size", 64);
		if (!is_numeric($batchSize) || (int) $batchSize < 1 || (int) $batchSize > 128) {
			$logger->warning(Lang::get(LangKeys::STARTUP_DIAG_BATCH_SIZE_RANGE));
		}

		$maxQueue = self::getData("zuri.async.max-queue-size", 2048);
		if (!is_numeric($maxQueue) || (int) $maxQueue < 32) {
			$logger->warning(Lang::get(LangKeys::STARTUP_DIAG_MAX_QUEUE_MIN));
		}

		$workerTimeout = self::getData("zuri.async.worker-timeout-seconds", 3.0);
		if (!is_numeric($workerTimeout) || (float) $workerTimeout < 0.1 || (float) $workerTimeout > 30.0) {
			$logger->warning(Lang::get(LangKeys::STARTUP_DIAG_WORKER_TIMEOUT_RANGE));
		}

		$degradedCooldown = self::getData("zuri.async.degraded-cooldown-seconds", 6.0);
		if (!is_numeric($degradedCooldown) || (float) $degradedCooldown < 0.1 || (float) $degradedCooldown > 120.0) {
			$logger->warning(Lang::get(LangKeys::STARTUP_DIAG_DEGRADED_COOLDOWN_RANGE));
		}

		$workerTargetMs = self::getData("zuri.async.worker-target-ms", 20.0);
		if (!is_numeric($workerTargetMs) || (float) $workerTargetMs < 1.0 || (float) $workerTargetMs > 250.0) {
			$logger->warning(Lang::get(LangKeys::STARTUP_DIAG_WORKER_TARGET_RANGE));
		}

		$correlationForceMultiplier = self::getData("zuri.correlation.force-escalation-multiplier", 2.5);
		if (!is_numeric($correlationForceMultiplier) || (float) $correlationForceMultiplier < 1.0 || (float) $correlationForceMultiplier > 20.0) {
			$logger->warning(Lang::get(LangKeys::STARTUP_DIAG_CORRELATION_MULTIPLIER_RANGE));
		}

		$correlationForceExtra = self::getData("zuri.correlation.force-escalation-extra-real-vl", 3);
		if (!is_numeric($correlationForceExtra) || (int) $correlationForceExtra < 0 || (int) $correlationForceExtra > 1000) {
			$logger->warning(Lang::get(LangKeys::STARTUP_DIAG_CORRELATION_EXTRA_RANGE));
		}

		$banEnable = self::getData(self::BAN_ENABLE, true);
		$kickEnable = self::getData(self::KICK_ENABLE, true);
		if (!is_bool($banEnable) || !is_bool($kickEnable)) {
			$logger->warning(Lang::get(LangKeys::STARTUP_DIAG_BAN_KICK_BOOL));
		}

		$confidenceThreshold = self::getData("zuri.confidence.threshold", 0.5);
		if (!is_numeric($confidenceThreshold) || (float) $confidenceThreshold < 0.0 || (float) $confidenceThreshold > 1.0) {
			$logger->warning(Lang::get(LangKeys::STARTUP_DIAG_CONFIDENCE_RANGE));
		}
	}
}
