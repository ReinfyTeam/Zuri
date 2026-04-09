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

namespace ReinfyTeam\Zuri\checks\modules\combat\reach;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Event;
use pocketmine\player\Player;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\checks\snapshots\CombatSnapshot;
use ReinfyTeam\Zuri\config\CheckConstants;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function abs;
use function is_array;
use function is_numeric;
use function sqrt;

class ReachA extends Check {
	public function getName() : string {
		return "Reach";
	}

	public function getSubType() : string {
		return "A";
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function checkJustEvent(Event $event) : void {
		if (!$event instanceof EntityDamageByEntityEvent || $event->isCancelled()) {
			return;
		}

		$entity = $event->getEntity();
		$damager = $event->getDamager();
		if (!$damager instanceof Player || !$entity instanceof Player) {
			return;
		}

		$damagerAPI = PlayerAPI::getAPIPlayer($damager);
		$playerAPI = PlayerAPI::getAPIPlayer($entity);
		if (
			!$damager->isSurvival() ||
			!$entity->isSurvival() ||
			$playerAPI->getProjectileAttackTicks() < 40 ||
			$damagerAPI->getProjectileAttackTicks() < 40 ||
			$playerAPI->getBowShotTicks() < 40 ||
			$damagerAPI->getBowShotTicks() < 40 ||
			$playerAPI->isRecentlyCancelledEvent() ||
			$damagerAPI->isRecentlyCancelledEvent()
		) {
			return;
		}

		$maxDistanceRaw = $this->getConstant(CheckConstants::REACHA_SURVIVAL_MAX_DISTANCE);
		$maxDistance = is_numeric($maxDistanceRaw) ? (float) $maxDistanceRaw : 0.0;
		$snapshot = new CombatSnapshot("ReachA", $damager, $damagerAPI, $entity, $playerAPI);
		$snapshot->addCachedData("maxDistance", $maxDistance);
		$snapshot->validate();
		$this->dispatchAsyncCheck($damager->getName(), $snapshot->build());
	}

	public static function evaluateAsync(array $payload) : array {
		if (!CombatSnapshot::validatePayload(
			$payload,
			"ReachA",
			CombatSnapshot::SCHEMA_VERSION,
			["type", "schemaVersion", "damagerEyeX", "damagerEyeY", "damagerEyeZ", "victimEyeX", "victimEyeY", "victimEyeZ", "cachedData"],
			[
				"damagerEyeX" => [-30000000.0, 30000000.0],
				"damagerEyeY" => [-2048.0, 2048.0],
				"damagerEyeZ" => [-30000000.0, 30000000.0],
				"victimEyeX" => [-30000000.0, 30000000.0],
				"victimEyeY" => [-2048.0, 2048.0],
				"victimEyeZ" => [-30000000.0, 30000000.0],
			]
		)) {
			return [];
		}

		$victimProjectileTicksRaw = $payload["victimProjectileTicks"] ?? 0;
		$damagerProjectileTicksRaw = $payload["damagerProjectileTicks"] ?? 0;
		$victimBowTicksRaw = $payload["victimBowTicks"] ?? 0;
		$damagerBowTicksRaw = $payload["damagerBowTicks"] ?? 0;
		$victimProjectileTicks = is_numeric($victimProjectileTicksRaw) ? (int) $victimProjectileTicksRaw : 0;
		$damagerProjectileTicks = is_numeric($damagerProjectileTicksRaw) ? (int) $damagerProjectileTicksRaw : 0;
		$victimBowTicks = is_numeric($victimBowTicksRaw) ? (int) $victimBowTicksRaw : 0;
		$damagerBowTicks = is_numeric($damagerBowTicksRaw) ? (int) $damagerBowTicksRaw : 0;

		if (
			!(bool) ($payload["damagerSurvival"] ?? false) ||
			!(bool) ($payload["victimSurvival"] ?? false) ||
			$victimProjectileTicks < 40 ||
			$damagerProjectileTicks < 40 ||
			$victimBowTicks < 40 ||
			$damagerBowTicks < 40 ||
			(bool) ($payload["victimRecentlyCancelled"] ?? false) ||
			(bool) ($payload["damagerRecentlyCancelled"] ?? false)
		) {
			return [];
		}

		$cachedDataRaw = $payload["cachedData"] ?? [];
		$cachedData = is_array($cachedDataRaw) ? $cachedDataRaw : [];
		$limitRaw = $cachedData["maxDistance"] ?? 0.0;
		$distance = sqrt(
			($payload["damagerEyeX"] - $payload["victimEyeX"]) ** 2 +
			($payload["damagerEyeY"] - $payload["victimEyeY"]) ** 2 +
			($payload["damagerEyeZ"] - $payload["victimEyeZ"]) ** 2
		);
		$isPlayerTop = $payload["victimEyeY"] > $payload["damagerEyeY"] ?
			abs($payload["victimEyeY"] - $payload["damagerEyeY"]) : 0;
		$distance -= $isPlayerTop;
		$limit = is_numeric($limitRaw) ? (float) $limitRaw : 0.0;
		$debug = "isPlayerTop={$isPlayerTop}, distance={$distance}, limit={$limit}";
		if ($distance > $limit) {
			return ["failed" => true, "debug" => $debug];
		}
		return ["debug" => $debug];
	}
}