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

namespace ReinfyTeam\Zuri\listener;

use pocketmine\block\Block;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\Cancellable;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityMotionEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\event\Event;
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
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\CommandEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\inventory\ArmorInventory;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\ActorEventPacket;
use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\PlayerActionPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\ServerboundPacket;
use pocketmine\network\mcpe\protocol\TextPacket;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemOnEntityTransactionData;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;
use pocketmine\network\mcpe\protocol\UpdateAdventureSettingsPacket;
use pocketmine\player\Player;
use ReinfyTeam\Zuri\lang\Lang;
use ReinfyTeam\Zuri\lang\LangKeys;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\BlockUtil;
use ReinfyTeam\Zuri\utils\ExceptionHandler;
use ReinfyTeam\Zuri\utils\HotPathProfiler;
use ReinfyTeam\Zuri\ZuriAC;
use function abs;
use function array_filter;
use function count;
use function microtime;

/**
 * Primary event and packet listener that routes player data into check pipelines.
 */
class PlayerListener implements Listener {
	/** @var array<string, Block> */
	private array $blockInteracted = [];
	/** @var array<string,list<float>> */
	private array $clicksData = [];
	/** @var array<string,array{windowStart:float,count:int,blockedUntil:float}> */
	private array $packetRateState = [];

	private const DELTAL_TIME_CLICK = 1;

	/**
	 * Handles inbound packets, updates CPS counters, and dispatches packet checks.
	 *
	 * @param DataPacketReceiveEvent $event Packet receive event.
	 */
	public function onDataPacketReceive(DataPacketReceiveEvent $event) : void {
		ExceptionHandler::wrapVoid(function() use ($event) : void {
			$profileStartedAt = microtime(true);
			$packet = $event->getPacket();
			$player = $event->getOrigin()->getPlayer();

			if ($player === null || !$this->isPlayerReady($player)) {
				return;
			}
			$playerAPI = PlayerAPI::getAPIPlayer($player);
			if ($this->isFloodingPackets($player)) {
				return;
			}

			$this->markRecentlyCancelled($event, $playerAPI);

			if ($packet instanceof DataPacket) {
				$this->check($packet, $playerAPI);
			}

			if ($packet instanceof LevelSoundEventPacket) {
				if ($packet->sound === LevelSoundEvent::ATTACK_NODAMAGE) {
					$this->addCPS($playerAPI);
					$playerAPI->setCPS($this->getCPS($playerAPI));
				}
			}
			if ($packet instanceof InventoryTransactionPacket) {
				if ($packet->trData instanceof UseItemOnEntityTransactionData) {
					$this->addCPS($playerAPI);
					$playerAPI->setCPS($this->getCPS($playerAPI));
				}
			}
			HotPathProfiler::record("packet.handler.receive", microtime(true) - $profileStartedAt);
		}, "PlayerListener::onDataPacketReceive");
	}

