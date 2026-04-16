<?php

namespace ReinfyTeam\Zuri;

use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemOnEntityTransactionData;
use pocketmine\Server;
use ReinfyTeam\Zuri\check\Check;
use ReinfyTeam\Zuri\ZuriAC;
use ReinfyTeam\Zuri\player\PlayerManager;
use pocketmine\player\Player;
use pocketmine\util\BlockUtil;
use pocketmine\event\player\PlayerMoveEvent;

class EventListener implements Listener {

    public function onDataPacketReceive(DataPacketReceiveEvent $event) : void {
        $player = $event->getOrigin()->getPlayer();
        $packet = $event->getPacket();

        if (!$player instanceof Player || !$player->isConnected() || !$player->spawned) {
            return;
        }
        
        $playerZuri = PlayerManager::get($player);

        if ($event->isCancelled()) {
			$playerZuri->setRecentlyCancelledEvent(microtime(true));
		}

        if ($packet instanceof LevelSoundEventPacket) {
			if (
                $packet->sound === LevelSoundEvent::ATTACK_NODAMAGE || 
                $packet->sound === LevelSoundEvent::ATTACK_STRONG || 
                $packet->sound === LevelSoundEvent::ATTACK || 
                $packet->sound === LevelSoundEvent::ITEM_USE_ON ||
                $packet->sound === LevelSoundEvent::BLOCK_CLICK ||
                $packet->sound === LevelSoundEvent::BLOCK_CLICK_FAIL
            ) {
				$playerZuri->addCPS();
			}
		}

        if ($packet instanceof InventoryTransactionPacket) {
			if ($packet->trData instanceof UseItemOnEntityTransactionData) {
				$playerZuri->addCPS();
			}
		}

        if ($packet instanceof PlayerAuthInputPacket) {
            $playerZuri->setPitch($packet->getPitch());
            $playerZuri->setYaw($packet->getYaw());
            $playerZuri->setHeadYaw($packet->getHeadYaw());
            $playerZuri->setMoveVecX($packet->getMoveVecX());
            $playerZuri->setMoveVecZ($packet->getMoveVecZ());
            $playerZuri->setInputMode($packet->getInputMode());
            $playerZuri->setPlayMode($packet->getPlayMode());
            $playerZuri->setInteractionMode($packet->getInteractionMode());
            $playerZuri->setTick($packet->getTick());
            $playerZuri->setDelta($packet->getDelta());
            $playerZuri->setRawMove($packet->getRawMove());
        }

        ZuriAC::getCheckRegistry()->spawnCheck($player, Check::TYPE_PACKET);
    }

    public function onPlayerMove(PlayerMoveEvent $event) : void {
        $player = $event->getPlayer();

        if (!$player instanceof Player || !$player->isConnected() || !$player->spawned) {
            return;
        }

        $playerZuri = PlayerManager::get($player);

        if ($event->isCancelled()) {
			$playerZuri->setRecentlyCancelledEvent(microtime(true));
		}

        $playerZuri->setMovement($event->getFrom(), $event->getTo());
        $playerZuri->setOnGround(BlockUtil::isOnGround($event->getTo(), 0) || BlockUtil::isOnGround($event->getTo(), 1));
        
        $playerZuri->isOnGround()
            ? $playerZuri->setLastGroundY($player->getPosition()->getY())
            : $playerZuri->setLastNoGroundY($player->getPosition()->getY());
        
        if (BlockUtil::onSlimeBlock($event->getTo(), 0) || BlockUtil::onSlimeBlock($event->getTo(), 1)) {
			$playerZuri->setSlimeBlockTicks(microtime(true));
		}
        
        $playerZuri->setOnIce(BlockUtil::isOnIce($event->getTo(), 1) || BlockUtil::isOnIce($event->getTo(), 2));

        $playerZuri->setOnStairs(BlockUtil::isOnStairs($event->getTo(), 0) || BlockUtil::isOnStairs($event->getTo(), 1));
		$playerZuri->setUnderBlock(BlockUtil::isOnGround($player->getLocation(), -2));
		$playerZuri->setTopBlock(BlockUtil::isOnGround($player->getLocation(), 1));
		$playerZuri->setInLiquid(BlockUtil::isOnLiquid($event->getTo(), 0) || BlockUtil::isOnLiquid($event->getTo(), 1));
		$playerZuri->setOnAdhesion(BlockUtil::isOnAdhesion($event->getTo(), 0));
		$playerZuri->setOnPlant(BlockUtil::isOnPlant($event->getTo(), 0));
		$playerZuri->setOnDoor(BlockUtil::isOnDoor($event->getTo(), 0));
		$playerZuri->setOnCarpet(BlockUtil::isOnCarpet($event->getTo(), 0));
		$playerZuri->setOnPlate(BlockUtil::isOnPlate($event->getTo(), 0));
		$playerZuri->setOnSnow(BlockUtil::isOnSnow($event->getTo(), 0));
		$playerZuri->setLastMoveTick((double) Server::getInstance()->getTick());

        $frictionBlock = $player->getWorld()->getBlock($player->getPosition()->getSide(Facing::DOWN));

		$playerZuri->setExternalData(ExternalDataPath::FRICTION_FACTOR, $playerZuri->isOnGround() ? $frictionBlock->getFrictionFactor() : ConstantValue::getConstant(ConstantPath::FRICTION_FACTOR));

        ZuriAC::getCheckRegistry()->spawnCheck($player, Check::TYPE_PLAYER);
    }


}