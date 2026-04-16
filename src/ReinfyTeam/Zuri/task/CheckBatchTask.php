<?php

namespace ReinfyTeam\Zuri\task;

use pocketmine\scheduler\Task;
use ReinfyTeam\Zuri\ZuriAC;
use ReinfyTeam\Zuri\check\ResultsHandler;
use pocketmine\Server;

class CheckBatchTask extends Task {

    public function onRun() : void {

        $batchInstance = ZuriAC::getWorker();

        if (!$batchInstance->isReady()) {
            return;
        }

        $batches = $batchInstance->drain();
        foreach ($batches as $batch) {
            Server::getInstance()->getAsyncPool()->submitTask(
                new AsyncCheckTask($batch)
            );
        }
    }
}