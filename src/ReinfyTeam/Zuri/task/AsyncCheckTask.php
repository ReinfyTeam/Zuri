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

namespace ReinfyTeam\Zuri\task;

use pocketmine\scheduler\AsyncTask;
use ReinfyTeam\Zuri\check\ResultsHandler;
use ReinfyTeam\Zuri\player\PlayerManager;
use ReinfyTeam\Zuri\ZuriAC;
use function serialize;
use function unserialize;

/**
 * Async task that executes batches of checks off the main thread.
 */
class AsyncCheckTask extends AsyncTask {
	/** @var string Serialized batch payload. */
	private $batchCheck;

	/**
	 * @param array $batchCheck Array of serialized check payloads.
	 */
	public function __construct(array $batchCheck) {
		static $checkBatch = [];
		foreach ($batchCheck as $data) {
			$checkData = $data["checkData"];

			$playerData = PlayerManager::get($checkData["player"]);

			$player = isset($checkData["player"]) ? $playerData->jsonSerialize() : null;
			$check = serialize($data["check"]);

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

	/**
	 * Executes the checks in the async worker and collects results.
	 */
	public function onRun() : void {
		static $results = [];
		foreach (unserialize($this->batchCheck) as $checkData) {
			$checkData = unserialize($checkData);

			$check = unserialize($checkData["check"]);
			$type = $checkData["type"];

			$constants = unserialize($checkData["constants"]);

			$playerData = isset($checkData["player"]) ? $checkData["player"] : null;
			$data = isset($checkData["data"]) ? unserialize($checkData["data"]) : null;

			static $result = [];

			$result["result"] = $check::check([
				"type" => $type,
				"data" => $data,
				"playerData" => $playerData,
				"constantData" => $constants
			]);

			$result["check"] = serialize($check);
			$result["player"] = $playerData["name"];

			$results[] = $result;
		}

		$this->setResult(serialize($results));
	}

	/**
	 * Called on the main thread when the async task completes.
	 * Processes results and delegates handling to ResultsHandler.
	 */
	public function onCompletion() : void {
		$results = unserialize($this->getResult());
		foreach ($results as $result) {
			ResultsHandler::handle($result);
		}
	}
}