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
use ReinfyTeam\Zuri\ZuriAC;
use function file_put_contents;
use function is_array;
use function is_object;
use function is_string;
use function method_exists;
use function strlen;
use const DIRECTORY_SEPARATOR;

final class DownloadLibsAsyncTask extends AsyncTask {
	private const DOWNLOAD_URL = "https://github.com/ReinfyTeam/LibVapmPMMP/releases/latest/download/LibVapmPMMP.phar";

	public function __construct(private string $pluginsPath) {
	}

	public function onRun() : void {
		$result = Internet::getURL(self::DOWNLOAD_URL, 30, [], $err);
		$body = null;
		$statusCode = 0;
		if (is_object($result) && method_exists($result, "getBody")) {
			$body = $result->getBody();
			if (method_exists($result, "getCode")) {
				$statusCode = (int) $result->getCode();
			}
		}

		$this->setResult([
			"body" => is_string($body) ? $body : "",
			"error" => is_string($err) ? $err : "",
			"status" => $statusCode,
		]);
	}

	public function onCompletion() : void {
		$server = Server::getInstance();
		$result = $this->getResult();
		if (!is_array($result)) {
			$server->getLogger()->error(Lang::get(LangKeys::STARTUP_VAPM_AUTO_DOWNLOAD_FAILED, ["error" => "invalid async result"]));
			$server->getPluginManager()->disablePlugin(ZuriAC::getInstance());
			return;
		}

		$error = is_string($result["error"] ?? null) ? $result["error"] : "";
		$body = is_string($result["body"] ?? null) ? $result["body"] : "";
		$status = (int) ($result["status"] ?? 0);
		if ($error !== "") {
			$server->getLogger()->error(Lang::get(LangKeys::STARTUP_VAPM_AUTO_DOWNLOAD_FAILED, ["error" => $error]));
			$server->getPluginManager()->disablePlugin(ZuriAC::getInstance());
			return;
		}
		if ($body === "" || strlen($body) < 1024) {
			$server->getLogger()->error(Lang::get(LangKeys::STARTUP_VAPM_AUTO_DOWNLOAD_FAILED, ["error" => "invalid download payload (status={$status})"]));
			$server->getPluginManager()->disablePlugin(ZuriAC::getInstance());
			return;
		}

		$target = $this->pluginsPath . DIRECTORY_SEPARATOR . "LibVapmPMMP.phar";
		$written = @file_put_contents($target, $body);
		if ($written === false) {
			$server->getLogger()->error(Lang::get(LangKeys::STARTUP_VAPM_AUTO_DOWNLOAD_FAILED, ["error" => "unable to write {$target}"]));
			$server->getPluginManager()->disablePlugin(ZuriAC::getInstance());
			return;
		}

		$server->getLogger()->warning(Lang::get(LangKeys::STARTUP_VAPM_AUTO_DOWNLOADED, ["path" => $target]));
		$server->getLogger()->warning(Lang::get(LangKeys::STARTUP_VAPM_RESTARTING));
		$server->shutdown();
	}
}

