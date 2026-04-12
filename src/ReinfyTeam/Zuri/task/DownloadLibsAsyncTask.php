<?php

declare(strict_types=1);

namespace ReinfyTeam\Zuri\task;

use pocketmine\command\ConsoleCommandSender;
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

