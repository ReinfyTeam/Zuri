<?php

declare(strict_types=1);

namespace ReinfyTeam\Zuri\task;

use pocketmine\Server;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use Throwable;
use vennv\vapm\ClosureThread;
use function json_decode;
use function is_array;
use function is_string;

class CheckAsyncTask {
	public static function dispatch(string $checkClass, string $playerName, array $payload) : void {
		$thread = new ClosureThread(
			static function (string $checkClass, string $playerName, array $payload) : array {
				try {
					if (!method_exists($checkClass, 'evaluateAsync')) {
						return ['error' => 'Missing evaluateAsync()'];
					}

					return $checkClass::evaluateAsync($payload);
				} catch (Throwable $throwable) {
					return ['error' => $throwable->getMessage()];
				}
			},
			[$checkClass, $playerName, $payload]
		);
		$thread->start()->then(function(string $output) use ($checkClass, $playerName) : void {
			$result = json_decode($output, true);
			if (!is_array($result) || isset($result['error'])) {
				return;
			}

			$player = Server::getInstance()->getPlayerExact($playerName);
			if ($player === null || !$player->isOnline() || !$player->spawned) {
				return;
			}

			$playerAPI = PlayerAPI::getAPIPlayer($player);
			/** @var Check $check */
			$check = new $checkClass();
			self::applyResult($check, $playerAPI, $result);
		});
	}

	private static function applyResult(Check $check, PlayerAPI $playerAPI, array $result) : void {
		foreach (($result['set'] ?? []) as $key => $value) {
			$playerAPI->setExternalData((string) $key, $value);
		}
		foreach (($result['unset'] ?? []) as $key) {
			$playerAPI->unsetExternalData((string) $key);
		}

		if (isset($result['debug']) && is_string($result['debug']) && $result['debug'] !== '') {
			$check->debug($playerAPI, $result['debug']);
		}

		if (!empty($result['failed'])) {
			try {
				$check->failed($playerAPI);
			} catch (Throwable) {
			}
		}
	}
}