	/**
	 * Tracks movement context and dispatches movement-related checks.
	 *
	 * @param PlayerMoveEvent $event Player move event.
	 */
	public function onPlayerMove(PlayerMoveEvent $event) : void {
		ExceptionHandler::wrapVoid(function() use ($event) : void {
			$player = $event->getPlayer();
			$playerAPI = PlayerAPI::getAPIPlayer($player);
			if (!$this->isPlayerReady($player)) {
				return;
			}
			$moved =
				abs($event->getTo()->getX() - $event->getFrom()->getX()) >= 0.0001 ||
				abs($event->getTo()->getY() - $event->getFrom()->getY()) >= 0.0001 ||
				abs($event->getTo()->getZ() - $event->getFrom()->getZ()) >= 0.0001;
			if (!$moved) {
				return;
			}

			$this->markRecentlyCancelled($event, $playerAPI);

			$this->checkEvent($event, $playerAPI);
			if ($playerAPI->isFlagged()) {
				$event->cancel();
				$playerAPI->setFlagged(false);
			}
			$playerAPI->setNLocation($event->getFrom(), $event->getTo());
			$playerAPI->setOnGround(BlockUtil::isOnGround($event->getTo(), 0) || BlockUtil::isOnGround($event->getTo(), 1));
			if ($playerAPI->isOnGround()) {
				$playerAPI->setLastGroundY($player->getPosition()->getY());
			} else {
				$playerAPI->setLastNoGroundY($player->getPosition()->getY());
			}
			if (BlockUtil::onSlimeBlock($event->getTo(), 0) || BlockUtil::onSlimeBlock($event->getTo(), 1)) {
				$playerAPI->setSlimeBlockTicks(microtime(true));
			}
			$playerAPI->setOnIce(BlockUtil::isOnIce($event->getTo(), 1) || BlockUtil::isOnIce($event->getTo(), 2));
			$playerAPI->setOnStairs(BlockUtil::isOnStairs($event->getTo(), 0) || BlockUtil::isOnStairs($event->getTo(), 1));
			$playerAPI->setUnderBlock(BlockUtil::isOnGround($player->getLocation(), -2));
			$playerAPI->setTopBlock(BlockUtil::isOnGround($player->getLocation(), 1));
			$playerAPI->setInLiquid(BlockUtil::isOnLiquid($event->getTo(), 0) || BlockUtil::isOnLiquid($event->getTo(), 1));
			$playerAPI->setOnAdhesion(BlockUtil::isOnAdhesion($event->getTo(), 0));
			$playerAPI->setOnPlant(BlockUtil::isOnPlant($event->getTo(), 0));
			$playerAPI->setOnDoor(BlockUtil::isOnDoor($event->getTo(), 0));
			$playerAPI->setOnCarpet(BlockUtil::isOnCarpet($event->getTo(), 0));
			$playerAPI->setOnPlate(BlockUtil::isOnPlate($event->getTo(), 0));
			$playerAPI->setOnSnow(BlockUtil::isOnSnow($event->getTo(), 0));
			$playerAPI->setLastMoveTick(microtime(true));
			$playerAPI->setMotion(Vector3::zero());
		}, "PlayerListener::onPlayerMove");
	}

	/**
	 * Tracks interacted blocks and dispatches interaction checks.
	 *
	 * @param PlayerInteractEvent $event Player interact event.
	 */
	public function onPlayerInteract(PlayerInteractEvent $event) : void {
		ExceptionHandler::wrapVoid(function() use ($event) : void {
			$player = $event->getPlayer();
			$playerAPI = PlayerAPI::getAPIPlayer($player);
			if (!$this->isPlayerReady($player)) {
				return;
			}
			$this->markRecentlyCancelled($event, $playerAPI);
			$block = $event->getBlock();
			$key = $this->getPlayerKey($player);
			if (!isset($this->blockInteracted[$key])) {
				$this->blockInteracted[$key] = $block;
			} else {
				unset($this->blockInteracted[$key]);
			}

			if ($playerAPI->isFlagged()) {
				$event->cancel();
				$playerAPI->setFlagged(false);
			}
			$this->checkEvent($event, $playerAPI);
		}, "PlayerListener::onPlayerInteract");
	}

	/**
	 * Updates aggregated motion state from entity motion events.
	 *
	 * @param EntityMotionEvent $event Entity motion event.
	 */
	public function onMotion(EntityMotionEvent $event) : void {
		ExceptionHandler::wrapVoid(function() use ($event) : void {
			$entity = $event->getEntity();
			if (!$entity instanceof Player) {
				return;
			}
			$playerAPI = PlayerAPI::getAPIPlayer($entity);
			if (!$this->isPlayerReady($entity)) {
				return;
			}
			$this->markRecentlyCancelled($event, $playerAPI);
			$playerAPI->getMotion()->x += abs($event->getVector()->getX());
			$playerAPI->getMotion()->z += abs($event->getVector()->getZ());
		}, "PlayerListener::onMotion");
	}

