<?php

namespace ReinfyTeam\Zuri\events\player;

use pocketmine\player\Player;
use pocketmine\event\Event;
use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\event\player\PlayerEvent;
use ReinfyTeam\Zuri\player\PlayerAPI;

/**
 * Bruh??! Why this event? #BlamePocketMine
 *
 * Well, PocketMine-MP doesn't support checks for teleport by command.
 * Unlike bukkit, there's something called "TeleportCause" which specifies what 
 * is the cause of the teleportation. Which is not implemented in PocketMine-MP.
 * This will fix some probably issues when Speed (A/B) detects as malicious behaivor.
 *
 * Also, i created this event so it can easily cancel by plugins.
 */
class PlayerTeleportByCommandEvent extends PlayerEvent implements Cancellable{
	use CancellableTrait;
	
	public function __construct(Player $player){
		$this->player = $player;
	}
	
	public function call() : void {
		$playerAPI = PlayerAPI::getAPIPlayer($this->player);
		
		$playerAPI->setTeleportCommandTicks(microtime(true)); // set ticks 
	}
}