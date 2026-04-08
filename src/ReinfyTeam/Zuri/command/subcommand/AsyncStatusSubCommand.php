<?php

declare(strict_types=1);

namespace ReinfyTeam\Zuri\command\subcommand;

use CortexPE\Commando\BaseSubCommand;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use ReinfyTeam\Zuri\task\CheckAsyncTask;

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
