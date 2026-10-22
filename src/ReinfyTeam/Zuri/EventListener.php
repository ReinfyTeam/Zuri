<?php

/*
 *
 *  ____           _            __           _____
 * |  _ \    ___  (_)  _ __    / _|  _   _  |_   _|   ___    __ _   _ __ ___
 * | |_) |  / _ \ | | | '_ \  | |_  | | | |   | |    / _ \  / _` | | '_ ` _ \
 * |  _ <  |  __/ | | | | | | |  _| | |_| |   | |   |  __/ | (_| | | | | | | |
 * |_| \_\  \___| |_| |_| |_| |_|    \__, |   |_|    \___|  \__,_| |_| |_| |_|
 *                                   |___/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Zuri attempts to enforce "vanilla Minecraft" mechanics, as well as preventing
 * players from abusing weaknesses in Minecraft or its protocol, making your server
 * more safe. Organized in different sections, various checks are performed to test
 * players doing, covering a wide range including flying and speeding, fighting
 * hacks, fast block breaking and nukers, inventory hacks, chat spam and other types
 * of malicious behaviour.
 *
 * @author ReinfyTeam
 * @link https://github.com/ReinfyTeam/
 *
 *
 */

declare(strict_types=1);

namespace ReinfyTeam\Zuri;

use pocketmine\entity\projectile\EnderPearl;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityMotionEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\entity\ProjectileHitBlockEvent;
use pocketmine\event\entity\ProjectileHitEntityEvent;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\event\inventory\InventoryCloseEvent;
use pocketmine\event\inventory\InventoryOpenEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerJumpEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\server\CommandEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\inventory\ArmorInventory;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemOnEntityTransactionData;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemTransactionData;
use pocketmine\network\mcpe\protocol\types\PlayerAuthInputFlags;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\Utils as PMMPUtils;
use ReinfyTeam\Zuri\check\Check;
use ReinfyTeam\Zuri\config\ConstantPath;
use ReinfyTeam\Zuri\utils\BlockUtil;
use ReinfyTeam\Zuri\utils\Utils;
use ReinfyTeam\Zuri\utils\WorldUtil;
use function microtime;

/**
 * Central event listener for ZuriAC.
 *
 * Listens to various game and network events and forwards relevant data to checks.
 */
class EventListener implements Listener {
	/**
	 * Handles incoming data packets.
	 */
	public function onDataPacketReceive(DataPacketReceiveEvent $event) : void {
		$player = $event->getOrigin()->getPlayer();
		$packet = $event->getPacket();

		if (!$player instanceof Player || !$player->isConnected()) {
			return;
		}

		$playerZuri = ZuriAC::getPlayerManager()->get($player);
		$playerZuri->setSprinting($player->isSprinting());
		$playerZuri->setSneaking($player->isSneakPressed());
		$playerZuri->setGliding($player->isGliding());
		$playerZuri->setDead(!$player->isAlive());

		if ($event->isCancelled()) {
			$playerZuri->setRecentlyCancelledEvent(microtime(true));
		}

		if ($packet instanceof InventoryTransactionPacket) { // interacting blocks
			$trType = $packet->trData->getTypeId();
			if ($trType === UseItemOnEntityTransactionData::ID || $trType === UseItemTransactionData::ID) {
				$playerZuri->addCPS();
			}
		}

		if ($packet instanceof AnimatePacket) {
			if ($packet->action === AnimatePacket::ACTION_SWING_ARM) {
				$playerZuri->addCPS();
			}
		}

		if ($packet instanceof PlayerAuthInputPacket) {
			$playerZuri->setPitch($packet->getPitch());
			$playerZuri->setYaw($packet->getYaw());
			$playerZuri->setHeadYaw($packet->getHeadYaw());
			$playerZuri->setInputMode($packet->getInputMode());
			$playerZuri->setPlayMode($packet->getPlayMode());
			$playerZuri->setInteractionMode($packet->getInteractionMode());
			$playerZuri->setTick($packet->getTick());
			$playerZuri->setRawMove($packet->getRawMove());

			// Sprinting
			$inputFlags = $packet->getInputFlags();
			if ($inputFlags->get(PlayerAuthInputFlags::START_SPRINTING)) {
				$playerZuri->setSprinting(true);
			} elseif ($inputFlags->get(PlayerAuthInputFlags::STOP_SPRINTING)) {
				$playerZuri->setSprinting(false);
			}

			// Gliding
			if ($inputFlags->get(PlayerAuthInputFlags::START_GLIDING)) {
				$playerZuri->setGliding(true);
			} elseif ($inputFlags->get(PlayerAuthInputFlags::STOP_GLIDING)) {
				$playerZuri->setGliding(false);
			}

			// Crawling
			if ($inputFlags->get(PlayerAuthInputFlags::START_CRAWLING)) {
				$playerZuri->setCrawling(true);
			} elseif ($inputFlags->get(PlayerAuthInputFlags::STOP_CRAWLING)) {
				$playerZuri->setCrawling(false);
			}

			// Sneaking
			if ($inputFlags->get(PlayerAuthInputFlags::START_SNEAKING)) {
				$playerZuri->setRawSneaking(true);
			} elseif ($inputFlags->get(PlayerAuthInputFlags::STOP_SNEAKING)) {
				$playerZuri->setRawSneaking(false);
			}

			// Swimming
			if ($inputFlags->get(PlayerAuthInputFlags::START_SWIMMING)) {
				$playerZuri->setSwimming(true);
			} elseif ($inputFlags->get(PlayerAuthInputFlags::STOP_SWIMMING)) {
				$playerZuri->setSwimming(false);
			}

			if ($inputFlags->get(PlayerAuthInputFlags::JUMPING)) {
				$playerZuri->setJumping(true);
				$playerZuri->setJumpTicks(microtime(true));
			} else {
				$playerZuri->setJumping(false);
			}

			if ($inputFlags->get(PlayerAuthInputFlags::MISSED_SWING)) {
				$playerZuri->addCPS();
			}
		}

		ZuriAC::getCheckRegistry()->spawnCheck([
			"type" => PMMPUtils::getNiceClassName($packet),
			"player" => $player
		], Check::TYPE_PACKET);
	}

