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
use pocketmine\Server;
use pocketmine\utils\Internet;
use pocketmine\utils\TextFormat;
use ReinfyTeam\Zuri\config\ConfigManager;
use ReinfyTeam\Zuri\config\ConfigPaths;
use function date;
use function json_decode;
use function round;
use function strtotime;

class UpdateCheckerAsyncTask extends AsyncTask {
	private string $currentVersion;

	public function __construct(string $currentVersion) {
		$this->currentVersion = $currentVersion;
	}

	public function onRun() : void {
		$result = Internet::getURL(
			"https://api.github.com/repos/ReinfyTeam/Zuri/releases/latest",
			10,
			[],
			$err
		);
		$this->setResult([$result ?? null, $err]);
	}

	public function onCompletion() : void {
		$server = Server::getInstance();
		$prefix = ConfigManager::getData(ConfigPaths::PREFIX) . " ";

		[$result, $error] = $this->getResult();

		if ($error !== null || $result === null) {
			$server->getLogger()->notice($prefix . TextFormat::RED . "An error occurred while checking updates from GitHub: {$error}. Please check your internet connection and try again.");
			return;
		}

		$json = json_decode($result->getBody(), true);
		if ($json === null) {
			$server->getLogger()->notice($prefix . TextFormat::RED . "Failed to decode update information.");
			return;
		}

		$latestVersion = $json["tag_name"] ?? "";
		$currentVersionTag = "v" . $this->currentVersion;

		if ($latestVersion === $currentVersionTag) {
			$server->getLogger()->notice($prefix . TextFormat::GREEN . "No updates found. Enjoy!");
			return;
		}

		// Version mismatch - there is an update
		$isPrerelease = $json["prerelease"] ?? false;
		$versionLabel = $isPrerelease ? $latestVersion . " (PRE-RELEASE)" : $latestVersion;

		$asset = $json["assets"][0] ?? null;
		$downloadUrl = $asset["browser_download_url"] ?? "N/A";
		$fileSizeKB = isset($asset["size"]) ? round($asset["size"] / 1000, 2) : "N/A";
		$downloadCount = $asset["download_count"] ?? "N/A";
		$branch = $json["target_commitish"] ?? "N/A";
		$publishTime = isset($json["published_at"]) ? date('F j, Y', strtotime($json["published_at"])) : "N/A";

		$server->getLogger()->warning($prefix . TextFormat::AQUA . "A new latest version of Zuri is released! ({$publishTime})");
		$server->getLogger()->warning($prefix . TextFormat::AQUA . "Current Version: {$currentVersionTag}");
		$server->getLogger()->warning($prefix . TextFormat::AQUA . "Latest Version: {$versionLabel} ({$branch})");
		$server->getLogger()->warning($prefix . TextFormat::AQUA . "Download Count: {$downloadCount}");
		$server->getLogger()->warning($prefix . TextFormat::AQUA . "Download: {$downloadUrl} ({$fileSizeKB} KB)");
	}
}
