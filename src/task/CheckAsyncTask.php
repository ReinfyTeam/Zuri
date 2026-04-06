<?php

declare(strict_types=1);

namespace ReinfyTeam\Zuri\task;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use Throwable;

class CheckAsyncTask extends AsyncTask {
	public function __construct(
		private readonly string $checkClass,
		private readonly string $playerName,
		private readonly array $payload
	) {
	}

	public function onRun() : void {
		try {
			$this->setResult($this->runEvaluation());
		} catch (Throwable $e) {
			$this->setResult(["error" => $e->getMessage()]);
		}
	}

	public function onCompletion() : void {
		$result = $this->getResult();
		if (!is_array($result) || isset($result["error"])) {
			return;
		}

		$player = Server::getInstance()->getPlayerExact($this->playerName);
		if ($player === null || !$player->isOnline() || !$player->spawned) {
			return;
		}

		$playerAPI = PlayerAPI::getAPIPlayer($player);
		/** @var Check $check */
		$check = new $this->checkClass();
		$this->applyResult($check, $playerAPI, $result);
	}

	private function runEvaluation() : array {
		$checkClass = $this->checkClass;
		if (!method_exists($checkClass, "evaluateAsync")) {
			return ["error" => "Missing evaluateAsync()"];
		}

		return $checkClass::evaluateAsync($this->payload);
	}

	private function applyResult(Check $check, PlayerAPI $playerAPI, array $result) : void {
		$this->applyStateChanges($playerAPI, $result);

		if (isset($result["debug"]) && is_string($result["debug"]) && $result["debug"] !== "") {
			$check->debug($playerAPI, $result["debug"]);
		}

		if (!empty($result["failed"])) {
			try {
				$check->failed($playerAPI);
			} catch (Throwable) {
			}
		}
	}

	private function applyStateChanges(PlayerAPI $playerAPI, array $result) : void {
		foreach (($result["set"] ?? []) as $key => $value) {
			$playerAPI->setExternalData((string) $key, $value);
		}
		foreach (($result["unset"] ?? []) as $key) {
			$playerAPI->unsetExternalData((string) $key);
		}
	}
}