	/**
	 * Handles player move events.
	 *
	 * @priority MONITOR
	 * @handleCancelled
	 */
	public function onPlayerMove(PlayerMoveEvent $event) : void {
		$player = $event->getPlayer();

		if (!$player->isConnected()) {
			return;
		}

		$playerZuri = ZuriAC::getPlayerManager()->get($player);

		if ($event->isCancelled()) {
			$playerZuri->setRecentlyCancelledEvent(microtime(true));
			return;
		}

		$currentTick = Server::getInstance()->getTick();
		if ($playerZuri->getLastMoveTick() === $currentTick) { // per tick only check, to maximize performance and accuracy for larger playerbase
			return;
		}

		$playerZuri->setLastMoveTick($currentTick);

		$playerZuri->setMovement($event->getFrom(), $event->getTo());
		$playerZuri->addMovementSample($event->getFrom(), $event->getTo());

		$deltaY = $event->getTo()->getY() - $event->getFrom()->getY();
		$isOnGround = ($deltaY <= 0.0001) && (BlockUtil::isOnGround($event->getTo(), 0) || BlockUtil::isOnGround($event->getTo(), 1));
		$playerZuri->setOnGround($isOnGround);
		if ($isOnGround) {
			$playerZuri->setAirTicks(0.0);
			$playerZuri->setFallTicks(0.0);
			if ($playerZuri->getGroundTicks() <= 0.0) {
				$playerZuri->setGroundTicks(microtime(true));
			}
		} else {
			$playerZuri->setGroundTicks(0.0);
			if ($playerZuri->getAirTicks() <= 0.0) {
				$playerZuri->setAirTicks(microtime(true));
			}
			if ($deltaY > 0.0001) {
				$playerZuri->setJumping(true);
				$playerZuri->setJumpTicks(microtime(true));
				$playerZuri->setFallTicks(0.0);
			} elseif ($deltaY < -0.0001) {
				if ($playerZuri->getFallTicks() <= 0.0) {
					$playerZuri->setFallTicks(microtime(true));
				}
			}
		}

		$playerZuri->isOnGround()
			? $playerZuri->setLastGroundY($player->getPosition()->getY())
			: $playerZuri->setLastNoGroundY($player->getPosition()->getY());
		$playerZuri->setFlying($player->isFlying());
		$playerZuri->setOnSlime(BlockUtil::hasSlimeWithinVerticalGap($player->getBoundingBox(), $event->getTo(), 0.05) || BlockUtil::onSlimeBlock($event->getTo(), 1) || BlockUtil::onSlimeBlock($event->getTo(), 2));
		// Block related checks
		$playerZuri->setOnIce(BlockUtil::isOnIce($event->getTo(), 1) || BlockUtil::isOnIce($event->getTo(), 2));
		$playerZuri->setOnMagma(BlockUtil::isOnMagma($event->getTo(), 1) || BlockUtil::isOnMagma($event->getTo(), 2));
		$playerZuri->setUsingItem($player->isUsingItem());
		$playerZuri->setFoodLevel($player->getHungerManager()->getFood());
		$playerZuri->setOnStairs(BlockUtil::isOnStairs($event->getTo(), 0) || BlockUtil::isOnStairs($event->getTo(), 1));
		$playerZuri->setUnderBlock(BlockUtil::isOnGround($player->getLocation(), -2));
		$playerZuri->setTopBlock(BlockUtil::isOnGround($player->getLocation(), 1));
		$playerZuri->setOnWeb(BlockUtil::isOnWeb($event->getTo(), 0));
		$playerZuri->setInLiquid(BlockUtil::isOnLiquid($event->getTo(), 0) || BlockUtil::isOnLiquid($event->getTo(), 1));
		$playerZuri->setInLava(BlockUtil::isOnLava($event->getTo(), 0) || BlockUtil::isOnLava($event->getTo(), 1));
		$playerZuri->setOnUnderwater(BlockUtil::isOnWater($event->getTo(), 0) && !BlockUtil::isOnGround($event->getTo(), 0));
		$playerZuri->setTwoBlockPassage(BlockUtil::isTwoBlockPassage($event->getTo()));
		$playerZuri->setClimbing(BlockUtil::isOnAdhesion($event->getTo(), 0));
		$playerZuri->setOnPlant(BlockUtil::isOnPlant($event->getTo(), 0));
		$playerZuri->setOnDoor(BlockUtil::isOnDoor($event->getTo(), 0));
		$playerZuri->setOnCarpet(BlockUtil::isOnCarpet($event->getTo(), 0));
		$playerZuri->setOnPlate(BlockUtil::isOnPlate($event->getTo(), 0));
		$playerZuri->setOnSoul(BlockUtil::isOnSoul($event->getTo(), 1) || BlockUtil::isOnSoul($event->getTo(), 2));
		$playerZuri->setOnSnow(BlockUtil::isOnSnow($event->getTo(), 0));
		$playerZuri->setBlockAbove(BlockUtil::isSolid(BlockUtil::getBlockAbove($event->getTo())));
		$playerZuri->setGroundSolid(BlockUtil::isGroundSolid($player->getLocation()));
		$playerZuri->setInLava(BlockUtil::isInLava($player->getLocation()));

		ZuriAC::getCheckRegistry()->spawnCheck([
			"type" => PlayerMoveEvent::class,
			"player" => $player
		], Check::TYPE_PLAYER);
	}

