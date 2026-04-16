<?php

namespace ReinfyTeam\Zuri\check;

use pocketmine\Server;
use ReinfyTeam\Zuri\player\PlayerManager;
use ReinfyTeam\Zuri\ZuriAC;
use ReinfyTeam\Zuri\config\ConfigPath;

/**
 * Handles the results of checks and applies violations to players accordingly.
 * Responsible handling results and thresholds violations depending on the player conditions.
 */
final class ResultsHandler {
    
    /**
     * Responsible for handling punishments within the server.
     * Automatically adjust threshold based on the player condition.
     */
    public static function handle(array $results) : void {
        if (($player = Server::getInstance()->getPlayerExact($results["player"])) !== null) {
            $playerZuri = PlayerManager::get($player);
            if ($results["result"]["failed"]) {
                $playerZuri->addViolation($results["check"], self::adjustThreshold($player, $results["check"]));
            }
        }
    }

	/**
	 * Adjusts the threshold for punishments.
	 */
    public static function adjustThreshold(Player $player, Check $checkType) : float {
        $multiplier = 1.0;

		$server = Server::getInstance();
		
		// Apply ping adjustment
		$ping = $player->getNetworkSession()->getPing() ?? 0;
		$multiplier *= self::getPingMultiplier($ping, $checkType);

		$tps = $server->getTicksPerSecond();
		$maxPlayers = $server->getMaxPlayers();
		$onlinePlayers = count($server->getOnlinePlayers());

		// Apply TPS adjustment
		$multiplier *= self::getTpsMultiplier($tps, $checkType);

		// Apply load factors (current players)
		$playerLoad = $maxPlayers > 0 ? $onlinePlayers / $maxPlayers : 0.0;
		$tpsLoad = max(0.0, (20.0 - $tps) / 10.0); // 0 at 20 TPS, 1.0 at 10 TPS

		$loadFactor = min(1.0, ($playerLoad * 0.4) + ($tpsLoad * 0.6));

		// Apply load adjustment
		$multiplier *= self::getLoadMultiplier($loadFactor, $checkType);

		// Thresholds should only increase (more lenient), never decrease
		return $baseThreshold * max(1.0, $multiplier);
    }

    public static function getPingMultiplier(int $ping, Check $checkType) : float {
		// Movement checks are more sensitive to ping
		$sensitivity = ZuriAC::getConfigManager()->getData(ConfigPath::THRESHOLDS_PING . strtolower($checkType->getName()), ZuriAC::getConfigManager()->getData(ConfigPath::THRESHOLD_PING_DEFAULT_MULTIPLIER, 1.0));

		return match (true) {
			$ping < 50 => 1.0,
			$ping < 100 => 1.0 + (0.1 * $sensitivity),
			$ping < 150 => 1.0 + (0.2 * $sensitivity),
			$ping < 200 => 1.0 + (0.35 * $sensitivity),
			$ping < 300 => 1.0 + (0.5 * $sensitivity),
			$ping < 400 => 1.0 + (0.7 * $sensitivity),
			default => 1.0 + (1.0 * $sensitivity),
		};


    }

    public static function getLoadMultiplier(float $loadFactor) : float {
		// Load affects all checks roughly equally
		return match (true) {
			$loadFactor < 0.3 => 1.0,
			$loadFactor < 0.5 => 1.05,
			$loadFactor < 0.7 => 1.1,
			$loadFactor < 0.9 => 1.15,
			default => 1.2,
		};
	}

    public static function getTpsMultiplier(float $tps, Check $checkType) : float {
		// Timer checks are most sensitive to TPS fluctuation

		$sensitivity = ZuriAC::getConfigManager()->getData(ConfigPath::THRESHOLDS_TPS . strtolower($checkType->getName()), ZuriAC::getConfigManager()->getData(ConfigPath::THRESHOLD_TPS_DEFAULT_MULTIPLIER, 1.0));

		return match (true) {
			$tps >= 19.5 => 1.0,
			$tps >= 18.0 => 1.0 + (0.1 * $sensitivity),
			$tps >= 16.0 => 1.0 + (0.25 * $sensitivity),
			$tps >= 14.0 => 1.0 + (0.5 * $sensitivity),
			$tps >= 10.0 => 1.0 + (0.8 * $sensitivity),
			default => 1.0 + (1.2 * $sensitivity), // Severe lag
		};
	}


}