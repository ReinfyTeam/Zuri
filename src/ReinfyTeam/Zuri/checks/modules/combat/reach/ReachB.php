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

/**
 * Detects horizontal reach distance beyond allowed combat limits.
 */
class ReachB extends Check {
	/**
	 * Returns the check name.
	 *
	 * @return string Check identifier.
	 */
	public function getName() : string {
		return "Reach";
	}

	/**
	 * Returns the check subtype.
	 *
	 * @return string Check subtype identifier.
	 */
	public function getSubType() : string {
		return "B";
	}

	/**
	 * Processes entity damage events for reach evaluation.
	 *
	 * @param Event $event Triggered event.
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

		$entityAPI = PlayerAPI::getAPIPlayer($entity);
		$damagerAPI = PlayerAPI::getAPIPlayer($damager);
		if (
			!$damager->isSurvival() ||
			!$entity->isSurvival() ||
			$entityAPI->getProjectileAttackTicks() < 40 ||
			$damagerAPI->getProjectileAttackTicks() < 40 ||
			$entityAPI->getBowShotTicks() < 40 ||
			$damagerAPI->getBowShotTicks() < 40 ||
			$entityAPI->isRecentlyCancelledEvent() ||
			$damagerAPI->isRecentlyCancelledEvent()
		) {
			return;
		}

		$maxDistanceRaw = $this->getConstant(CheckConstants::REACHB_SURVIVAL_MAX_DISTANCE);
		$maxDistance = is_numeric($maxDistanceRaw) ? (float) $maxDistanceRaw : 0.0;
		$snapshot = new CombatSnapshot("ReachB", $damager, $damagerAPI, $entity, $entityAPI);
		$snapshot->addCachedData("maxDistance", $maxDistance);
		$snapshot->validate();
		$this->dispatchAsyncCheck($damager->getName(), $snapshot->build());
	}

	/**
	 * Evaluates an async payload for Reach B violations.
	 *
	 * @param array<string,mixed> $payload Serialized check payload.
	 * @return array<string,mixed> Async decision data.
	 */
	public static function evaluateAsync(array $payload) : array {
    // Thread-safe: execute in async worker thread only; use only $payload (no Player objects)
    if (\pocketmine\thread\Thread::getCurrentThreadId() === 0) {
        throw new \RuntimeException("evaluateAsync must not be called on the main thread");
    }
		if (!CombatSnapshot::validatePayload(
			$payload,
			"ReachB",
			CombatSnapshot::SCHEMA_VERSION,
			["type", "schemaVersion", "damagerEyeX", "damagerEyeZ", "victimEyeX", "victimEyeZ", "cachedData"],
			[
				"damagerEyeX" => [-30000000.0, 30000000.0],
				"damagerEyeZ" => [-30000000.0, 30000000.0],
				"victimEyeX" => [-30000000.0, 30000000.0],
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
		$distance = ($payload["damagerEyeX"] - $payload["victimEyeX"]) ** 2 +
			($payload["damagerEyeZ"] - $payload["victimEyeZ"]) ** 2;
		$limit = is_numeric($limitRaw) ? (float) $limitRaw : 0.0;
		$debug = "distance={$distance}, limit={$limit}";
		if ($distance > $limit) {
			return ["failed" => true, "debug" => $debug];
		}
		return ["debug" => $debug];
	}
}

