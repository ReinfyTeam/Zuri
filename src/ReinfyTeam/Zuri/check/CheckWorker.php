<?php

namespace ReinfyTeam\Zuri\check;

use pocketmine\utils\SingletonTrait;
use pocketmine\plugin\PluginBase;
use ReinfyTeam\Zuri\player\PlayerZuri;
use ReinfyTeam\Zuri\config\ConfigManager;
use ReinfyTeam\Zuri\config\ConfigPath;
use ReinfyTeam\Zuri\task\CheckBatchTask;

class CheckWorker {

    public function __construct(
        private readonly int $batchSize, 
        private readonly int $maxWorkers
    ) {
        // NO-OP
    }

    /** @var array<int, array> */
    private array $queue = [];

    public function queue(PlayerZuri $player, Check $check): void {
        $this->queue[] = ['player' => $player, 'check' => $check];
    }

    public function getSize(): int {
        return count($this->queue);
    }

    public function drain() : array {
        $queueSize = $this->getSize();

        $possibleWorkers = intdiv($queueSize, $this->batchSize);
        $workers = min($possibleWorkers, $this->maxWorkers);

        $batches = [];

        for ($i = 0; $i < $workers; $i++) {
            $batches[] = array_splice($this->queue, 0, $this->batchSize);
        }

        return $batches;
    }

    public function getMaxWorkers(): int {
        return $this->maxWorkers;
    }

    public function clear(): void {
        $this->queue = [];
    }

    public function isReady(): bool {
        return $this->getSize() >= $this->getBatchSize();
    }

    public function getBatchSize(): int {
        return $this->batchSize;
    }

    public static function spawnWorker(PluginBase $plugin): self {
        $plugin->getScheduler()->scheduleRepeatingTask(new CheckBatchTask(), 1);

        return new self(ConfigManager::getData(ConfigPath::ASYNC_BATCH_SIZE, 1), ConfigManager::getData(ConfigPath::ASYNC_MAX_WORKER, 4));
    }
}
