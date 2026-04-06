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

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Event;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use ReinfyTeam\Zuri\utils\MathUtil;
use function count;

class KillAuraE extends Check {
	public function getName() : string {
		return "KillAura";
	}

	public function getSubType() : string {
		return "E";
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function checkJustEvent(Event $event) : void {
		if ($event instanceof EntityDamageByEntityEvent) {
			$entity = $event->getEntity();
			$damager = $event->getDamager();
			if ($damager instanceof Player && $entity instanceof Player) {
				$damagerAPI = PlayerAPI::getAPIPlayer($damager);
				$victimAPI = PlayerAPI::getAPIPlayer($entity);

				if (
					$victimAPI->getProjectileAttackTicks() < 40 ||
					$damagerAPI->getProjectileAttackTicks() < 40 ||
					$victimAPI->getBowShotTicks() < 40 ||
					$damagerAPI->getBowShotTicks() < 40 ||
					$damagerAPI->recentlyCancelledEvent() < 40 ||
					$victimAPI->recentlyCancelledEvent() < 40
				) { // false-positive in projectiles
					return;
				}
				$delta = MathUtil::getDeltaDirectionVector($damagerAPI, 3);
				$entities = [];
				foreach ($damager->getWorld()->getEntities() as $target) {
					$entities[] = [
						"id" => $target->getId(),
						"x" => $target->getPosition()->getX(),
						"y" => $target->getPosition()->getY(),
						"z" => $target->getPosition()->getZ(),
					];
				}
				$this->dispatchAsyncCheck($damager->getName(), [
					"type" => "KillAuraE",
					"victimId" => $entity->getId(),
					"locX" => $damager->getLocation()->getX(),
					"locY" => $damager->getLocation()->getY(),
					"locZ" => $damager->getLocation()->getZ(),
					"eyeHeight" => $damager->getEyeHeight(),
					"deltaX" => $delta->getX(),
					"deltaY" => $delta->getY(),
					"deltaZ" => $delta->getZ(),
					"maxRange" => $this->getConstant("max-range"),
					"entities" => $entities,
				]);
			}
		}
	}

	public static function evaluateAsync(array $payload) : array {
		if (($payload["type"] ?? null) !== "KillAuraE") {
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
				if (sqrt((($target["x"] - $distanceA->getX()) ** 2) + (($target["y"] - $distanceA->getY()) ** 2) + (($target["z"] - $distanceA->getZ()) ** 2)) <= (float) ($payload["maxRange"] ?? 0)) {
					$entities[(int) $target["id"]] = true;
				}
			}
		}

		$debug = "distance={$distance}, entities=" . count($entities);
		if (!isset($entities[(int) ($payload["victimId"] ?? -1)])) {
			return ["failed" => true, "debug" => $debug];
		}

		return ["debug" => $debug];
	}
}