	/**
	 * Handles block break context and special-break heuristics.
	 *
	 * @param BlockBreakEvent $event Block break event.
	 */
	public function onPlayerBreak(BlockBreakEvent $event) : void {
		ExceptionHandler::wrapVoid(function() use ($event) : void {
			$block = $event->getBlock();
			$x = $block->getPosition()->getX();
			$z = $block->getPosition()->getZ();
			$player = $event->getPlayer();
			$playerAPI = PlayerAPI::getAPIPlayer($player);
			if (!$this->isPlayerReady($player)) {
				return;
			}
			$this->markRecentlyCancelled($event, $playerAPI);
			$this->checkEvent($event, $playerAPI);
			if ($playerAPI->isFlagged()) {
				$event->cancel();
				$playerAPI->setFlagged(false);
			}
			if (isset($this->blockInteracted[($player->getXuid() === "" ? $player->getUniqueId()->__toString() : $player->getXuid())])) {
				$key = $this->getPlayerKey($player);
				if (!isset($this->blockInteracted[$key])) {
					return;
				}
				$blockInteracted = $this->blockInteracted[$key];
				$xI = $blockInteracted->getPosition()->getX();
				$zI = $blockInteracted->getPosition()->getZ();
				if ((int) $x != (int) $xI && (int) $z != (int) $zI) {
					$playerAPI->setActionBreakingSpecial(true);
					$playerAPI->setBlocksBrokeASec($playerAPI->getBlocksBrokeASec() + 1);
				} else {
					$playerAPI->setBlocksBrokeASec(0);
					unset($this->blockInteracted[($player->getXuid() === "" ? $player->getUniqueId()->__toString() : $player->getXuid())]);
					unset($this->blockInteracted[$key]);
				}
			}
		}, "PlayerListener::onPlayerBreak");
	}

	/**
	 * Handles block place context and special-place heuristics.
	 *
	 * @param BlockPlaceEvent $event Block place event.
	 */
	public function onPlayerPlace(BlockPlaceEvent $event) : void {
		ExceptionHandler::wrapVoid(function() use ($event) : void {
			$block = $event->getBlockAgainst();
			$x = $block->getPosition()->getX();
			$z = $block->getPosition()->getZ();
			$player = $event->getPlayer();
			$playerAPI = PlayerAPI::getAPIPlayer($player);
			if (!$this->isPlayerReady($player)) {
				return;
			}
			$this->markRecentlyCancelled($event, $playerAPI);
			$playerAPI->setPlacingTicks(microtime(true));
			$this->checkEvent($event, $playerAPI);
			if ($playerAPI->isFlagged()) {
				$event->cancel();
				$playerAPI->setFlagged(false);
			}
			if (isset($this->blockInteracted[$player->getXuid()])) {
				$key = $this->getPlayerKey($player);
				if (!isset($this->blockInteracted[$key])) {
					return;
				}
				$blockInteracted = $this->blockInteracted[$key];
				$xI = $blockInteracted->getPosition()->getX();
				$zI = $blockInteracted->getPosition()->getZ();
				if ((int) $x != (int) $xI && (int) $z != (int) $zI) {
					$playerAPI->setActionPlacingSpecial(true);
					$playerAPI->setBlocksPlacedASec($playerAPI->getBlocksPlacedASec() + 1);
				} else {
					$playerAPI->setBlocksPlacedASec(0);
					unset($this->blockInteracted[$player->getXuid()]);
					unset($this->blockInteracted[$key]);
				}
			}
		}, "PlayerListener::onPlayerPlace");
	}

	/**
	 * Processes item-use events and records cancellation state.
	 *
	 * @param PlayerItemUseEvent $event Item use event.
	 */
	public function onPlayerItemUse(PlayerItemUseEvent $event) : void {
		ExceptionHandler::wrapVoid(function() use ($event) : void {
			$player = $event->getPlayer();
			$playerAPI = PlayerAPI::getAPIPlayer($player);
			if (!$player->isConnected() || !$player->isOnline()) {
				return;
			}
			$this->markRecentlyCancelled($event, $playerAPI);
			//TODO
		}, "PlayerListener::onPlayerItemUse");
	}


	/**
	 * Processes inventory transactions and armor transaction flags.
	 *
	 * @param InventoryTransactionEvent $event Inventory transaction event.
	 */
	public function onInventoryTransaction(InventoryTransactionEvent $event) : void {
		ExceptionHandler::wrapVoid(function() use ($event) : void {
			$player = $event->getTransaction()->getSource();
			$playerAPI = PlayerAPI::getAPIPlayer($player);
			if (!$player->isConnected() || !$player->isOnline()) {
				return;
			}
			$this->markRecentlyCancelled($event, $playerAPI);
			$this->checkEvent($event, $playerAPI);
			foreach ($event->getTransaction()->getInventories() as $inventory) {
				if ($inventory instanceof ArmorInventory) {
					$playerAPI->setTransactionArmorInventory(true);
				}
			}
		}, "PlayerListener::onInventoryTransaction");
	}

