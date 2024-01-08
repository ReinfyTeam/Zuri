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
use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\Packet;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemOnEntityTransactionData;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use Zuri\Zuri;
use function abs;
use function fmod;
use function pow;
use function round;
use function sqrt;

class Killaura extends Zuri implements Listener {
	public function __construct() {
		parent::__construct(Zuri::KILLAURA);
	}

	private array $entities = [];

	// Commonly in Toolbox
	public function killauraV1(Packet $packet, Player $player) : void {
		if ($player->getGamemode()->equals(GameMode::CREATIVE())) {
			return;
		}
		if ($player->getGamemode()->equals(GameMode::SPECTATOR())) {
			return;
		}
		if ($this->canBypass($player)) {
			return;
		}
		if ($this->isLagging($player)) {
			return;
		}
		if (!$packet instanceof DataPacket) {
			return;
		}
		$swing = null;
		if ($packet instanceof AnimatePacket) {
			if ($packet->action === AnimatePacket::ACTION_SWING_ARM) {
				$swing = true;
			} else {
				$swing = false; // player detected killaura. :>
			}
		}

		if ($packet instanceof InventoryTransactionPacket && $packet->trData->getTypeId() === InventoryTransactionPacket::TYPE_USE_ITEM_ON_ENTITY && $packet->trData->getTypeId() === UseItemOnEntityTransactionData::ACTION_ATTACK) {
			if (!$swing && $swing !== null) {
				$this->fail($player);
			} else {
				$this->reward($player);
			}
		}
	}

	public function processEvent(DataPacketReceiveEvent $event) : void {
		if ($event->getOrigin()->getPlayer() !== null) {
			$this->killauraV1($event->getPacket(), $event->getOrigin()->getPlayer());
		}
	}

	// check player yaw if their head is actually hitting the player, but might possible to bypass if player has aimbot
	public function killauraV2(DataPacketReceiveEvent $event) : void {
		$packet = $event->getPacket();
		$player = $event->getOrigin()->getPlayer();
		if ($player !== null && $player->isOnline()) {
			if ($this->canBypass($player)) {
				return;
			}
			if ($this->isLagging($player)) {
				return;
			}
			if ($packet instanceof ServerboundPacket) {
				if ($packet instanceof PlayerAuthInputPacket) {
					$expectedHeadYaw = fmod(($packet->getYaw() > 0 ? 0 : 360) + $packet->getYaw(), 360);
					$diff = fmod(abs($expectedHeadYaw - $packet->getHeadYaw()), 360);
					$roundedDiff = round($diff, 4);
					if ($diff > 5E-5 && $roundedDiff !== 360.0 && $packet->getHeadYaw() > 0) {
						$this->fail($player);
					} elseif ($packet->getHeadYaw() < 0) {
						$expectedHeadYaw = fmod($packet->getHeadYaw(), 180);
						$diff = fmod(abs($expectedHeadYaw - $packet->getHeadYaw()), 360);
						$roundedDiff = round($diff, 4);
						if ($diff > 5E-5 && $roundedDiff !== 360.0) {
							$this->fail($player);
						} else {
							$this->reward();
						}
					}
				}
			}
		}
	}

	public function KillauraV3(EntityDamageByEntityEvent $event) : void {
		$entity = $event->getEntity();
		$damager = $event->getDamager();
		$locDamager = $damager->getLocation();
		if ($damager instanceof Player) {
			if ($this->canBypass($damager)) {
				return;
			}
			$delta = Killaura::getDeltaDirectionVector($damager, 3);
			$from = new Vector3($locDamager->getX(), $locDamager->getY() + $damager->getEyeHeight(), $locDamager->getZ());
			$to = $damager->getLocation()->add($delta->getX(), $delta->getY() + $damager->getEyeHeight(), $delta->getZ());
			$distance = Killaura::distance($from, $to);
			$vector = $to->subtract($from->x, $from->y, $from->z)->normalize()->multiply(1);
			$entities = [];
			for ($i = 0; $i <= $distance; $i += 1) {
				$from = $from->add($vector->x, $vector->y, $vector->z);
				foreach ($damager->getWorld()->getEntities() as $target) {
					$distanceA = new Vector3($from->x, $from->y, $from->z);
					if ($target->getPosition()->distance($distanceA) <= 2.6) {
						$entities[$target->getId()] = $target;
					}
				}
			}
			if (!isset($entities[$entity->getId()])) {
				$this->fail($damager);
			}
		}
	}

	public static function getDeltaDirectionVector(Player $player, float $distance) : Vector3 {
		return $player->getDirectionVector()->multiply($distance);
	}

	public static function distance(Vector3 $from, Vector3 $to) {
		return sqrt(pow($from->getX() - $to->getX(), 2) + pow($from->getY() - $to->getY(), 2) + pow($from->getZ() - $to->getZ(), 2));
	}
}
