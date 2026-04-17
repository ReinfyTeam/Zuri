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
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerJumpEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\entity\EntityMotionEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use ReinfyTeam\Zuri\utils\BlockUtil;
use ReinfyTeam\Zuri\utils\Utils;
use ReinfyTeam\Zuri\player\ExternalDataPath;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\inventory\InventoryOpenEvent;
use pocketmine\event\inventory\InventoryCloseEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\inventory\ArmorInventory;
use pocketmine\math\Vector3;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\event\server\CommandEvent;
use pocketmine\event\entity\EntityShootBowEvent;
use ReflectionClass;

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
            $playerZuri->setStartedJumping($packet->getInputFlags()->get(PlayerAuthInputFlags::START_JUMPING));

            $frictionBlock = $player->getWorld()->getBlock($player->getPosition()->getSide(Facing::DOWN));
            $playerZuri->setExternalData(ExternalDataPath::FRICTION_FACTOR, $playerZuri->isOnGround() ? $frictionBlock->getFrictionFactor() : ZuriAC::getConstants()->getConstant(ConstantPath::FRICTION_FACTOR));
            
            $lastDistanceXZ = $playerZuri->getExternalData(ExternalDataPath::LAST_DISTANCE_XZ);
            $frictionFactor = $playerZuri->getExternalData(ExternalDataPath::FRICTION_FACTOR);
            $playerZuri->setExternalData(ExternalDataPath::MOMENTUM, MathUtil::getMomentum($lastDistanceXZ, $frictionFactor));

            $movement = MathUtil::getMovement($player, new Vector3(max(-1, min(1, $packet->getMoveVecZ())), 0, max(-1, min(1, $packet->getMoveVecX()))));
            $playerZuri->setExternalData(ExternalDataPath::MOVEMENT, $movement);

            $movementMultiplier = Utils::getMovementMultiplier($player);
			$acceleration = MathUtil::getAcceleration($movement, $movementMultiplier, $frictionFactor, $playerZuri->isOnGround());
            $playerZuri->setExternalData(ExternalDataPath::MOVEMENT_MULTIPLIER, $movementMultiplier);
            $playerZuri->setExternalData(ExternalDataPath::ACCELERATION, $acceleration);
        }

        ZuriAC::getCheckRegistry()->spawnCheck([
            "type" => (new ReflectionClass($packet))->getShortName(),
            "player" => $player
        ], Check::TYPE_PACKET);
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
		$playerZuri->setBlockAbove(BlockUtil::getBlockAbove($player)->isSolid());

        ZuriAC::getCheckRegistry()->spawnCheck([
            "type" => (new ReflectionClass($event))->getShortName(),
            "player" => $player
        ], Check::TYPE_PLAYER);
    }

    public function onMotion(EntityMotionEvent $event) : void {
        $player = $event->getEntity();

		if (!$player instanceof Player || !$player->isConnected() || !$player->spawned) {
            return;
        }

        $playerZuri = PlayerManager::get($player);

        if ($event->isCancelled()) {
			$playerZuri->setRecentlyCancelledEvent(microtime(true));
		}

        $currentMotion = $playerZuri->getMotion();
        $newMotion = $event->getVector();

        $playerZuri->setMotion($currentMotion->addVector($newMotion));

        ZuriAC::getCheckRegistry()->spawnCheck([
            "type" => (new ReflectionClass($event))->getShortName(),
            "player" => $player
        ], Check::TYPE_PLAYER);
    }

    public function onInventoryTransaction(InventoryTransactionEvent $event) : void {
        $player = $event->getPlayer();

        if (!$player instanceof Player || !$player->isConnected() || !$player->spawned) {
            return;
        }

        $playerZuri = PlayerManager::get($player);

        if ($event->isCancelled()) {
			$playerZuri->setRecentlyCancelledEvent(microtime(true));
		}

        foreach ($event->getTransaction()->getInventories() as $inventory) {
			$playerZuri->setTransactionArmorInventory(($inventory instanceof ArmorInventory));
		}

        ZuriAC::getCheckRegistry()->spawnCheck([
            "type" => (new ReflectionClass($event))->getShortName(),
            "player" => $player
        ], Check::TYPE_PLAYER);
    }

    public function onInventoryOpen(InventoryOpenEvent $event) : void {
        $player = $event->getPlayer();

        if (!$player instanceof Player || !$player->isConnected() || !$player->spawned) {
            return;
        }

        $playerZuri = PlayerManager::get($player);

        if ($event->isCancelled()) {
			$playerZuri->setRecentlyCancelledEvent(microtime(true));
		}

        $playerZuri->setInventoryOpen(true);

        ZuriAC::getCheckRegistry()->spawnCheck([
            "type" => (new ReflectionClass($event))->getShortName(),
            "player" => $player
        ], Check::TYPE_PLAYER);
    }

    public function onInventoryClose(InventoryCloseEvent $event) : void {
		$player = $event->getPlayer();

        if (!$player instanceof Player || !$player->isConnected() || !$player->spawned) {
            return;
        }

        $playerZuri = PlayerManager::get($player);

        if ($event->isCancelled()) {
			$playerZuri->setRecentlyCancelledEvent(microtime(true));
		}

        $playerZuri->setInventoryOpen(false);

        ZuriAC::getCheckRegistry()->spawnCheck([
            "type" => (new ReflectionClass($event))->getShortName(),
            "player" => $player
        ], Check::TYPE_PLAYER);
	}

    public function onEntityTeleport(EntityTeleportEvent $event) : void {
        $player = $event->getEntity();

		if (!$player instanceof Player || !$player->isConnected() || !$player->spawned) {
            return;
        }

        $playerZuri = PlayerManager::get($player);

        if ($event->isCancelled()) {
			$playerZuri->setRecentlyCancelledEvent(microtime(true));
		}

        $playerZuri->setTeleportTicks(microtime(true));

        ZuriAC::getCheckRegistry()->spawnCheck([
            "type" => (new ReflectionClass($event))->getShortName(),
            "player" => $player,
            "data" => [
                "from" => Utils::vector3ToArray($event->getFrom()->asVector3()),
                "fromWorld" => $event->getFrom()->getWorld()->getFolderName(),
                "to" => Utils::vector3ToArray($event->getTo()->asVector3()),
                "toWorld" => $event->getTo()->getWorld()->getFolderName()
            ]
        ], Check::TYPE_PLAYER);
    }

    public function onPlayerJump(PlayerJumpEvent $event) : void {
        $player = $event->getPlayer();

		if (!$player instanceof Player || !$player->isConnected() || !$player->spawned) {
            return;
        }

        $playerZuri = PlayerManager::get($player);

        if ($event->isCancelled()) {
			$playerZuri->setRecentlyCancelledEvent(microtime(true));
		}

        $playerZuri->setJumpTicks(microtime(true));

        ZuriAC::getCheckRegistry()->spawnCheck([
            "type" => (new ReflectionClass($event))->getShortName(),
            "player" => $player
        ], Check::TYPE_PLAYER);
    }

    public function onPlayerJoin(PlayerJoinEvent $event) : void {
        $player = $event->getPlayer();

		if (!$player instanceof Player || !$player->isConnected() || !$player->spawned) {
            return;
        }

        $playerZuri = PlayerManager::get($player);

        $playerZuri->setJoinedAtTheTime(microtime(true));

        ZuriAC::getCheckRegistry()->spawnCheck([
            "type" => (new ReflectionClass($event))->getShortName(),
            "player" => $player
        ], Check::TYPE_PLAYER);
    }

    public function onPlayerPreLogin(PlayerPreLoginEvent $event) : void {
        ZuriAC::getCheckRegistry()->spawnCheck([
            "type" => (new ReflectionClass($event))->getShortName(),
            "data" => [
                "ip" => $event->getIp(),
                "port" => $event->getPort(),
                "isAuthRequired" => $event->isAuthRequired(),
                "getKickFlags" => $event->getKickFlags(),
                "isKickFlagSet" => $event->isKickFlagSet(),
                "getUsername" => $event->getPlayerInfo()->getUsername(),
                "getLocale" => $event->getPlayerInfo()->getLocale(),
                "getUuid" => $event->getPlayerInfo()->getUuid(),
                "getExtraData" => $event->getPlayerInfo()->getExtraData()
            ]
        ], Check::TYPE_EVENT);
    }

    public function onEntityDamage(EntityDamageEvent $event) : void {
		$player = $event->getEntity();

		if (!$player instanceof Player || !$player->isConnected() || !$player->spawned) {
            return;
        }

        $playerZuri = PlayerManager::get($player);

        if ($event->isCancelled()) {
			$playerZuri->setRecentlyCancelledEvent(microtime(true));
		}
		
        $playerZuri->setHurtTicks(microtime(true));

        ZuriAC::getCheckRegistry()->spawnCheck([
            "type" => (new ReflectionClass($event))->getShortName(),
            "player" => $player,
            "data" => [
                "cause" => $event->getCause(),
                "position" => Utils::vector3ToArray($event->getEntity()->getPosition()->asVector3()),
                "pitch" => $player->getLocation()->getPitch(),
                "yaw" => $player->getLocation()->getYaw()
            ]
        ], Check::TYPE_PLAYER);
	}

    public function onEntityDamageByEntity(EntityDamageByEntityEvent $event) : void {
        $damager = $event->getDamager();
        $player = $event->getEntity();

		if (!$player instanceof Player || !$player->isConnected() || !$player->spawned) {
            return;
        }

        if (!$damager instanceof Player || !$damager->isConnected() || !$damager->spawned) {
            return;
        }

        $playerZuri = PlayerManager::get($player);

        if ($event->isCancelled()) {
			$playerZuri->setRecentlyCancelledEvent(microtime(true));
		}

        $cause = $event->getCause();

        if ($cause === EntityDamageEvent::CAUSE_ENTITY_EXPLOSION || $cause === EntityDamageEvent::CAUSE_BLOCK_EXPLOSION) {
            $playerZuri->setExplosionTicks(microtime(true));
        }

        if ($cause === EntityDamageEvent::CAUSE_ENTITY_ATTACK) {
            $damagerZuri = PlayerManager::get($damager);

            $playerZuri->setAttackTicks(microtime(true));
            $damagerZuri->setAttackTicks(microtime(true));

            ZuriAC::getCheckRegistry()->spawnCheck([
                "type" => (new ReflectionClass($event))->getShortName(),
                "player" => $damager,
                "data" => [
                    "position" => Utils::vector3ToArray($player->getPosition()->asVector3()),
                    "pitch" => $player->getLocation()->getPitch(),
                    "yaw" => $player->getLocation()->getYaw()
                ]
            ], Check::TYPE_PLAYER);
        }
    }

    public function onProjectileHit(ProjectileHitEvent $event) {
        $projectile = $event->getEntity();
		$player = $projectile->getOwningEntity();

        if (!$player instanceof Player || !$player->isConnected() || !$player->spawned) {
            return;
        }

        $playerZuri = PlayerManager::get($player);

        if ($event->isCancelled()) {
            $playerZuri->setRecentlyCancelledEvent(microtime(true));
        }

        $playerZuri->setProjectileAttackTicks(microtime(true));

        ZuriAC::getCheckRegistry()->spawnCheck([
            "type" => (new ReflectionClass($event))->getShortName(),
            "player" => $player,
            "data" => [
                "projectileType" => $projectile->getTypeId(),
                "hitEntity" => $event->getHitEntity() ? $event->getHitEntity()->getId() : null,
                "hitBlock" => $event->getHitBlock() ? $event->getHitBlock()->getPosition() : null,
            ]
        ], Check::TYPE_PLAYER);
    }

    public function onPlayerDeath(PlayerDeathEvent $event) : void {
        $player = $event->getPlayer();

        if (!$player instanceof Player || !$player->isConnected() || !$player->spawned) {
            return;
        }

        $playerZuri = PlayerManager::get($player);

        $playerZuri->setDeathTicks(microtime(true));
    }

    public function onPlayerChat(PlayerChatEvent $event) : void {
        $player = $event->getPlayer();

        if (!$player instanceof Player || !$player->isConnected() || !$player->spawned) {
            return;
        }

        $playerZuri = PlayerManager::get($player);

        if ($event->isCancelled()) {
            $playerZuri->setRecentlyCancelledEvent(microtime(true));
        }

        ZuriAC::getCheckRegistry()->spawnCheck([
            "type" => (new ReflectionClass($event))->getShortName(),
            "player" => $player
        ], Check::TYPE_PLAYER);
    }

    public function onPlayerItemHeld(PlayerItemHeldEvent $event) : void {
        $player = $event->getPlayer();

        if (!$player instanceof Player || !$player->isConnected() || !$player->spawned) {
            return;
        }

        $playerZuri = PlayerManager::get($player);

        if ($event->isCancelled()) {
            $playerZuri->setRecentlyCancelledEvent(microtime(true));
        }

        ZuriAC::getCheckRegistry()->spawnCheck([
            "type" => (new ReflectionClass($event))->getShortName(),
            "player" => $player
        ], Check::TYPE_PLAYER);
    }

    public function onPlayerRegen(EntityRegainHealthEvent $event) : void {
        $player = $event->getEntity();

        if (!$player instanceof Player || !$player->isConnected() || !$player->spawned) {
            return;
        }

        $playerZuri = PlayerManager::get($player);

        if ($event->isCancelled()) {
            $playerZuri->setRecentlyCancelledEvent(microtime(true));
        }

        ZuriAC::getCheckRegistry()->spawnCheck([
            "type" => (new ReflectionClass($event))->getShortName(),
            "player" => $player,
            "data" => [
                "amount" => $event->getAmount(),
                "regainReason" => $event->getRegainReason()
            ]
        ], Check::TYPE_PLAYER);
    }

    public function onCommandEvent(CommandEvent $event) : void {
        $sender = $event->getSender();

        if (!$sender instanceof Player || !$sender->isConnected() || !$sender->spawned) {
            return;
        }

        $playerZuri = PlayerManager::get($sender);

        if ($event->isCancelled()) {
            $playerZuri->setRecentlyCancelledEvent(microtime(true));
        }

        $playerZuri->setCommandTicks(microtime(true));

        ZuriAC::getCheckRegistry()->spawnCheck([
            "type" => (new ReflectionClass($event))->getShortName(),
            "player" => $sender,
            "data" => [
                "command" => $event->getCommand()
            ]
        ], Check::TYPE_PLAYER);
    }

    public function onEntityShootBowEvent(EntityShootBowEvent $event) : void {
        $player = $event->getEntity();

        if (!$player instanceof Player || !$player->isConnected() || !$player->spawned) {
            return;
        }

        $playerZuri = PlayerManager::get($player);

        if ($event->isCancelled()) {
            $playerZuri->setRecentlyCancelledEvent(microtime(true));
        }

        $playerZuri->setBowShotTicks(microtime(true));

        ZuriAC::getCheckRegistry()->spawnCheck([
            "type" => (new ReflectionClass($event))->getShortName(),
            "player" => $player
        ], Check::TYPE_PLAYER);
    }

    public function onPlayerItemConsume(PlayerItemConsumeEvent $event) : void {
        $player = $event->getPlayer();

        if (!$player instanceof Player || !$player->isConnected() || !$player->spawned) {
            return;
        }

        $playerZuri = PlayerManager::get($player);

        if ($event->isCancelled()) {
            $playerZuri->setRecentlyCancelledEvent(microtime(true));
        }

        ZuriAC::getCheckRegistry()->spawnCheck([
            "type" => (new ReflectionClass($event))->getShortName(),
            "player" => $player
        ], Check::TYPE_PLAYER);
    }

    public function onDropItem(PlayerDropItemEvent $event) : void {
        $player = $event->getPlayer();

        if (!$player instanceof Player || !$player->isConnected() || !$player->spawned) {
            return;
        }

        $playerZuri = PlayerManager::get($player);

        if ($event->isCancelled()) {
            $playerZuri->setRecentlyCancelledEvent(microtime(true));
        }

        ZuriAC::getCheckRegistry()->spawnCheck([
            "type" => (new ReflectionClass($event))->getShortName(),
            "player" => $player,
            "data" => [
                "itemType" => $event->getItem()->getTypeId()
            ]
        ], Check::TYPE_PLAYER);
    }

    public function onProjectileLaunch(ProjectileLaunchEvent $event) : void {
        $player = $event->getEntity()->getOwningEntity();

        if (!$player instanceof Player || !$player->isConnected() || !$player->spawned) {
            return;
        }

        $playerZuri = PlayerManager::get($player);

        if ($event->isCancelled()) {
            $playerZuri->setRecentlyCancelledEvent(microtime(true));
        }
    
        ZuriAC::getCheckRegistry()->spawnCheck([
            "type" => (new ReflectionClass($event))->getShortName(),
            "player" => $player
        ], Check::TYPE_PLAYER);
    }
}