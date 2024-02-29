<?php

namespace ReinfyTeam\Zuri\checks\moving;

use pocketmine\block\BlockTypeIds;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\Event;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;

class FastLadder extends Check {
	public function getName() : string {
		return "FastLadder";
	}

	public function getSubType() : string {
		return "A";
	}

	public function ban() : bool {
		return false;
	}

	public function kick() : bool {
		return true;
	}

	public function flag() : bool {
		return false;
	}

	public function captcha() : bool {
		return false;
	}

	public function maxViolations() : int {
		return 2;
	}

	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if($event instanceof PlayerMoveEvent) {
			$lastY = $event->getFrom()->getY();
			$newY = $event->getTo()->getY();
			$player = $playerAPI->getPlayer();
			
			$x = intval($player->getLocation()->getX());
			$z = intval($player->getLocation()->getZ());
			
			$checkLadderLastX = $player->getWorld()->getBlockAt($x, intval($lastY), $z)->getTypeId() === BlockTypeIds::LADDER;
			$checkLadderNewY = $player->getWorld()->getBlockAt($x, intval($newY), $z)->getTypeId() === BlockTypeIds::LADDER;
			
			$diff = abs($newY - $lastY);
			
			if($checkLadderLastX || $checkLadderNewY) {
				if($diff > 0.5){ // impossible 0.6~
					$this->failed($playerAPI);
				}
				$this->debug($playerAPI, "lastY=$lastY, newY=$newY, diffY=$diff");
			}
		}
	}
}