	/**
	 * Marks inventory open state and dispatches related checks.
	 *
	 * @param InventoryOpenEvent $event Inventory open event.
	 */
	public function onInventoryOpen(InventoryOpenEvent $event) : void {
		ExceptionHandler::wrapVoid(function() use ($event) : void {
			$player = $event->getPlayer();
			$playerAPI = PlayerAPI::getAPIPlayer($player);
			$this->markRecentlyCancelled($event, $playerAPI);
			$playerAPI->setInventoryOpen(true);
			$this->checkEvent($event, $playerAPI);
		}, "PlayerListener::onInventoryOpen");
	}

	/**
	 * Marks inventory closed state and dispatches related checks.
	 *
	 * @param InventoryCloseEvent $event Inventory close event.
	 */
	public function onInventoryClose(InventoryCloseEvent $event) : void {
		ExceptionHandler::wrapVoid(function() use ($event) : void {
			$player = $event->getPlayer();
			$playerAPI = PlayerAPI::getAPIPlayer($player);
			$this->markRecentlyCancelled($event, $playerAPI);
			$playerAPI->setInventoryOpen(false);
			$this->checkEvent($event, $playerAPI);
		}, "PlayerListener::onInventoryClose");
	}

	/**
	 * Tracks teleport ticks and world-transfer cooldown markers.
	 *
	 * @param EntityTeleportEvent $event Entity teleport event.
	 */
	public function onEntityTeleport(EntityTeleportEvent $event) : void {
		ExceptionHandler::wrapVoid(function() use ($event) : void {
			$entity = $event->getEntity();
			if (!$entity instanceof Player) {
				return;
			}

			$playerAPI = PlayerAPI::getAPIPlayer($entity);

			if (!$playerAPI->getPlayer()->isConnected() || !$playerAPI->getPlayer()->isOnline()) {
				return;
			}

			if ($event->isCancelled()) {
				$playerAPI->setRecentlyCancelledEvent(microtime(true));
			}

			// Track world transfers for FP cooldown
			$from = $event->getFrom();
			$to = $event->getTo();
			if ($from->getWorld()->getFolderName() !== $to->getWorld()->getFolderName()) {
				$playerAPI->setLastWorldTransfer(microtime(true));
			}

			$playerAPI->setTeleportTicks(microtime(true));
		}, "PlayerListener::onEntityTeleport");
	}

	/**
	 * Records jump timing for movement checks.
	 *
	 * @param PlayerJumpEvent $event Player jump event.
	 */
	public function onPlayerJump(PlayerJumpEvent $event) : void {
		ExceptionHandler::wrapVoid(function() use ($event) : void {
			$player = $event->getPlayer();
			$playerAPI = PlayerAPI::getAPIPlayer($player);
			if (!$player->isConnected() || !$player->isOnline()) {
				return;
			}
			$playerAPI->setJumpTicks(microtime(true));
		}, "PlayerListener::onPlayerJump");
	}

	/**
	 * Clears per-player listener state on disconnect.
	 *
	 * @param PlayerQuitEvent $event Player quit event.
	 */
	public function onPlayerQuit(PlayerQuitEvent $event) : void {
		ExceptionHandler::wrapVoid(function() use ($event) : void {
			unset($this->packetRateState[$this->getPlayerKey($event->getPlayer())]);
			PlayerAPI::removeAPIPlayer($event->getPlayer());
		}, "PlayerListener::onPlayerQuit");
	}

	/**
	 * Initializes per-player timing state on join.
	 *
	 * @param PlayerJoinEvent $event Player join event.
	 */
	public function onPlayerJoin(PlayerJoinEvent $event) : void {
		ExceptionHandler::wrapVoid(function() use ($event) : void {
			$player = $event->getPlayer();
			$playerAPI = PlayerAPI::getAPIPlayer($player);
			if (!$player->isConnected() || !$player->isOnline()) {
				return;
			}
			$this->checkEvent($event, $playerAPI);
			$playerAPI->setJoinedAtTheTime(microtime(true));
		}, "PlayerListener::onPlayerJoin");
	}

