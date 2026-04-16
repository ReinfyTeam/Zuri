<?php


namespace ReinfyTeam\Zuri\utils;

final class WorldUtil {
    
    public static function isCurrentChunkIsLoaded(Player $player) : bool {
		return $player->getWorld()->isInLoadedTerrain($player->getLocation());
	}
}