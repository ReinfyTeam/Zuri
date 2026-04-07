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

namespace ReinfyTeam\Zuri\checks\modules\combat\killaura;

use pocketmine\block\BlockTypeIds;
use pocketmine\event\Event;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemOnEntityTransactionData;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\config\CheckConstants;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use ReinfyTeam\Zuri\utils\MathUtil;
use function count;
use function sqrt;

class KillAuraC extends Check {
	private bool $interact = false;

	public function getName() : string {
		return "KillAura";
	}

	public function getSubType() : string {
		return "C";
	}

	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if ($event instanceof PlayerInteractEvent) {
			$this->interact = true;
		}
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		if ($playerAPI->getAttackTicks() > 40 || $this->interact) {
			return;
		}

		$player = $playerAPI->getPlayer();

		if (
			$playerAPI->getProjectileAttackTicks() < 40 ||
			$playerAPI->getBowShotTicks() < 40 ||
			$playerAPI->isRecentlyCancelledEvent()
		) { // false-positive in projectiles
			return;
		}
		if ($packet instanceof InventoryTransactionPacket) {
			if ($packet->trData instanceof UseItemOnEntityTransactionData) {
				$delta = MathUtil::getDeltaDirectionVector($playerAPI, 3);
				$entities = [];
				foreach ($player->getWorld()->getEntities() as $target) {
					$entities[] = [
						"id" => $target->getId(),
						"x" => $target->getPosition()->getX(),
						"y" => $target->getPosition()->getY(),
						"z" => $target->getPosition()->getZ(),
					];
				}
				$this->dispatchAsyncCheck($player->getName(), [
					"type" => "KillAuraC",
					"playerId" => $player->getId(),
					"pitch" => $player->getLocation()->getPitch(),
					"locX" => $player->getLocation()->getX(),
					"locY" => $player->getLocation()->getY(),
					"locZ" => $player->getLocation()->getZ(),
					"eyeHeight" => $player->getEyeHeight(),
					"deltaX" => $delta->getX(),
					"deltaY" => $delta->getY(),
					"deltaZ" => $delta->getZ(),
					"maxDistance" => $this->getConstant(CheckConstants::KILLAURAC_MAX_DISTANCE),
					"suspiciousPitch" => $this->getConstant(CheckConstants::KILLAURAC_SUSPECIOUS_PITCH),
					"suspiciousCount" => $this->getConstant(CheckConstants::KILLAURAC_SUSPECIOUS_COUNT),
					"targetBlockAir" => $player->getTargetBlock(10)->getTypeId() === BlockTypeIds::AIR,
					"entities" => $entities,
				]);
			}
		}
	}

	public static function evaluateAsync(array $payload) : array {
		if (($payload["type"] ?? null) !== "KillAuraC") {
			return [];
		}

		if ((float) ($payload["pitch"] ?? 0) >= (float) ($payload["suspiciousPitch"] ?? 0)) {
			return [];
		}

		$from = new Vector3((float) ($payload["locX"] ?? 0), (float) ($payload["locY"] ?? 0) + (float) ($payload["eyeHeight"] ?? 0), (float) ($payload["locZ"] ?? 0));
		$delta = new Vector3((float) ($payload["deltaX"] ?? 0), (float) ($payload["deltaY"] ?? 0) + (float) ($payload["eyeHeight"] ?? 0), (float) ($payload["deltaZ"] ?? 0));
		$to = $from->add($delta->getX(), $delta->getY(), $delta->getZ());
		$distance = MathUtil::distance($from, $to);
		$vector = $to->subtract($from->x, $from->y, $from->z)->normalize()->multiply(1);
		$entities = [];
		for ($i = 0; $i <= $distance; ++$i) {
			$from = $from->add($vector->x, $vector->y, $vector->z);
			foreach (($payload["entities"] ?? []) as $target) {
				$distanceA = new Vector3($from->x, $from->y, $from->z);
				if (sqrt((($target["x"] - $distanceA->getX()) ** 2) + (($target["y"] - $distanceA->getY()) ** 2) + (($target["z"] - $distanceA->getZ()) ** 2)) <= (float) ($payload["maxDistance"] ?? 0)) {
					$entities[(int) $target["id"]] = true;
				}
			}
		}

		$debug = "distance={$distance}, entities=" . count($entities);
		if (count($entities) < (int) ($payload["suspiciousCount"] ?? 0) && !(bool) ($payload["targetBlockAir"] ?? true)) {
			return ["failed" => true, "debug" => $debug];
		}

		return ["debug" => $debug];
	}
}