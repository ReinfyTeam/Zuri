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

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Event;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\config\CheckConstants;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use ReinfyTeam\Zuri\utils\MathUtil;
use function count;
use function is_array;
use function is_numeric;
use function sqrt;

/**
 * Detects suspicious attack traces that intersect multiple nearby entities.
 */
class KillAuraE extends Check {
	/**
	 * Returns the check name.
	 *
	 * @return string Check identifier.
	 */
	public function getName() : string {
		return "KillAura";
	}

	/**
	 * Returns the check subtype.
	 *
	 * @return string Check subtype identifier.
	 */
	public function getSubType() : string {
		return "E";
	}

	/**
	 * Processes entity damage events for KillAura E evaluation.
	 *
	 * @param Event $event Triggered event.
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
					$damagerAPI->isRecentlyCancelledEvent() ||
					$victimAPI->isRecentlyCancelledEvent()
				) { // false-positive in projectiles
					return;
				}
				$delta = MathUtil::getDeltaDirectionVector($damagerAPI, 3);
				$maxRangeRaw = $this->getConstant(CheckConstants::KILLAURAE_MAX_RANGE);
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
					"checkName" => $this->getName(),
					"checkSubType" => $this->getSubType(),
					"victimId" => $entity->getId(),
					"locX" => $damager->getLocation()->getX(),
					"locY" => $damager->getLocation()->getY(),
					"locZ" => $damager->getLocation()->getZ(),
					"eyeHeight" => $damager->getEyeHeight(),
					"deltaX" => $delta->getX(),
					"deltaY" => $delta->getY(),
					"deltaZ" => $delta->getZ(),
					"maxRange" => is_numeric($maxRangeRaw) ? (float) $maxRangeRaw : 0.0,
					"entities" => $entities,
				]);
			}
		}
	}

	/**
	 * Evaluates an async payload for KillAura E violations.
	 *
	 * @param array<string,mixed> $payload Serialized check payload.
	 * @return array<string,mixed> Async decision data.
	 */
	public static function evaluateAsync(array $payload) : array {
		$check = new self();
		if (($payload["checkName"] ?? null) !== $check->getName() || ($payload["checkSubType"] ?? null) !== $check->getSubType()) {
			return [];
		}

		$locXRaw = $payload["locX"] ?? 0;
		$locYRaw = $payload["locY"] ?? 0;
		$locZRaw = $payload["locZ"] ?? 0;
		$eyeHeightRaw = $payload["eyeHeight"] ?? 0;
		$deltaXRaw = $payload["deltaX"] ?? 0;
		$deltaYRaw = $payload["deltaY"] ?? 0;
		$deltaZRaw = $payload["deltaZ"] ?? 0;
		$locX = is_numeric($locXRaw) ? (float) $locXRaw : 0.0;
		$locY = is_numeric($locYRaw) ? (float) $locYRaw : 0.0;
		$locZ = is_numeric($locZRaw) ? (float) $locZRaw : 0.0;
		$eyeHeight = is_numeric($eyeHeightRaw) ? (float) $eyeHeightRaw : 0.0;
		$deltaX = is_numeric($deltaXRaw) ? (float) $deltaXRaw : 0.0;
		$deltaY = is_numeric($deltaYRaw) ? (float) $deltaYRaw : 0.0;
		$deltaZ = is_numeric($deltaZRaw) ? (float) $deltaZRaw : 0.0;
		$from = new Vector3($locX, $locY + $eyeHeight, $locZ);
		$delta = new Vector3($deltaX, $deltaY + $eyeHeight, $deltaZ);
		$to = $from->add($delta->getX(), $delta->getY(), $delta->getZ());
		$distance = MathUtil::distance($from, $to);
		$vector = $to->subtract($from->x, $from->y, $from->z)->normalize()->multiply(1);
		$maxRangeRaw = $payload["maxRange"] ?? 0;
		$maxRange = is_numeric($maxRangeRaw) ? (float) $maxRangeRaw : 0.0;
		$entitiesPayload = $payload["entities"] ?? [];
		$entitiesList = is_array($entitiesPayload) ? $entitiesPayload : [];
		$entities = [];
		for ($i = 0; $i <= $distance; ++$i) {
			$from = $from->add($vector->x, $vector->y, $vector->z);
			foreach ($entitiesList as $target) {
				if (!is_array($target)) {
					continue;
				}
				$targetXRaw = $target["x"] ?? null;
				$targetYRaw = $target["y"] ?? null;
				$targetZRaw = $target["z"] ?? null;
				$targetIdRaw = $target["id"] ?? null;
				if (!is_numeric($targetXRaw) || !is_numeric($targetYRaw) || !is_numeric($targetZRaw) || !is_numeric($targetIdRaw)) {
					continue;
				}
				$targetX = (float) $targetXRaw;
				$targetY = (float) $targetYRaw;
				$targetZ = (float) $targetZRaw;
				$targetId = (int) $targetIdRaw;
				$distanceA = new Vector3($from->x, $from->y, $from->z);
				if (sqrt((($targetX - $distanceA->getX()) ** 2) + (($targetY - $distanceA->getY()) ** 2) + (($targetZ - $distanceA->getZ()) ** 2)) <= $maxRange) {
					$entities[$targetId] = true;
				}
			}
		}

		$debug = "distance={$distance}, entities=" . count($entities);
		$victimIdRaw = $payload["victimId"] ?? -1;
		$victimId = is_numeric($victimIdRaw) ? (int) $victimIdRaw : -1;
		if (!isset($entities[$victimId])) {
			return ["failed" => true, "debug" => $debug];
		}

		return ["debug" => $debug];
	}
}
