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
 * @author ReinfyTeam
 * @link https://github.com/ReinfyTeam/
 *
 *
 */

declare(strict_types=1);

namespace ReinfyTeam\Zuri\task;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Internet;
use ReinfyTeam\Zuri\config\ConfigManager;
use ReinfyTeam\Zuri\config\ConfigPaths;
use function curl_exec;
use function curl_getinfo;
use function curl_init;
use function curl_setopt;
use function json_decode;

class UpdateCheckerAsyncTask extends AsyncTask {
	private $currentVersion;

	public function __construct(string $currentVersion) {
		$this->currentVersion = $currentVersion;
	}

	public function onRun() : void {
		$body = Internet::getURL("https://api.github.com/repos/ReinfyTeam/Zuri-Rewrite/releases/latest", 10, [], $err);
		$this->setResult([$body, $err]);
	}

	public function onCompletion() : void {
		$server = Server::getInstance();
		$result = $this->getResult();
		$name = "";
		$ver = "";
		$download_url = "";
		$noUpdates = false;
		var_dump($result[0]);
		if ($result[1] === null) {
			$json = json_decode($result[0]);
			if ($json !== false && $json !== null) {
				if (($ver = $json["tag_name"]) !== "v" . $currentVersion) {
					$name = $json["name"];
					if ($json["prerelease"]) {
						$ver = $ver . "-PRERELEASE";
					}
					$download_url = $json["assets"]["browser_download_url"];
					$noUpdates = false;
				} else {
					$noUpdates = true;
				}
			}
		} else {
			$server->getLogger()->notice(ConfigManager::getData(ConfigPaths::PREFIX) . " " . TextFormat::RED . "An error occur while checking updates from github. Error Code: " . $result[1] . ", Please check your internet connection, and try again.");
			return;
		}

		if ($noUpdates) {
			$server->getLogger()->notice(ConfigManager::getData(ConfigPaths::PREFIX) . " " . TextFormat::GREEN . "No updates found. Enjoy!");
		} else {
			$server->getLogger()->warning(ConfigManager::getData(ConfigPaths::PREFIX) . " " . TextFormat::AQUA . "A new version of Zuri is released!");
			$server->getLogger()->warning(ConfigManager::getData(ConfigPaths::PREFIX) . " " . TextFormat::AQUA . "Current Version: " . $this->currentVersion);
			$server->getLogger()->warning(ConfigManager::getData(ConfigPaths::PREFIX) . " " . TextFormat::AQUA . "Latest Version: " . $ver);
			$server->getLogger()->warning(ConfigManager::getData(ConfigPaths::PREFIX) . " " . TextFormat::AQUA . "Download: " . $download_url);
		}
	}
}