	/**
	 * Dispatches pre-login checks that do not require PlayerAPI context.
	 *
	 * @param PlayerPreLoginEvent $event Pre-login event.
	 */
	public function onPlayerPreLogin(PlayerPreLoginEvent $event) : void {
		ExceptionHandler::wrapVoid(function() use ($event) : void {
			$this->checkJustEvent($event);
		}, "PlayerListener::onPlayerPreLogin");
	}

	/**
	 * Handles generic damage events and updates hurt timers.
	 *
	 * @param EntityDamageEvent $event Damage event.
	 */
	public function onEntityDamage(EntityDamageEvent $event) : void {
		ExceptionHandler::wrapVoid(function() use ($event) : void {
			$this->checkJustEvent($event);

			if (($player = $event->getEntity()) instanceof Player) {
				$playerAPI = PlayerAPI::getAPIPlayer($player);
				$this->markRecentlyCancelled($event, $playerAPI);
				if (
					$event->getCause() === EntityDamageEvent::CAUSE_ENTITY_ATTACK ||
					$event->getCause() === EntityDamageEvent::CAUSE_PROJECTILE ||
					$event->getCause() === EntityDamageEvent::CAUSE_SUFFOCATION ||
					$event->getCause() === EntityDamageEvent::CAUSE_VOID ||
					$event->getCause() === EntityDamageEvent::CAUSE_FALLING_BLOCK
				) {
					return;
				}
				$playerAPI->setHurtTicks(microtime(true));
			}
		}, "PlayerListener::onEntityDamage");
	}

	/**
	 * Handles combat damage events and updates attack timings.
	 *
	 * @param EntityDamageByEntityEvent $event Damage-by-entity event.
	 */
	public function onEntityDamageByEntity(EntityDamageByEntityEvent $event) : void {
		ExceptionHandler::wrapVoid(function() use ($event) : void {
			$cause = $event->getCause();
			$entity = $event->getEntity();
			$damager = $event->getDamager();
			if (!$damager instanceof Player) {
				return;
			}
			$playerAPI = PlayerAPI::getAPIPlayer($damager);
			if (!$playerAPI->getPlayer()->isConnected() || !$playerAPI->getPlayer()->isOnline()) {
				return;
			}
			$this->markRecentlyCancelled($event, $playerAPI);
			$this->checkJustEvent($event);
			if ($cause === EntityDamageEvent::CAUSE_ENTITY_ATTACK) {
				//$event->setAttackCooldown(1);
				if ($entity instanceof Player) {
					PlayerAPI::getAPIPlayer($entity)->setAttackTicks(microtime(true));
				}
				if ($playerAPI->isFlagged()) {
					$event->cancel();
					$playerAPI->setFlagged(false);
				}
				$playerAPI->setAttackTicks(microtime(true));
			}
			if ($cause === EntityDamageEvent::CAUSE_ENTITY_EXPLOSION || $cause === EntityDamageEvent::CAUSE_BLOCK_EXPLOSION) {
				if ($entity instanceof Player) {
					PlayerAPI::getAPIPlayer($entity)->setAttackTicks(microtime(true));
				}
			}
		}, "PlayerListener::onEntityDamageByEntity");
	}

	/**
	 * Tracks projectile-hit timing for shooter-related checks.
	 *
	 * @param ProjectileHitEvent $event Projectile hit event.
	 */
	public function onProjectileHit(ProjectileHitEvent $event) : void {
		ExceptionHandler::wrapVoid(function() use ($event) : void {
			$projectile = $event->getEntity();
			$player = $projectile->getOwningEntity();

			if ($player !== null && $player instanceof Player) { // this will fix ender pearl tp hack for now...
				$playerAPI = PlayerAPI::getAPIPlayer($player);
				$this->markRecentlyCancelled($event, $playerAPI);

				$playerAPI->setProjectileAttackTicks(microtime(true));
			}
		}, "PlayerListener::onProjectileHit");
	}


	/**
	 * Records death timing markers used by cooldown logic.
	 *
	 * @param PlayerDeathEvent $event Player death event.
	 */
	public function onPlayerDeath(PlayerDeathEvent $event) : void {
		ExceptionHandler::wrapVoid(function() use ($event) : void {
			$player = $event->getPlayer();
			$playerAPI = PlayerAPI::getAPIPlayer($player);
			if (!$player->isConnected() || !$player->isOnline()) {
				return;
			}
			$this->markRecentlyCancelled($event, $playerAPI);
			$playerAPI->setDeathTicks(microtime(true));
		}, "PlayerListener::onPlayerDeath");
	}