	/**
	 * Handles entity motion events.
	 */
	public function onMotion(EntityMotionEvent $event) : void {
		$player = $event->getEntity();

		if (!$player instanceof Player || !$player->isConnected()) {
			return;
		}

		$playerZuri = ZuriAC::getPlayerManager()->get($player);

		if ($event->isCancelled()) {
			$playerZuri->setRecentlyCancelledEvent(microtime(true));
		}

		$playerZuri->setMotion($event->getVector());
	}

	/**
	 * Handles inventory transaction events.
	 */
	public function onInventoryTransaction(InventoryTransactionEvent $event) : void {
		$player = $event->getTransaction()->getSource();

		if (!$player->isConnected()) {
			return;
		}

		$playerZuri = ZuriAC::getPlayerManager()->get($player);

		if ($event->isCancelled()) {
			$playerZuri->setRecentlyCancelledEvent(microtime(true));
		}

		foreach ($event->getTransaction()->getInventories() as $inventory) {
			$playerZuri->setTransactionArmorInventory(($inventory instanceof ArmorInventory));
		}

		ZuriAC::getCheckRegistry()->spawnCheck([
			"type" => PMMPUtils::getNiceClassName($event),
			"player" => $player
		], Check::TYPE_PLAYER);
	}

	/**
	 * Handles inventory open events.
	 */
	public function onInventoryOpen(InventoryOpenEvent $event) : void {
		$player = $event->getPlayer();

		if (!$player->isConnected()) {
			return;
		}

		$playerZuri = ZuriAC::getPlayerManager()->get($player);

		if ($event->isCancelled()) {
			$playerZuri->setRecentlyCancelledEvent(microtime(true));
		}

		$playerZuri->setInventoryOpen(true);

		ZuriAC::getCheckRegistry()->spawnCheck([
			"type" => PMMPUtils::getNiceClassName($event),
			"player" => $player
		], Check::TYPE_PLAYER);
	}

