<?php

namespace ReinfyTeam\Zuri\player;

use pocketmine\player\Player;

class PlayerManager {
    
    /** @var PlayerZuri[] */
    private static array $players = [];
    
    public static function get(Player $player) : PlayerZuri {
        return self::$players[$player->getName()]->updateData() ??= PlayerZuri::create($player);
    }
    
    public static function add(Player $player) : void {
        self::$players[$player->getName()] = PlayerZuri::create($player);
    }
    
    public static function remove(Player $player) : void {
        unset(self::$players[$player->getName()]);
    }
}