	/**
	 * Dispatches chat checks when captcha flow is inactive.
	 *
	 * @param PlayerChatEvent $event Player chat event.
	 */
	public function onPlayerChat(PlayerChatEvent $event) : void {
		ExceptionHandler::wrapVoid(function() use ($event) : void {
			$player = $event->getPlayer();
			$playerAPI = PlayerAPI::getAPIPlayer($player);
			if (!$player->isConnected() || !$player->isOnline()) {
				return;
			}
			if ($playerAPI->isCaptcha()) {
				return;
			}
			$this->markRecentlyCancelled($event, $playerAPI);
			$this->checkEvent($event, $playerAPI);
		}, "PlayerListener::onPlayerChat");
	}

	/**
	 * Dispatches held-item change checks.
	 *
	 * @param PlayerItemHeldEvent $event Item-held event.
	 */
	public function onPlayerItemHeld(PlayerItemHeldEvent $event) : void {
		ExceptionHandler::wrapVoid(function() use ($event) : void {
			$player = $event->getPlayer();
			$playerAPI = PlayerAPI::getAPIPlayer($player);
			if (!$player->isConnected() || !$player->isOnline()) {
				return;
			}
			$this->markRecentlyCancelled($event, $playerAPI);
			$this->checkEvent($event, $playerAPI);
		}, "PlayerListener::onPlayerItemHeld");
	}

	/**
	 * Dispatches health-regain related checks.
	 *
	 * @param EntityRegainHealthEvent $event Health regain event.
	 */
	public function onPlayerRegen(EntityRegainHealthEvent $event) : void {
		ExceptionHandler::wrapVoid(function() use ($event) : void {
			$player = $event->getEntity();
			if (!$player instanceof Player) {
				return;
			}
			$playerAPI = PlayerAPI::getAPIPlayer($player);
			if (!$player->isConnected() || !$player->isOnline()) {
				return;
			}
			$this->markRecentlyCancelled($event, $playerAPI);
			$this->checkEvent($event, $playerAPI);
		}, "PlayerListener::onPlayerRegen");
	}

	/**
	 * Dispatches command-based behavioral checks for players.
	 *
	 * @param CommandEvent $event Command event.
	 */
	public function onCommandEvent(CommandEvent $event) : void {
		ExceptionHandler::wrapVoid(function() use ($event) : void {
			$sender = $event->getSender();
			if (!$sender instanceof Player) {
				return;
			}
			$playerAPI = PlayerAPI::getAPIPlayer($sender);
			if (!$playerAPI->getPlayer()->isConnected() || !$playerAPI->getPlayer()->isOnline()) {
				return;
			}
			$this->markRecentlyCancelled($event, $playerAPI);
			$this->checkEvent($event, $playerAPI);
		}, "PlayerListener::onCommandEvent");
	}

	/**
	 * Tracks bow-shot timing and dispatches bow-related checks.
	 *
	 * @param EntityShootBowEvent $event Shoot bow event.
	 */
	public function onEntityShootBowEvent(EntityShootBowEvent $event) : void {
		ExceptionHandler::wrapVoid(function() use ($event) : void {
			$player = $event->getEntity();
			if (!$player instanceof Player) {
				return;
			}
			$playerAPI = PlayerAPI::getAPIPlayer($player);
			if (!$player->isConnected() || !$player->isOnline()) {
				return;
			}
			$this->markRecentlyCancelled($event, $playerAPI);
			$playerAPI->setBowShotTicks(microtime(true));
			$this->checkEvent($event, $playerAPI);
		}, "PlayerListener::onEntityShootBowEvent");
	}

	/**
	 * Dispatches item-consume related checks.
	 *
	 * @param PlayerItemConsumeEvent $event Item consume event.
	 */
	public function onPlayerItemConsume(PlayerItemConsumeEvent $event) : void {
		ExceptionHandler::wrapVoid(function() use ($event) : void {
			$player = $event->getPlayer();
			$playerAPI = PlayerAPI::getAPIPlayer($player);
			if (!$player->isConnected() || !$player->isOnline()) {
				return;
			}
			$this->markRecentlyCancelled($event, $playerAPI);
			$this->checkEvent($event, $playerAPI);
		}, "PlayerListener::onPlayerItemConsume");
	}

