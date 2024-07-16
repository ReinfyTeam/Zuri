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

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\event\entity\EntityTeleportEvent;
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
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\Packet;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemOnEntityTransactionData;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;
use pocketmine\player\Player;
use pocketmine\Server;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\BlockUtil;
use ReinfyTeam\Zuri\ZuriAC;
use function array_filter;
use function count;
use function in_array;
use function microtime;

class PlayerListener implements Listener {
	private array $blockInteracted = [];
	private array $clicksData = [];

	const DELTAL_TIME_CLICK = 1;

	public function onDataPacketReceive(DataPacketReceiveEvent $event) : void {
		$packet = $event->getPacket();
		$player = $event->getOrigin()->getPlayer();
		if ($player === null) {
			return;
		}
		if (!$player->spawned && !$player->isConnected()) {
			return;
		}
		$playerAPI = PlayerAPI::getAPIPlayer($player);
		$this->check($packet, $playerAPI);

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
	}

	public function onPlayerMove(PlayerMoveEvent $event) : void {
		$player = $event->getPlayer();
		$playerAPI = PlayerAPI::getAPIPlayer($player);
		if (!$player->spawned && !$player->isConnected()) {
			return;
		}
		if ($playerAPI->getPlayer() === null) {
			return;
		}
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
		$playerAPI->setLastMoveTick((double) Server::getInstance()->getTick());
	}

	public function onPlayerInteract(PlayerInteractEvent $event) : void {
		$player = $event->getPlayer();
		if (!$player->spawned && !$player->isConnected()) {
			return;
		}
		$playerAPI = PlayerAPI::getAPIPlayer($player);
		if ($playerAPI->getPlayer() === null) {
			return;
		}
		$block = $event->getBlock();
		if (!isset($this->blockInteracted[($player->getXuid() === "" ? $player->getUniqueId()->__toString() : $player->getXuid())])) {
			$this->blockInteracted[($player->getXuid() === "" ? $player->getUniqueId()->__toString() : $player->getXuid())] = $block;
		} else {
			unset($this->blockInteracted[($player->getXuid() === "" ? $player->getUniqueId()->__toString() : $player->getXuid())]);
		}

		if ($playerAPI->isFlagged()) {
			$event->cancel();
			$playerAPI->setFlagged(false);
		}
		$this->checkEvent($event, $playerAPI);
	}

	public function onPlayerBreak(BlockBreakEvent $event) : void {
		$block = $event->getBlock();
		$x = $block->getPosition()->getX();
		$z = $block->getPosition()->getZ();
		$player = $event->getPlayer();
		$playerAPI = PlayerAPI::getAPIPlayer($player);
		if ($playerAPI->getPlayer() === null) {
			return;
		}
		if (!$player->spawned && !$player->isConnected()) {
			return;
		}
		$this->checkEvent($event, $playerAPI);
		if ($playerAPI->isFlagged()) {
			$event->cancel();
			$playerAPI->setFlagged(false);
		}
		if (isset($this->blockInteracted[($player->getXuid() === "" ? $player->getUniqueId()->__toString() : $player->getXuid())])) {
			$blockInteracted = $this->blockInteracted[($player->getXuid() === "" ? $player->getUniqueId()->__toString() : $player->getXuid())];
			$xI = $blockInteracted->getPosition()->getX();
			$zI = $blockInteracted->getPosition()->getZ();
			if ((int) $x !== (int) $xI && (int) $z !== (int) $zI) {
				$playerAPI->setActionBreakingSpecial(true);
				$playerAPI->setBlocksBrokeASec($playerAPI->getBlocksBrokeASec() + 1);
			} else {
				$playerAPI->setBlocksBrokeASec(0);
				unset($this->blockInteracted[($player->getXuid() === "" ? $player->getUniqueId()->__toString() : $player->getXuid())]);
			}
		}
	}

	public function onPlayerPlace(BlockPlaceEvent $event) : void {
		$block = $event->getBlockAgainst();
		$x = $block->getPosition()->getX();
		$z = $block->getPosition()->getZ();
		$player = $event->getPlayer();
		$playerAPI = PlayerAPI::getAPIPlayer($player);
		if ($playerAPI->getPlayer() === null) {
			return;
		}
		if (!$player->spawned && !$player->isConnected()) {
			return;
		}
		$playerAPI->setPlacingTicks(microtime(true));
		$this->checkEvent($event, $playerAPI);
		if ($playerAPI->isFlagged()) {
			$event->cancel();
			$playerAPI->setFlagged(false);
		}
		if (isset($this->blockInteracted[$player->getXuid()])) {
			$blockInteracted = $this->blockInteracted[$player->getXuid()];
			$xI = $blockInteracted->getPosition()->getX();
			$zI = $blockInteracted->getPosition()->getZ();
			if ((int) $x !== (int) $xI && (int) $z !== (int) $zI) {
				$playerAPI->setActionPlacingSpecial(true);
				$playerAPI->setBlocksPlacedASec($playerAPI->getBlocksPlacedASec() + 1);
			} else {
				$playerAPI->setBlocksPlacedASec(0);
				unset($this->blockInteracted[$player->getXuid()]);
			}
		}
	}

