<?php

namespace ReinfyTeam\Zuri\check;

use pocketmine\Server;
use ReinfyTeam\Zuri\player\PlayerManager;

/**
 * Handles the results of checks and applies violations to players accordingly.
 * Responsible handling results and thresholds violations depending on the player conditions.
 */
final class ResultsHandler {
    public static function handle(array $results) : void {
        if (($player = Server::getInstance()->getPlayerExact($results["player"])) !== null) {
            $playerZuri = PlayerManager::get($player);
            if ($results["failed"]) {
                $playerZuri->addViolation($results["check"], 1);
            }
        }
    }
}