	/**
	 * Dispatches item-drop related checks.
	 *
	 * @param PlayerDropItemEvent $event Drop item event.
	 */
	public function onDropItem(PlayerDropItemEvent $event) : void {
		ExceptionHandler::wrapVoid(function() use ($event) : void {
			$player = $event->getPlayer();
			$playerAPI = PlayerAPI::getAPIPlayer($player);

			if (!$player->isConnected() || !$player->isOnline()) {
				return;
			}
			$this->markRecentlyCancelled($event, $playerAPI);
			$this->checkEvent($event, $playerAPI);
		}, "PlayerListener::onDropItem");
	}

	/**
	 * Appends one click timestamp for CPS estimation.
	 *
	 * @param PlayerAPI $player Player context.
	 */
	private function addCPS(PlayerAPI $player) : void {
		$time = microtime(true);
		$this->clicksData[$player->getPlayer()->getName()][] = $time;
	}

	/**
	 * Returns clicks-per-second from recent click timestamps.
	 *
	 * @param PlayerAPI $player Player context.
	 */
	private function getCPS(PlayerAPI $player) : int {
		$newTime = microtime(true);
		return count(array_filter($this->clicksData[$player->getPlayer()->getName()] ?? [], static function(float $lastTime) use ($newTime) : bool {
			return ($newTime - $lastTime) <= self::DELTAL_TIME_CLICK;
		}));
	}

	/**
	 * Dispatches event-aware checks for a player.
	 *
	 * @param Event $event Event instance.
	 * @param PlayerAPI $player Player context.
	 */
	private function checkEvent(Event $event, PlayerAPI $player) : void {
		$p = $player->getPlayer();
		if (!$p->isOnline() || !$p->isConnected()) {
			return;
		}

		foreach (ZuriAC::EventChecks() as $class) {
			ExceptionHandler::wrapVoid(
				function() use ($class, $event, $player) : void {
					$startedAt = microtime(true);
					$class->checkEvent($event, $player);
					HotPathProfiler::record("check.event." . $class->getName() . "." . $class->getSubType(), microtime(true) - $startedAt);
				},
				"checkEvent:" . $class->getName() . ":" . $class->getSubType()
			);
		}
	}

	/**
	 * Dispatches packet checks for a player.
	 *
	 * @param ServerboundPacket $packet Packet instance.
	 * @param PlayerAPI $player Player context.
	 */
	private function check(ServerboundPacket $packet, PlayerAPI $player) : void {
		$p = $player->getPlayer();
		if (!$p->isOnline() || !$p->isConnected()) {
			return;
		}
		if (!$this->isPacketRelevantForIdlePlayer($packet) && !$this->isPlayerActive($player)) {
			return;
		}

		foreach (ZuriAC::PacketChecks() as $class) {
			if (!$packet instanceof DataPacket) {
				continue;
			}
			ExceptionHandler::wrapVoid(
				function() use ($class, $packet, $player) : void {
					$startedAt = microtime(true);
					$class->check($packet, $player);
					HotPathProfiler::record("check.packet." . $class->getName() . "." . $class->getSubType(), microtime(true) - $startedAt);
				},
				"check:" . $class->getName() . ":" . $class->getSubType()
			);
		}
	}

	/**
	 * Dispatches checks that only consume global event context.
	 *
	 * @param Event $event Event instance.
	 */
	private function checkJustEvent(Event $event) : void {
		foreach (ZuriAC::JustEventChecks() as $class) {
			ExceptionHandler::wrapVoid(
				function() use ($class, $event) : void {
					$startedAt = microtime(true);
					$class->checkJustEvent($event);
					HotPathProfiler::record("check.justevent." . $class->getName() . "." . $class->getSubType(), microtime(true) - $startedAt);
				},
				"checkJustEvent:" . $class->getName() . ":" . $class->getSubType()
			);
		}
	}

