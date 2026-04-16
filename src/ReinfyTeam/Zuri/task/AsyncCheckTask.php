<?php

declare(strict_types=1);

namespace ReinfyTeam\Zuri\task;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use ReinfyTeam\Zuri\check\ResultsHandler;
use ReinfyTeam\Zuri\ZuriAC;
use Closure;

class AsyncCheckTask extends AsyncTask {

	private $batchCheck;
	
	public function __construct(array $batchCheck) {
		$checkBatch = [];
		foreach ($batchCheck as $checkData) {
			$eventData = (isset($checkData["data"]["eventData"]) ? serialize($checkData["data"]["eventData"]) : null);
			$player = $checkData["data"]["player"]->jsonSerialize();
			$check = serialize($checkData["data"]["check"]);
			$constants = serialize(ZuriAC::getConstants()->export());
			$checkBatch[] = serialize(["eventData" => $eventData, "player" => $player, "check" => $check, "constants" => $constants]);
		}

		$this->batchCheck = serialize($checkBatch);
	}

	public function onRun() : void {
		
		$results = [];
		foreach (unserialize($this->batchCheck) as $checkData) {
			$checkData = unserialize($checkData);
			$check = unserialize($checkData["check"]);
			$constants = unserialize($checkData["constants"]);
			
			$result = [];
			
			$playerData = $checkData["player"] ?? null;
			$eventData = $checkData["eventData"] ?? null;

			$result["result"] = $check::check(["eventData" => $eventData, "playerData" => $playerData, "constantData" => $constants]);
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