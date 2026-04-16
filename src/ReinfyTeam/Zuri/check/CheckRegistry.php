<?php

namespace ReinfyTeam\Zuri\check;

use pocketmine\utils\SingletonTrait;
use ReinfyTeam\Zuri\check\moving\speed\SpeedA;
use ReinfyTeam\Zuri\player\PlayerManager;
use pocketmine\player\Player;
use ReinfyTeam\Zuri\ZuriAC;

class CheckRegistry {

    /** @var Check[] */
    private array $checks = [];
    
    public function __construct(array $checks) {
        $this->checks = $checks;
    } 

    public function registerCheck(Check $check) : void {
        $this->checks[] = $check;
    }
    
    public function getChecks() : array {
        return $this->checks;
    }

    /**
     * Spawns all check based on its type.
     */
    public function spawnCheck(Player $player, int $type) : void {
        $playerZuri = PlayerManager::get($player);
        foreach ($this->getChecksByType($type) as $check) {
            ZuriAC::getWorker()->queue($playerZuri, $check);
        }
    }

    /**
     * Get checks by type (packet, player, event)
     * Needed for filtering checks when running them asynchronously, as we don't want to run player checks on packet data, for example.
     * 
      * @param int $type
      * @return Check[]
      * @see Check::TYPE_PACKET
      * @see Check::TYPE_PLAYER
      * @see Check::TYPE_EVENT
     */
    public function getChecksByType(int $type) : array {
        return array_filter($this->checks, function(Check $check) use ($type) {
            return $check->getType() === $type;
        });
    }

    /**
     * Load default checks.
     */
    public static function loadChecks() : self {
        return new self([
            new SpeedA()
        ]);
    }
}