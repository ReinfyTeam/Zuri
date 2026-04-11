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
use ReinfyTeam\Zuri\lang\Lang;
use ReinfyTeam\Zuri\lang\LangKeys;
use function date;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_numeric;
use function is_object;
use function is_string;
use function json_decode;
use function ltrim;
use function method_exists;
use function round;
use function strtotime;
use function trim;
use function version_compare;

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

		$resultData = $this->getResult();
		if (!is_array($resultData)) {
			$server->getLogger()->notice(Lang::get(LangKeys::UPDATE_ERROR, ["error" => "Invalid async result payload"]));
			return;
		}

		$result = $resultData[0] ?? null;
		$error = $resultData[1] ?? null;

		if ($error !== null || $result === null) {
			$errorMessage = is_string($error) ? $error : "unknown";
			$server->getLogger()->notice(Lang::get(LangKeys::UPDATE_ERROR, ["error" => $errorMessage]));
			return;
		}

		if (!is_object($result) || !method_exists($result, 'getBody')) {
			$server->getLogger()->notice(Lang::get(LangKeys::UPDATE_DECODE_FAILED));
			return;
		}

		$json = json_decode($result->getBody(), true);
		if (!is_array($json)) {
			$server->getLogger()->notice(Lang::get(LangKeys::UPDATE_DECODE_FAILED));
			return;
		}

		$latestVersion = is_string($json["tag_name"] ?? null) ? trim($json["tag_name"]) : "";
		$currentVersion = trim($this->currentVersion);
		$currentVersionTag = "v" . $currentVersion;
		$latestComparable = self::normalizeVersion($latestVersion);
		$currentComparable = self::normalizeVersion($currentVersion);

		$isUpToDate = false;
		if ($latestVersion === "" || $latestComparable === "" || $currentComparable === "") {
			$isUpToDate = $latestVersion === $currentVersionTag || $latestVersion === $currentVersion;
		} else {
			$isUpToDate = version_compare($latestComparable, $currentComparable, "<=");
		}

		if ($isUpToDate) {
			$server->getLogger()->notice(Lang::get(LangKeys::UPDATE_NONE));
			return;
		}

		// Version mismatch - there is an update
		$isPrerelease = is_bool($json["prerelease"] ?? null) ? $json["prerelease"] : false;
		$versionLabel = $isPrerelease ? $latestVersion . " (PRE-RELEASE)" : $latestVersion;

		$assets = $json["assets"] ?? [];
		$asset = is_array($assets) && isset($assets[0]) && is_array($assets[0]) ? $assets[0] : [];
		$downloadUrl = is_string($asset["browser_download_url"] ?? null) ? $asset["browser_download_url"] : "N/A";
		$sizeRaw = $asset["size"] ?? null;
		$fileSizeKB = (is_int($sizeRaw) || is_float($sizeRaw) || is_numeric($sizeRaw)) ? (string) round((float) $sizeRaw / 1000, 2) : "N/A";
		$downloadCountRaw = $asset["download_count"] ?? null;
		$downloadCount = (is_int($downloadCountRaw) || is_string($downloadCountRaw)) ? (string) $downloadCountRaw : "N/A";
		$branch = is_string($json["target_commitish"] ?? null) ? $json["target_commitish"] : "N/A";
		$publishedAt = $json["published_at"] ?? null;
		if (is_string($publishedAt)) {
			$publishedTimestamp = strtotime($publishedAt);
			$publishTime = $publishedTimestamp !== false ? date('F j, Y', $publishedTimestamp) : "N/A";
		} else {
			$publishTime = "N/A";
		}

		$server->getLogger()->warning(Lang::get(LangKeys::UPDATE_AVAILABLE, ["publishTime" => $publishTime]));
		$server->getLogger()->warning(Lang::get(LangKeys::UPDATE_CURRENT, ["currentVersion" => $currentVersionTag]));
		$server->getLogger()->warning(Lang::get(LangKeys::UPDATE_LATEST, ["latestVersion" => $versionLabel, "branch" => $branch]));
		$server->getLogger()->warning(Lang::get(LangKeys::UPDATE_DOWNLOADS, ["downloadCount" => $downloadCount]));
		$server->getLogger()->warning(Lang::get(LangKeys::UPDATE_DOWNLOAD_URL, ["downloadUrl" => $downloadUrl, "fileSizeKB" => $fileSizeKB]));
	}

	private static function normalizeVersion(string $version) : string {
		$normalized = ltrim(trim($version), "vV");
		return $normalized;
	}
}
