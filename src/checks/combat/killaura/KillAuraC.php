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

namespace ReinfyTeam\Zuri\checks\combat\killaura;

use pocketmine\block\BlockTypeIds;
use pocketmine\event\Event;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\Packet;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemOnEntityTransactionData;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\MathUtil;
use function count;

class KillAuraC extends Check {
	private bool $interact = false;

	public function getName() : string {
		return "KillAura";
	}

	public function getSubType() : string {
		return "C";
	}

	public function maxViolations() : int {
		return 3;
	}

	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if ($event instanceof PlayerInteractEvent) {
			$this->interact = true;
		}
	}

	public function check(Packet $packet, PlayerAPI $playerAPI) : void {
		if ($playerAPI->getAttackTicks() > 40 || $this->interact) {
			return;
		}
		$player = $playerAPI->getPlayer();
		$locPlayer = $player->getLocation();
		$delta = MathUtil::getDeltaDirectionVector($playerAPI, 3);
		$from = new Vector3($locPlayer->getX(), $locPlayer->getY() + $player->getEyeHeight(), $locPlayer->getZ());
		$to = $player->getLocation()->add($delta->getX(), $delta->getY() + $player->getEyeHeight(), $delta->getZ());
		$distance = MathUtil::distance($from, $to);
		$vector = $to->subtract($from->x, $from->y, $from->z)->normalize()->multiply(1);
		$entities = [];
		for ($i = 0; $i <= $distance; ++$i) {
			$from = $from->add($vector->x, $vector->y, $vector->z);
			foreach ($player->getWorld()->getEntities() as $target) {
				$distanceA = new Vector3($from->x, $from->y, $from->z);
				if ($target->getPosition()->distance($distanceA) <= $this->getConstant("max-distance") && $target->getId() !== $player->getId()) {
					$entities[$target->getId()] = $target;
				}
			}
		}
		if ($packet instanceof InventoryTransactionPacket) {
			if ($packet->trData instanceof UseItemOnEntityTransactionData) {
				if ($locPlayer->getPitch() < $this->getConstant("suspecious-pitch")) {
					if (count($entities) < $this->getConstant("suspecious-count") && $player->getTargetBlock(10)->getTypeId() !== BlockTypeIds::AIR) {
						$this->failed($playerAPI);
					}
				}
				$this->debug($playerAPI, "delta=$delta, distance=$distance, entities=" . count($entities));
			}
		}
	}
}