	/**
	 * Handles inventory close events.
	 */
	public function onInventoryClose(InventoryCloseEvent $event) : void {
		$player = $event->getPlayer();

		if (!$player->isConnected()) {
			return;
		}

		$playerZuri = ZuriAC::getPlayerManager()->get($player);

		$playerZuri->setInventoryOpen(false);

		ZuriAC::getCheckRegistry()->spawnCheck([
			"type" => PMMPUtils::getNiceClassName($event),
			"player" => $player
		], Check::TYPE_PLAYER);
	}

	/**
	 * Handles entity teleport events.
	 */
	public function onEntityTeleport(EntityTeleportEvent $event) : void {
		$player = $event->getEntity();

		if (!$player instanceof Player || !$player->isConnected()) {
			return;
		}

		$playerZuri = ZuriAC::getPlayerManager()->get($player);

		if ($event->isCancelled()) {
			$playerZuri->setRecentlyCancelledEvent(microtime(true));
		}

		$playerZuri->setTeleportTicks(microtime(true));
		$player->sendMessage("you teleported");

		ZuriAC::getCheckRegistry()->spawnCheck([
			"type" => PMMPUtils::getNiceClassName($event),
			"player" => $player,
			"data" => [
				"from" => Utils::vector3ToArray($event->getFrom()->asVector3()),
				"fromWorld" => $event->getFrom()->getWorld()->getFolderName(),
				"to" => Utils::vector3ToArray($event->getTo()->asVector3()),
				"toWorld" => $event->getTo()->getWorld()->getFolderName()
			]
		], Check::TYPE_PLAYER);
	}

	/**
	 * Handles player jump events.
	 */
	public function onPlayerJump(PlayerJumpEvent $event) : void {
		$player = $event->getPlayer();

		if (!$player->isConnected()) {
			return;
		}

		$playerZuri = ZuriAC::getPlayerManager()->get($player);

		$playerZuri->setJumpTicks(microtime(true));

		ZuriAC::getCheckRegistry()->spawnCheck([
			"type" => PMMPUtils::getNiceClassName($event),
			"player" => $player
		], Check::TYPE_PLAYER);
	}

	/**
	 * Handles player join events.
	 */
	public function onPlayerJoin(PlayerJoinEvent $event) : void {
		$player = $event->getPlayer();

		if (!$player->isConnected()) {
			return;
		}

		$playerZuri = ZuriAC::getPlayerManager()->get($player);

		$playerZuri->setJoinedAtTheTime(microtime(true));
		$playerZuri->setTeleportTicks(microtime(true));

		ZuriAC::getCheckRegistry()->spawnCheck([
			"type" => PMMPUtils::getNiceClassName($event),
			"player" => $player
		], Check::TYPE_PLAYER);
	}

	/**
	 * Handles pre-login events.
	 */
	public function onPlayerPreLogin(PlayerPreLoginEvent $event) : void {
		ZuriAC::getCheckRegistry()->spawnCheck([
			"type" => PMMPUtils::getNiceClassName($event),
			"data" => [
				"ip" => $event->getIp(),
				"port" => $event->getPort(),
				"isAuthRequired" => $event->isAuthRequired(),
				"getKickFlags" => $event->getKickFlags(),
				"isKickFlagSet" => $event->getKickFlags() !== [],
				"getUsername" => $event->getPlayerInfo()->getUsername(),
				"getLocale" => $event->getPlayerInfo()->getLocale(),
				"getUuid" => $event->getPlayerInfo()->getUuid(),
				"getExtraData" => $event->getPlayerInfo()->getExtraData()
			]
		], Check::TYPE_EVENT);
	}

