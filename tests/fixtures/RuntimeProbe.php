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

namespace ZuriRuntimeTest;

use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskScheduler;
use ReflectionProperty;
use ReinfyTeam\Zuri\task\MetricsTask;
use ReinfyTeam\Zuri\ZuriAC;
use RuntimeException;
use Throwable;
use function count;
use function file_get_contents;
use function file_put_contents;
use function json_decode;
use function json_encode;

final class RuntimeProbe extends PluginBase {
	private array $stages = [];

	protected function onEnable() : void {
		try {
			$this->verifyMetrics("onEnable");
			if (count(ZuriAC::getCheckRegistry()->getChecks()) !== 2 || !ZuriAC::getCheckThread()->isRunning()) {
				throw new RuntimeException("Registered checks and the background coordinator must be active");
			}
			$period = json_decode(file_get_contents($this->getDataFolder() . "expectations.json"), true, 512, JSON_THROW_ON_ERROR)["period"];
			$zuri = $this->getServer()->getPluginManager()->getPlugin("Zuri");
			if (!$zuri instanceof ZuriAC) {
				throw new RuntimeException("Packaged Zuri must be loaded");
			}
			$metricsTasks = 0;
			foreach ((new ReflectionProperty(TaskScheduler::class, "tasks"))->getValue($zuri->getScheduler()) as $handler) {
				if ($handler->getTask() instanceof MetricsTask) {
					++$metricsTasks;
					if (!$handler->isRepeating() || $handler->getPeriod() !== $period) {
						throw new RuntimeException("Metrics must repeat at the configured seconds-to-ticks interval");
					}
				}
			}
			if ($metricsTasks !== 1) {
				throw new RuntimeException("Exactly one metrics task must be scheduled");
			}
			$this->getScheduler()->scheduleDelayedTask(new ClosureTask(function() : void {
				$this->poisonMetrics();
			}), 2);
			foreach ([1, 2] as $cycle) {
				$this->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($cycle) : void {
					try {
						$this->verifyMetrics("scheduled update " . $cycle);
						if ($cycle === 1) {
							$this->poisonMetrics();
						} else {
							$this->finish(null);
						}
					} catch (Throwable $error) {
						$this->finish($error);
					}
				}), $period * $cycle + 4);
			}
		} catch (Throwable $error) {
			$this->finish($error);
		}
	}

	private function poisonMetrics() : void {
		$metrics = ZuriAC::getMetricsData();
		$metrics->setServerTPS(-1.0);
		$metrics->setMaxPlayerCount(-1);
		$metrics->setPlayerCount(-1);
		$metrics->setMemoryUsage(-1.0);
	}

	private function verifyMetrics(string $stage) : void {
		$metrics = ZuriAC::getMetricsData();
		if ($metrics->getServerTPS() <= 0.0 || $metrics->getServerTPS() > 20.0 ||
			$metrics->getMaxPlayerCount() !== $this->getServer()->getMaxPlayers() ||
			$metrics->getPlayerCount() !== count($this->getServer()->getOnlinePlayers()) ||
			$metrics->getMemoryUsage() <= 0.0) {
			throw new RuntimeException("Server metrics were not initialized/refreshed at " . $stage);
		}
		$this->stages[] = ["stage" => $stage, "tick" => $this->getServer()->getTick(), "tps" => $metrics->getServerTPS(), "maxPlayers" => $metrics->getMaxPlayerCount()];
	}

	private function finish(?Throwable $error) : void {
		file_put_contents($this->getDataFolder() . "result.json", json_encode([
			"ok" => $error === null,
			"error" => $error?->getMessage(),
			"stages" => $this->stages,
			"zuriFile" => (new \ReflectionClass(ZuriAC::class))->getFileName()
		], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
		$this->getServer()->shutdown();
	}
}