	public function onPlayerItemUse(PlayerItemUseEvent $event) : void {
		$player = $event->getPlayer();
		//TODO
	}


	public function onInventoryTransaction(InventoryTransactionEvent $event) : void {
		$player = $event->getTransaction()->getSource();
		$playerAPI = PlayerAPI::getAPIPlayer($player);
		if ($playerAPI->getPlayer() === null) {
			return;
		}
		if (!$player->spawned && !$player->isConnected()) {
			return;
		}
		$this->checkEvent($event, $playerAPI);
		foreach ($event->getTransaction()->getInventories() as $inventory) {
			if ($inventory instanceof ArmorInventory) {
				$playerAPI->setTransactionArmorInventory(true);
			}
		}
	}

	public function onInventoryOpen(InventoryOpenEvent $event) : void {
		$player = $event->getPlayer();
		$playerAPI = PlayerAPI::getAPIPlayer($player);
		if ($playerAPI->getPlayer() === null) {
			return;
		}
		$playerAPI->setInventoryOpen(true);
		$this->checkEvent($event, $playerAPI);
	}

	public function onInventoryClose(InventoryCloseEvent $event) : void {
		$player = $event->getPlayer();
		$playerAPI = PlayerAPI::getAPIPlayer($player);
		if ($playerAPI->getPlayer() === null) {
			return;
		}
		$playerAPI->setInventoryOpen(false);
		$this->checkEvent($event, $playerAPI);
	}

	public function onEntityTeleport(EntityTeleportEvent $event) : void {
		$entity = $event->getEntity();
		if ($entity === null) {
			return;
		}
		if (!$entity instanceof Player) {
			return;
		}
		$playerAPI = PlayerAPI::getAPIPlayer($entity);
		if ($playerAPI->getPlayer() === null) {
			return;
		}
		if (!$playerAPI->getPlayer()->isConnected() && !$playerAPI->getPlayer()->spawned) {
			return;
		}
		$playerAPI->setTeleportTicks(microtime(true));
	}

	public function onPlayerJump(PlayerJumpEvent $event) : void {
		$player = $event->getPlayer();
		$playerAPI = PlayerAPI::getAPIPlayer($player);
		if ($playerAPI->getPlayer() === null) {
			return;
		}
		if (!$player->spawned && !$player->isConnected()) {
			return;
		}
		$playerAPI->setJumpTicks(microtime(true));
	}

	public function onPlayerQuit(PlayerQuitEvent $event) : void {
		PlayerAPI::removeAPIPlayer($event->getPlayer());
	}

	public function onPlayerJoin(PlayerJoinEvent $event) : void {
		$player = $event->getPlayer();
		$playerAPI = PlayerAPI::getAPIPlayer($player);
		if (!$player->spawned && !$player->isConnected()) {
			return;
		}
		if ($playerAPI->getPlayer() === null) {
			return;
		}
		$this->checkEvent($event, $playerAPI);
		$playerAPI->setJoinedAtTheTime(microtime(true));
	}

	public function onPlayerPreLogin(PlayerPreLoginEvent $event) : void {
		$this->checkJustEvent($event);
	}

	public function onEntityDamage(EntityDamageEvent $event) : void {
		$this->checkJustEvent($event);
	}

	public function onEntityDamageByEntity(EntityDamageByEntityEvent $event) : void {
		$cause = $event->getCause();
		$entity = $event->getEntity();
		if ($entity === null) {
			return;
		}
		$damager = $event->getDamager();
		if ($damager === null) {
			return;
		}
		if (!$damager instanceof Player) {
			return;
		}
		$playerAPI = PlayerAPI::getAPIPlayer($damager);
		if ($playerAPI->getPlayer() === null) {
			return;
		}
		if (!$playerAPI->getPlayer()->isConnected() && !$playerAPI->getPlayer()->spawned) {
			return;
		}
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
		if (in_array($cause, [EntityDamageEvent::CAUSE_ENTITY_EXPLOSION, EntityDamageEvent::CAUSE_BLOCK_EXPLOSION], true)) {
			PlayerAPI::getAPIPlayer($entity)->setAttackTicks(microtime(true));
		}
	}