	/**
	 * Handles entity damage events.
	 */
	public function onEntityDamage(EntityDamageEvent $event) : void {
		$player = $event->getEntity();

		if (!$player instanceof Player || !$player->isConnected()) {
			return;
		}

		$playerZuri = ZuriAC::getPlayerManager()->get($player);

		if ($event->isCancelled()) {
			$playerZuri->setRecentlyCancelledEvent(microtime(true));
		}

		$playerZuri->setHurtTicks(microtime(true));
		if (
			$event->getCause() === EntityDamageEvent::CAUSE_ENTITY_EXPLOSION ||
			$event->getCause() === EntityDamageEvent::CAUSE_BLOCK_EXPLOSION
		) {
			$playerZuri->setExplosionTicks(microtime(true));
		}

		if ($event->getCause() === EntityDamageEvent::CAUSE_ENTITY_ATTACK) {
			return; // we don't want to double-count this event, as it will be handled in onEntityDamageByEntity
		}

		ZuriAC::getCheckRegistry()->spawnCheck([
			"type" => PMMPUtils::getNiceClassName($event),
			"player" => $player,
			"data" => [
				"cause" => $event->getCause(),
				"position" => Utils::vector3ToArray($event->getEntity()->getPosition()->asVector3()),
				"pitch" => $player->getLocation()->getPitch(),
				"yaw" => $player->getLocation()->getYaw()
			]
		], Check::TYPE_PLAYER);
	}

	/**
	 * Handles entity damage-by-entity events.
	 */
	public function onEntityDamageByEntity(EntityDamageByEntityEvent $event) : void {
		$damager = $event->getDamager();
		$player = $event->getEntity();

		if (!$player instanceof Player || !$player->isConnected()) {
			return;
		}

		if (!$damager instanceof Player || !$damager->isConnected() || !$damager->spawned) {
			return;
		}

		$playerZuri = ZuriAC::getPlayerManager()->get($player);

		if ($event->isCancelled()) {
			$playerZuri->setRecentlyCancelledEvent(microtime(true));
		}
			
		$damagerZuri = ZuriAC::getPlayerManager()->get($damager);
		$playerZuri->setAttackTicks(microtime(true));
		$damagerZuri->setAttackTicks(microtime(true));

		$playerZuri->setBlockCollisionBoxes(WorldUtil::collectBlockCollisionBoxes($player->getWorld(), $player->getPosition()->asVector3(), $damager->getBoundingBox(), Utils::readFloat(ZuriAC::getConstants()->getConstant(ConstantPath::HITBOX_TOLERANCE))));

		ZuriAC::getCheckRegistry()->spawnCheck([
			"type" => PMMPUtils::getNiceClassName($event),
			"player" => $damager, // damager instead of player, because the check is for the attacker
			"data" => [
				"enemy" => $player
			]
		], Check::TYPE_PLAYER);
	}

	/**
	 * Handles projectile hit events.
	 *
	 * @return void
	 */
	public function onProjectileHit(ProjectileHitEvent $event) {
		$projectile = $event->getEntity();
		$player = $projectile->getOwningEntity();

		if (!$player instanceof Player || !$player->isConnected()) {
			return;
		}

		$playerZuri = ZuriAC::getPlayerManager()->get($player);

		$playerZuri->setProjectileAttackTicks(microtime(true));

		ZuriAC::getCheckRegistry()->spawnCheck([
			"type" => PMMPUtils::getNiceClassName($event),
			"player" => $player,
			"data" => [
				"projectileType" => $projectile::getNetworkTypeId(),
				"hitEntity" => $event instanceof ProjectileHitEntityEvent ? $event->getEntityHit()->getId() : null,
				"hitBlock" => $event instanceof ProjectileHitBlockEvent ? Utils::vector3ToArray($event->getBlockHit()->getPosition()) : null,
			]
		], Check::TYPE_PLAYER);
	}

	/**
	 * Handles player death events.
	 */
	public function onPlayerDeath(PlayerDeathEvent $event) : void {
		$player = $event->getPlayer();

		if (!$player->isConnected()) {
			return;
		}

		$playerZuri = ZuriAC::getPlayerManager()->get($player);

		$playerZuri->setDeathTicks(microtime(true));
		$playerZuri->setTeleportTicks(microtime(true));
	}