	/**
	 * Dispatches projectile launch checks.
	 *
	 * @param ProjectileLaunchEvent $event Projectile launch event.
	 */
	public function onProjectileLaunch(ProjectileLaunchEvent $event) : void {
		ExceptionHandler::wrapVoid(function() use ($event) : void {
			$projectile = $event->getEntity();
			$player = $projectile->getOwningEntity();
			if ($player instanceof Player) {
				$this->markRecentlyCancelled($event, PlayerAPI::getAPIPlayer($player));
			}
			$this->checkJustEvent($event);
		}, "PlayerListener::onProjectileLaunch");
	}

	/**
	 * Validates that a player is online and fully connected.
	 *
	 * @param Player $player Player entity.
	 */
	private function isPlayerReady(Player $player) : bool {
		return $player->isConnected() && $player->isOnline();
	}

	/**
	 * Determines whether a player should be treated as recently active.
	 *
	 * @param PlayerAPI $playerAPI Player context.
	 */
	private function isPlayerActive(PlayerAPI $playerAPI) : bool {
		return $playerAPI->getLastMoveTick() <= 40
			|| $playerAPI->getAttackTicks() <= 40
			|| $playerAPI->getProjectileAttackTicks() <= 40
			|| $playerAPI->getPlacingTicks() <= 40
			|| $playerAPI->isInventoryOpen()
			|| $playerAPI->getBlocksPlacedASec() > 0
			|| $playerAPI->getBlocksBrokeASec() > 0;
	}

	/**
	 * Identifies packets still relevant for idle players.
	 *
	 * @param ServerboundPacket $packet Packet instance.
	 */
	private function isPacketRelevantForIdlePlayer(ServerboundPacket $packet) : bool {
		return $packet instanceof PlayerAuthInputPacket
			|| $packet instanceof MovePlayerPacket
			|| $packet instanceof InventoryTransactionPacket
			|| $packet instanceof PlayerActionPacket
			|| $packet instanceof LevelSoundEventPacket
			|| $packet instanceof AnimatePacket
			|| $packet instanceof ActorEventPacket
			|| $packet instanceof TextPacket
			|| $packet instanceof LoginPacket
			|| $packet instanceof UpdateAdventureSettingsPacket;
	}

	/**
	 * Builds a stable key used for per-player state arrays.
	 *
	 * @param Player $player Player entity.
	 */
	private function getPlayerKey(Player $player) : string {
		return $player->getXuid() === "" ? $player->getUniqueId()->__toString() : $player->getXuid();
	}

	/**
	 * Marks a player when a cancellable event was blocked.
	 *
	 * @param Event $event Event instance.
	 * @param PlayerAPI $playerAPI Player context.
	 */
	private function markRecentlyCancelled(Event $event, PlayerAPI $playerAPI) : void {
		if ($event instanceof Cancellable && $event->isCancelled()) {
			$playerAPI->setRecentlyCancelledEvent(microtime(true));
		}
	}

	/**
	 * Applies packet-rate flood guard and temporary block windows.
	 *
	 * @param Player $player Player entity.
	 * @return bool True when packet processing should be skipped.
	 */
	private function isFloodingPackets(Player $player) : bool {
		$key = $this->getPlayerKey($player);
		$now = microtime(true);
		$state = $this->packetRateState[$key] ?? ["windowStart" => $now, "count" => 0, "blockedUntil" => 0.0];

		if ($state["blockedUntil"] > $now) {
			return true;
		}

		if (($now - $state["windowStart"]) >= 1.0) {
			$state["windowStart"] = $now;
			$state["count"] = 0;
		}

		$state["count"]++;
		$maxPacketsPerSecond = 1200;
		if ($state["count"] > $maxPacketsPerSecond) {
			$state["blockedUntil"] = $now + 1.5;
			$this->packetRateState[$key] = $state;
			ExceptionHandler::wrapVoid(
				static function() use ($key, $maxPacketsPerSecond) : void {
					ZuriAC::getInstance()->getLogger()->warning(Lang::get(
						LangKeys::DEBUG_PACKET_FLOOD_GUARD,
						["key" => $key, "limit" => (string) $maxPacketsPerSecond]
					));
				},
				"PlayerListener::packetFloodGuard"
			);
			return true;
		}

		$this->packetRateState[$key] = $state;
		return false;
	}
}