	public function onPlayerDeath(PlayerDeathEvent $event) : void {
		$player = $event->getPlayer();
		$playerAPI = PlayerAPI::getAPIPlayer($player);
		if ($playerAPI->getPlayer() === null) {
			return;
		}
		if (!$player->spawned && !$player->isConnected()) {
			return;
		}
		$playerAPI->setDeathTicks(microtime(true));
	}

	public function onPlayerChat(PlayerChatEvent $event) : void {
		$player = $event->getPlayer();
		$playerAPI = PlayerAPI::getAPIPlayer($player);
		if ($playerAPI->getPlayer() === null) {
			return;
		}
		if (!$player->spawned && !$player->isConnected()) {
			return;
		}
		if ($playerAPI->isCaptcha()) {
			return;
		}
		$this->checkEvent($event, $playerAPI);
	}

	public function onPlayerItemHeld(PlayerItemHeldEvent $event) : void {
		$player = $event->getPlayer();
		$playerAPI = PlayerAPI::getAPIPlayer($player);
		if ($playerAPI->getPlayer() === null) {
			return;
		}
		if (!$player->spawned && !$player->isConnected()) {
			return;
		}
		$this->checkEvent($event, $playerAPI);
	}

	public function onPlayerRegen(EntityRegainHealthEvent $event) : void {
		$player = $event->getEntity();
		if (!$player instanceof Player) {
			return;
		}
		$playerAPI = PlayerAPI::getAPIPlayer($player);
		if ($playerAPI->getPlayer() === null) {
			return;
		}
		if (!$player->spawned && !$player->isConnected()) {
			return;
		}
		$this->checkEvent($event, $playerAPI);
	}

	public function onCommandEvent(CommandEvent $event) : void {
		$sender = $event->getSender();
		if (!$sender instanceof Player) {
			return;
		}
		$playerAPI = PlayerAPI::getAPIPlayer($sender);
		if ($playerAPI->getPlayer() === null) {
			return;
		}
		if (!$playerAPI->getPlayer()->isConnected() && !$playerAPI->getPlayer()->spawned) {
			return;
		}
		$this->checkEvent($event, $playerAPI);
	}

	public function onEntityShootBowEvent(EntityShootBowEvent $event) : void {
		$player = $event->getEntity();
		if (!$player instanceof Player) {
			return;
		}
		$playerAPI = PlayerAPI::getAPIPlayer($player);
		if ($playerAPI->getPlayer() === null) {
			return;
		}
		if (!$player->spawned && !$player->isConnected()) {
			return;
		}
		$this->checkEvent($event, $playerAPI);
	}

	public function onPlayerItemConsume(PlayerItemConsumeEvent $event) : void {
		$player = $event->getPlayer();
		$playerAPI = PlayerAPI::getAPIPlayer($player);
		if ($playerAPI->getPlayer() === null) {
			return;
		}
		if (!$player->spawned && !$player->isConnected()) {
			return;
		}
		$this->checkEvent($event, $playerAPI);
	}

	public function onDropItem(PlayerDropItemEvent $event) : void {
		$player = $event->getPlayer();
		$playerAPI = PlayerAPI::getAPIPlayer($player);

		if ($playerAPI->getPlayer() === null) {
			return;
		}
		if (!$player->spawned && !$player->isConnected()) {
			return;
		}
		$this->checkEvent($event, $playerAPI);
	}

	private function addCPS(PlayerAPI $player) : void {
		if ($player->getPlayer() === null) {
			return;
		}
		$this->clicksData[$player->getPlayer()->getName()][] = microtime(true);
	}

	private function getCPS(PlayerAPI $player) : int {
		$newTime = microtime(true);
		return count(array_filter($this->clicksData[$player->getPlayer()->getName()] ?? [], static function(float $lastTime) use ($newTime) : bool {
			return ($newTime - $lastTime) <= self::DELTAL_TIME_CLICK;
		}));
	}

	private function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		$player = $playerAPI->getPlayer();
		if (!$player->spawned || !$player->isOnline()) {
			return;
		}
		foreach (ZuriAC::getChecks() as $class) {
			if ($class->enable()) {
				$class->checkEvent($event, $playerAPI);
			}
		}
	}

	private function check(Packet $packet, PlayerAPI $playerAPI) : void {
		$player = $playerAPI->getPlayer();
		if (!$player->spawned || !$player->isOnline()) {
			return;
		}

		foreach (ZuriAC::getChecks() as $class) {
			if ($class->enable()) {
				$class->check($packet, $playerAPI);
			}
		}
	}

	private function checkJustEvent(Event $event) : void {
		foreach (ZuriAC::getChecks() as $class) {
			if ($class->enable()) {
				$class->checkJustEvent($event);
			}
		}
	}

	public function onProjectileLaunch(ProjectileLaunchEvent $event) : void {
		$this->checkJustEvent($event);
	}
}
