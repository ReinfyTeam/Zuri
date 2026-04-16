<?php

declare(strict_types=1);

namespace ReinfyTeam\Zuri\task;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use ReinfyTeam\Zuri\check\ResultsHandler;
use Closure;

class AsyncCheckTask extends AsyncTask {

	private $batchCheck;
	
	public function __construct(array $batchCheck) {
		$checkBatch = [];
		foreach ($batchCheck as $checkData) {
			$player = $checkData["player"]->jsonSerialize();
			$check = serialize($checkData["check"]);

			$checkBatch[] = serialize(["player" => $player, "check" => $check]);
		}

		$this->batchCheck = serialize($checkBatch);
	}

	public function onRun() : void {
		
		$results = [];
		foreach (unserialize($this->batchCheck) as $checkData) {
			$checkData = unserialize($checkData);
			$check = unserialize($checkData["check"]);
			
			$result = [];
			
			$playerData = $checkData["player"];

			$result["failed"] = $check::check($playerData);
			$result["check"] = $check::class;
			$result["player"] = $playerData["name"];
			
			$results[] = $result;
		}

		$this->setResult(serialize($results));
	}
	
	public function onCompletion() : void {
		$results = unserialize($this->getResult());
		foreach ($results as $result) {
			ResultsHandler::handle($result);
		}
	}
    
}