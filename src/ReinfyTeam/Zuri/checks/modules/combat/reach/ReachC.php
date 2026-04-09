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
use function is_array;
use function is_numeric;
use function sqrt;

class ReachC extends Check {
	public function getName() : string {
		return "Reach";
	}

	public function getSubType() : string {
		return "C";
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function checkJustEvent(Event $event) : void {
		if (!$event instanceof EntityDamageByEntityEvent || $event->isCancelled()) {
			return;
		}

		if (!($victim = $event->getEntity()) instanceof Player || !($damager = $event->getDamager()) instanceof Player) {
			return;
		}

		$victimAPI = PlayerAPI::getAPIPlayer($victim);
		$damagerAPI = PlayerAPI::getAPIPlayer($damager);
		if (
			!$damager->isSurvival() ||
			!$victim->isSurvival() ||
			$victimAPI->getProjectileAttackTicks() < 40 ||
			$damagerAPI->getProjectileAttackTicks() < 40 ||
			$victimAPI->getBowShotTicks() < 40 ||
			$damagerAPI->getBowShotTicks() < 40 ||
			$victimAPI->isRecentlyCancelledEvent() ||
			$damagerAPI->isRecentlyCancelledEvent()
		) {
			return;
		}

		$maxReachEyeDistanceRaw = $this->getConstant(CheckConstants::REACHC_MAX_REACH_EYE_DISTANCE);
		$maxReachEyeDistance = is_numeric($maxReachEyeDistanceRaw) ? (float) $maxReachEyeDistanceRaw : 0.0;
		$snapshot = new CombatSnapshot("ReachC", $damager, $damagerAPI, $victim, $victimAPI);
		$snapshot->addCachedData("maxReachEyeDistance", $maxReachEyeDistance);
		$snapshot->validate();
		$this->dispatchAsyncCheck($damager->getName(), $snapshot->build());
	}

	public static function evaluateAsync(array $payload) : array {
		if (!CombatSnapshot::validatePayload(
			$payload,
			"ReachC",
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
		$damagerEyeXRaw = $payload["damagerEyeX"] ?? 0.0;
		$damagerEyeYRaw = $payload["damagerEyeY"] ?? 0.0;
		$damagerEyeZRaw = $payload["damagerEyeZ"] ?? 0.0;
		$victimEyeXRaw = $payload["victimEyeX"] ?? 0.0;
		$victimEyeYRaw = $payload["victimEyeY"] ?? 0.0;
		$victimEyeZRaw = $payload["victimEyeZ"] ?? 0.0;
		$damagerEye = [
			is_numeric($damagerEyeXRaw) ? (float) $damagerEyeXRaw : 0.0,
			is_numeric($damagerEyeYRaw) ? (float) $damagerEyeYRaw : 0.0,
			is_numeric($damagerEyeZRaw) ? (float) $damagerEyeZRaw : 0.0,
		];
		$victimEye = [
			is_numeric($victimEyeXRaw) ? (float) $victimEyeXRaw : 0.0,
			is_numeric($victimEyeYRaw) ? (float) $victimEyeYRaw : 0.0,
			is_numeric($victimEyeZRaw) ? (float) $victimEyeZRaw : 0.0,
		];

		// Simplified distance calculation
		$distance = sqrt(
			($damagerEye[0] - $victimEye[0]) ** 2 +
			($damagerEye[1] - $victimEye[1]) ** 2 +
			($damagerEye[2] - $victimEye[2]) ** 2
		);

		$limitRaw = $cachedData["maxReachEyeDistance"] ?? 3.0;
		$limit = is_numeric($limitRaw) ? (float) $limitRaw : 3.0;
		$debug = "distance={$distance}, limit={$limit}";
		if ($distance > $limit) {
			return ["failed" => true, "debug" => $debug];
		}

		return ["debug" => $debug];
	}
}