	/**
	 * Handles player chat events.
	 */
	public function onPlayerChat(PlayerChatEvent $event) : void {
		$player = $event->getPlayer();

		if (!$player->isConnected()) {
			return;
		}

		$playerZuri = ZuriAC::getPlayerManager()->get($player);

		if ($event->isCancelled()) {
			$playerZuri->setRecentlyCancelledEvent(microtime(true));
		}

		ZuriAC::getCheckRegistry()->spawnCheck([
			"type" => PMMPUtils::getNiceClassName($event),
			"player" => $player
		], Check::TYPE_PLAYER);
	}

	/**
	 * Handles player item held change events.
	 */
	public function onPlayerItemHeld(PlayerItemHeldEvent $event) : void {
		$player = $event->getPlayer();

		if (!$player->isConnected()) {
			return;
		}

		$playerZuri = ZuriAC::getPlayerManager()->get($player);

		if ($event->isCancelled()) {
			$playerZuri->setRecentlyCancelledEvent(microtime(true));
		}

		ZuriAC::getCheckRegistry()->spawnCheck([
			"type" => PMMPUtils::getNiceClassName($event),
			"player" => $player
		], Check::TYPE_PLAYER);
	}

	/**
	 * Handles entity regain health events.
	 */
	public function onPlayerRegen(EntityRegainHealthEvent $event) : void {
		$player = $event->getEntity();

		if (!$player instanceof Player || !$player->isConnected()) {
			return;
		}

		$playerZuri = ZuriAC::getPlayerManager()->get($player);

		if ($event->isCancelled()) {
			$playerZuri->setRecentlyCancelledEvent(microtime(true));
		}

		ZuriAC::getCheckRegistry()->spawnCheck([
			"type" => PMMPUtils::getNiceClassName($event),
			"player" => $player,
			"data" => [
				"amount" => $event->getAmount(),
				"regainReason" => $event->getRegainReason()
			]
		], Check::TYPE_PLAYER);
	}

	/**
	 * Handles command events issued by players or console.
	 */
	public function onCommandEvent(CommandEvent $event) : void {
		$sender = $event->getSender();

		if (!$sender instanceof Player || !$sender->isConnected()) {
			return;
		}

		$playerZuri = ZuriAC::getPlayerManager()->get($sender);

		if ($event->isCancelled()) {
			$playerZuri->setRecentlyCancelledEvent(microtime(true));
		}

		$playerZuri->setTeleportCommandTicks(microtime(true));

		ZuriAC::getCheckRegistry()->spawnCheck([
			"type" => PMMPUtils::getNiceClassName($event),
			"player" => $sender,
			"data" => [
				"command" => $event->getCommand()
			]
		], Check::TYPE_PLAYER);
	}

	/**
	 * Handles entity shooting bow events.
	 */
	public function onEntityShootBowEvent(EntityShootBowEvent $event) : void {
		$player = $event->getEntity();

		if (!$player instanceof Player || !$player->isConnected()) {
			return;
		}

		$playerZuri = ZuriAC::getPlayerManager()->get($player);

		if ($event->isCancelled()) {
			$playerZuri->setRecentlyCancelledEvent(microtime(true));
		}

		$playerZuri->setBowShotTicks(microtime(true));

		ZuriAC::getCheckRegistry()->spawnCheck([
			"type" => PMMPUtils::getNiceClassName($event),
			"player" => $player
		], Check::TYPE_PLAYER);
	}

	/**
	 * Handles player item consume events.
	 */
	public function onPlayerItemConsume(PlayerItemConsumeEvent $event) : void {
		$player = $event->getPlayer();

		if (!$player->isConnected()) {
			return;
		}

		$playerZuri = ZuriAC::getPlayerManager()->get($player);

		if ($event->isCancelled()) {
			$playerZuri->setRecentlyCancelledEvent(microtime(true));
		}

		ZuriAC::getCheckRegistry()->spawnCheck([
			"type" => PMMPUtils::getNiceClassName($event),
			"player" => $player
		], Check::TYPE_PLAYER);
	}

