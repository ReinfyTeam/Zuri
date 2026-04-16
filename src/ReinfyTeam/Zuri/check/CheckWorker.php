<?php

namespace ReinfyTeam\Zuri\check;

use pocketmine\utils\SingletonTrait;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use ReinfyTeam\Zuri\player\PlayerZuri;
use ReinfyTeam\Zuri\ZuriAC;
use ReinfyTeam\Zuri\config\ConfigPath;
use ReinfyTeam\Zuri\task\CheckBatchTask;

class CheckWorker {

    public function __construct(
        private readonly int $maxWorkers
    ) {
        // NO-OP
    }

    /** @var array<int, array> */
    private array $queue = [];

    public function queue(array|PlayerZuri $data, Check $check): void {
        if ($check->getType() === Check::TYPE_PLAYER || $check->getType() === Check::TYPE_PACKET) {
            $this->queue[] = [
                'type' => $check->getType(), 
                'data' => ['player' => $data, 'check' => $check]
            ];
        } else {
            $this->queue[] = [
                'type' => $check->getType(), 
                'data' => ['eventData' => $data, 'check' => $check]
            ];
        }
    }

    public function getSize(): int {
        return count($this->queue);
    }

    public function drain() : array {
        $queueSize = $this->getSize();

        $possibleWorkers = intdiv($queueSize, $this->getBatchSize());
        $workers = min($possibleWorkers, $this->maxWorkers);

        $batches = [];

        for ($i = 0; $i < $workers; $i++) {
            $batches[] = array_splice($this->queue, 0, $this->getBatchSize());
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
        return $this->getSize() !== 0 && $this->getSize() >= $this->getBatchSize();
    }

    public function getBatchSize(): int {
        return count(Server::getInstance()->getOnlinePlayers()) * count(ZuriAC::getCheckRegistry()->getChecks());
    }

    public static function spawnWorker(PluginBase $plugin): self {
        $plugin->getScheduler()->scheduleRepeatingTask(new CheckBatchTask(), 1);

        return new self(ZuriAC::getConfigManager()->getData(ConfigPath::ASYNC_MAX_WORKER, 4));
    }
}
