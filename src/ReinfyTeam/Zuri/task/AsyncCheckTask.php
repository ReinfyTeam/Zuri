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
		foreach ($batchCheck as $data) {
			$checkData = $data["checkData"];

			$playerData = PlayerManager::get($checkData["player"]);

			$player = isset($checkData["player"]) ? $playerData->jsonSerialize() : null;
			$check = serialize($checkData["check"]);

			$constants = serialize(ZuriAC::getConstants()->export());

			$data = isset($checkData["data"]) ? serialize($checkData["data"]) : null;
			
			$checkBatch[] = serialize([
				"type" => $checkData["type"],
				"player" => $player,
				"check" => $check,
				"data" => $data,
				"constants" => $constants
			]);
		}

		$this->batchCheck = serialize($checkBatch);
	}

	public function onRun() : void {
		
		$results = [];
		foreach (unserialize($this->batchCheck) as $checkData) {
			$checkData = unserialize($checkData);

			$check = unserialize($checkData["check"]);
			$type = $checkData["type"];

			$constants = unserialize($checkData["constants"]);
			
			$playerData = isset($checkData["player"]) ? json_decode($checkData["player"], true) : null;
			$data = isset($checkData["data"]) ? unserialize($checkData["data"]) : null;

			$result = [];

			$result["result"] = $check::check([
				"type" => $type,
				"data" => $data,
				"playerData" => $playerData,
				"constantData" => $constants
			]);

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