	/**
	 * Handles player item drop events.
	 */
	public function onDropItem(PlayerDropItemEvent $event) : void {
		$player = $event->getPlayer();

		if (!$player->isConnected()) {
			return;
		}

		$playerZuri = ZuriAC::getPlayerManager()->get($player);

		if ($event->isCancelled()) {
			$playerZuri->setRecentlyCancelledEvent(microtime(true));
		}

		$playerZuri->setDropTicks(microtime(true));

		ZuriAC::getCheckRegistry()->spawnCheck([
			"type" => PMMPUtils::getNiceClassName($event),
			"player" => $player,
			"data" => [
				"itemType" => $event->getItem()->getTypeId()
			]
		], Check::TYPE_PLAYER);
	}

	/**
	 * Handles projectile launch events.
	 */
	public function onProjectileLaunch(ProjectileLaunchEvent $event) : void {
		$projectile = $event->getEntity();
		$player = $projectile->getOwningEntity();

		if (!$player instanceof Player || !$player->isConnected()) {
			return;
		}

		$playerZuri = ZuriAC::getPlayerManager()->get($player);

		if ($projectile instanceof EnderPearl) {
			$playerZuri->setTeleportTicks(microtime(true));
		}

		if ($event->isCancelled()) {
			$playerZuri->setRecentlyCancelledEvent(microtime(true));
		}

		ZuriAC::getCheckRegistry()->spawnCheck([
			"type" => PMMPUtils::getNiceClassName($event),
			"player" => $player
		], Check::TYPE_PLAYER);
	}

	/**
	 * @priority MONITOR
	 * @handleCancelled
	 */
	public function onPlayerInteract(PlayerInteractEvent $event) : void {
		$player = $event->getPlayer();
		if (!$player->isConnected()) {
			return;
		}

		$playerZuri = ZuriAC::getPlayerManager()->get($player);
		if ($event->isCancelled() || !$event->useBlock()) {
			$playerZuri->setRecentlyCancelledEvent(microtime(true));
			return;
		}

		ZuriAC::getCheckRegistry()->spawnCheck([
			"type" => PMMPUtils::getNiceClassName($event),
			"player" => $player,
			"data" => [
				"blockPos" => Utils::vector3ToArray($event->getBlock()->getPosition()->asVector3()),
				"blockType" => $event->getBlock()->getTypeId(),
			]
		], Check::TYPE_PLAYER);
	}

	public function onPlayerBreak(BlockBreakEvent $event) : void {
		$block = $event->getBlock();
		$x = $block->getPosition()->getX();
		$z = $block->getPosition()->getZ();
		$player = $event->getPlayer();

		$playerZuri = ZuriAC::getPlayerManager()->get($player);
		if ($event->isCancelled()) {
			$playerZuri->setRecentlyCancelledEvent(microtime(true));
		}

		ZuriAC::getCheckRegistry()->spawnCheck([
			"type" => PMMPUtils::getNiceClassName($event),
			"player" => $player,
			"data" => [
				"blockPos" => Utils::vector3ToArray($event->getBlock()->getPosition()->asVector3()),
				"blockType" => $event->getBlock()->getTypeId(),
			]
		], Check::TYPE_PLAYER);
	}

	public function onPlayerPlace(BlockPlaceEvent $event) : void {
		$player = $event->getPlayer();

		$playerZuri = ZuriAC::getPlayerManager()->get($player);
		if ($event->isCancelled()) {
			$playerZuri->setRecentlyCancelledEvent(microtime(true));
		}

		foreach ($event->getTransaction()->getBlocks() as [$x, $y, $z, $block]) {
			ZuriAC::getCheckRegistry()->spawnCheck([
				"type" => PMMPUtils::getNiceClassName($event),
				"player" => $player,
				"data" => [
					"blockPos" => Utils::vector3ToArray(new Vector3($x, $y, $z)),
					"blockType" => $block->getTypeId(),
				]
			], Check::TYPE_PLAYER);
		}
	}

	public function onPlayerItemUse(PlayerItemUseEvent $event) : void {
		$player = $event->getPlayer();

		$playerZuri = ZuriAC::getPlayerManager()->get($player);
		if ($event->isCancelled()) {
			$playerZuri->setRecentlyCancelledEvent(microtime(true));
		}

		ZuriAC::getCheckRegistry()->spawnCheck([
			"type" => PMMPUtils::getNiceClassName($event),
			"player" => $player,
			"data" => [
				"itemType" => $event->getItem()->getTypeId()
			]
		], Check::TYPE_PLAYER);
	}
}
