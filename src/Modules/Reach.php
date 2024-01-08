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
 * @author ReinfyTeam
 * @link https://github.com/ReinfyTeam/
 *
 *
 */

declare(strict_types=1);

namespace Zuri\Modules;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use Zuri\Anticheat;
use Zuri\Zuri;

class Reach extends Zuri implements Listener {
	public const MAX_PLAYER_REACH = 8.1;
	public const MAX_PLAYER_REACH_V2 = 4.0;

	private const MAX_REACH_DISTANCE_CREATIVE_V3 = 13;
	private const MAX_REACH_DISTANCE_SURVIVAL_V3 = 7;
	private const MAX_REACH_DISTANCE_ENTITY_INTERACTION_V3 = 8;

	public function __construct() {
		parent::__construct(Zuri::REACH);
	}

	public function reachV1(EntityDamageByEntityEvent $event) : void {
		if (($player = $event->getEntity()) instanceof Player && ($damager = $event->getDamager()) instanceof Player) {
			if ($this->canBypass($damager)) {
				return;
			}
			if ($this->isLagging($damager)) {
				return;
			}
			if ($damager->getGamemode()->equals(GameMode::CREATIVE())) {
				return;
			}
			if ($damager->getGamemode()->equals(GameMode::SPECTATOR())) {
				return;
			}
			if ($player->getLocation()->distance($damager->getLocation()) > Anticheat::getInstance()->getConfig()->getNested("max-player-reach", Reach::MAX_PLAYER_REACH)) {
				$this->fail($damager);
			} else {
				$this->reward($damager, 0.01);
			}
		}
	}

	// V2 - just check again...
	public function reachV2(EntityDamageEvent $event) {
		if ($event instanceof EntityDamageByEntityEvent && $event->getEntity() instanceof Player && $event->getDamager() instanceof Player) {
			if ($this->canBypass($event->getDamager())) {
				return;
			}
			if ($this->isLagging($event->getDamager())) {
				return;
			}
			if ($event->getDamager()->getGamemode()->equals(GameMode::CREATIVE())) {
				return;
			}
			if ($event->getDamager()->getGamemode()->equals(GameMode::SPECTATOR())) {
				return;
			}
			if ($event->getEntity()->getLocation()->distanceSquared($event->getDamager()->getLocation()) > Anticheat::getInstance()->getConfig()->getNested("max-player-reach", Reach::MAX_PLAYER_REACH_V2)) {
				$this->fail($event->getDamager());
			} else {
				$this->reward($event->getDamager(), 0.01);
			}
		}
	}

	// V3 - just checking again
	public function reachV3(EntityDamageEvent $event) {
		if ($event instanceof EntityDamageByEntityEvent && $event->getEntity() instanceof Player && $event->getDamager() instanceof Player) {
			if ($this->canBypass($event->getDamager())) {
				return;
			}
			if ($this->isLagging($event->getDamager())) {
				return;
			}
			if (!$event->getDamager()->canInteract($event->getEntity()->getLocation()->add(0.5, 0.5, 0.5), $event->getEntity()->isCreative() ? Anticheat::getInstance()->getConfig()->getNested("max-reach-distance-creative", Reach::MAX_REACH_DISTANCE_CREATIVE_V3) : Anticheat::getInstance()->getConfig()->getNested("max-reach-distance-survival", Reach::MAX_REACH_DISTANCE_SURVIVAL_V3))) {
				$this->fail($event->getDamager());
			} else {
				$this->reward($event->getDamager(), 0.01);
			}
			if (!$event->getDamager()->canInteract($event->getEntity()->getLocation(), Anticheat::getInstance()->getConfig()->getNested("max-reach-distance-survival", Reach::MAX_REACH_DISTANCE_ENTITY_INTERACTION_V3))) {
				$this->fail($event->getDamager());
				$event->cancel();
			} else {
				$this->reward($event->getDamager(), 0.01);
			}
		}
	}

	public function reachBlockV1(PlayerInteractEvent $event) : void {
		if ($this->isLagging($event->getPlayer())) {
			return;
		}
		if (!$event->getPlayer()->canInteract($event->getBlock()->getPosition()->add(0.5, 0.5, 0.5), $event->getPlayer()->isCreative() ? Anticheat::getInstance()->getConfig()->getNested("max-reach-distance-creative", Reach::MAX_REACH_DISTANCE_SURVIVAL_V3) : Anticheat::getInstance()->getConfig()->getNested("max-reach-distance-survival", Reach::MAX_REACH_DISTANCE_SURVIVAL_V3))) {
			$this->fail($event->getPlayer());
		} else {
			$this->reward($event->getPlayer(), 0.01);
		}
	}
}
