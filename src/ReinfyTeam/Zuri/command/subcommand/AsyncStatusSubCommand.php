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

namespace ReinfyTeam\Zuri\command\subcommand;

use CortexPE\Commando\BaseSubCommand;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use ReinfyTeam\Zuri\task\CheckAsyncTask;
use function implode;
use function round;

class AsyncStatusSubCommand extends BaseSubCommand {
	public function __construct(PluginBase $plugin) {
		parent::__construct($plugin, "async", "Show async pipeline status.");
	}

	protected function prepare() : void {
	}

	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void {
		$metrics = CheckAsyncTask::getMetrics();

		$lines = [
			TextFormat::YELLOW . "[Zuri] Async pipeline status:",
			TextFormat::GRAY . "Queue: " . TextFormat::WHITE . $metrics["queueSize"] .
				TextFormat::GRAY . " / " . TextFormat::WHITE . $metrics["maxQueueSize"],
			TextFormat::GRAY . "Workers in flight: " . TextFormat::WHITE . $metrics["inFlight"] .
				TextFormat::GRAY . " / " . TextFormat::WHITE . $metrics["maxConcurrentWorkers"],
			TextFormat::GRAY . "Total dispatched: " . TextFormat::WHITE . $metrics["totalDispatched"] .
				TextFormat::GRAY . ", completed: " . TextFormat::WHITE . $metrics["totalCompleted"] .
				TextFormat::GRAY . ", dropped: " . TextFormat::WHITE . $metrics["totalDropped"],
			TextFormat::GRAY . "Avg worker time: " . TextFormat::WHITE . round((float) $metrics["avgWorkerTime"], 4) . "s",
		];

		$sender->sendMessage(implode("\n", $lines));
	}
}
