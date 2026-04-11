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
use ReinfyTeam\Zuri\utils\MathUtil;
use function is_array;
use function is_numeric;

/**
 * Detects reach anomalies after ping and sprint compensation adjustments.
 */
class ReachD extends Check {
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
		return "D";
	}

	/**
	 * Processes entity damage events for reach evaluation.
	 *
	 * @param Event $event Triggered event.
	 */
	public function checkJustEvent(Event $event) : void {
		if ($event instanceof EntityDamageByEntityEvent) {
			$damager = $event->getDamager();
			$victim = $event->getEntity();

			if ($victim instanceof Player && $damager instanceof Player) {
				$damagerAPI = PlayerAPI::getAPIPlayer($damager);
				$victimAPI = PlayerAPI::getAPIPlayer($victim);

				if ($this->shouldSkip($damager, $victim, $damagerAPI, $victimAPI)) { // false-positive in projectiles
					return;
				}

				$defaultEyeDistanceRaw = $this->getConstant(CheckConstants::REACHD_DEFAULT_EYE_DISTANCE);
				$victimSprintingDistanceRaw = $this->getConstant(CheckConstants::REACHD_SPRINTING_EYE_DISTANCE);
				$victimNotSprintingDistanceRaw = $this->getConstant(CheckConstants::REACHD_NOT_SPRINTING_EYE_DISTANCE);
				$damagerSprintingDistanceRaw = $this->getConstant(CheckConstants::REACHD_DAMAGER_SPRINTING_EYE_DISTANCE);
				$damagerNotSprintingDistanceRaw = $this->getConstant(CheckConstants::REACHD_NOT_SPRINTING_DAMAGER_EYE_DISTANCE);
				$limitRaw = $this->getConstant(CheckConstants::REACHD_REACH_EYE_LIMIT);

				$snapshot = new CombatSnapshot("ReachD", $damager, $damagerAPI, $victim, $victimAPI);
				$snapshot->addCachedData("defaultEyeDistance", is_numeric($defaultEyeDistanceRaw) ? (float) $defaultEyeDistanceRaw : 0.0041);
				$snapshot->addCachedData("victimSprintingDistance", is_numeric($victimSprintingDistanceRaw) ? (float) $victimSprintingDistanceRaw : 0.97);
				$snapshot->addCachedData("victimNotSprintingDistance", is_numeric($victimNotSprintingDistanceRaw) ? (float) $victimNotSprintingDistanceRaw : 0.87);
				$snapshot->addCachedData("damagerSprintingDistance", is_numeric($damagerSprintingDistanceRaw) ? (float) $damagerSprintingDistanceRaw : 0.77);
				$snapshot->addCachedData("damagerNotSprintingDistance", is_numeric($damagerNotSprintingDistanceRaw) ? (float) $damagerNotSprintingDistanceRaw : 0.67);
				$snapshot->addCachedData("limit", is_numeric($limitRaw) ? (float) $limitRaw : 3.0);
				$snapshot->validate();
				$this->dispatchAsyncCheck($damager->getName(), $snapshot->build());
			}
		}
	}

	/**
	 * Evaluates an async payload for Reach D violations.
	 *
	 * @param array<string,mixed> $payload Serialized check payload.
	 * @return array<string,mixed> Async decision data.
	 */
	public static function evaluateAsync(array $payload) : array {
		if (!CombatSnapshot::validatePayload(
			$payload,
			"ReachD",
			CombatSnapshot::SCHEMA_VERSION,
			["type", "schemaVersion", "damagerEyeX", "damagerEyeY", "damagerEyeZ", "victimEyeX", "victimEyeY", "victimEyeZ", "damagerPing", "victimPing", "cachedData"],
			[
				"damagerEyeX" => [-30000000.0, 30000000.0],
				"damagerEyeY" => [-2048.0, 2048.0],
				"damagerEyeZ" => [-30000000.0, 30000000.0],
				"victimEyeX" => [-30000000.0, 30000000.0],
				"victimEyeY" => [-2048.0, 2048.0],
				"victimEyeZ" => [-30000000.0, 30000000.0],
				"damagerPing" => [0.0, 60000.0],
				"victimPing" => [0.0, 60000.0],
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
		$damagerPingRaw = $payload["damagerPing"] ?? 0;
		$victimPingRaw = $payload["victimPing"] ?? 0;
		$defaultEyeDistanceRaw = $cachedData["defaultEyeDistance"] ?? 0.0041;
		$victimSprintingDistanceRaw = $cachedData["victimSprintingDistance"] ?? 0.97;
		$victimNotSprintingDistanceRaw = $cachedData["victimNotSprintingDistance"] ?? 0.87;
		$damagerSprintingDistanceRaw = $cachedData["damagerSprintingDistance"] ?? 0.77;
		$damagerNotSprintingDistanceRaw = $cachedData["damagerNotSprintingDistance"] ?? 0.67;
		$limitRaw = $cachedData["limit"] ?? 3.0;

		$damagerEyeX = is_numeric($damagerEyeXRaw) ? (float) $damagerEyeXRaw : 0.0;
		$damagerEyeY = is_numeric($damagerEyeYRaw) ? (float) $damagerEyeYRaw : 0.0;
		$damagerEyeZ = is_numeric($damagerEyeZRaw) ? (float) $damagerEyeZRaw : 0.0;
		$victimEyeX = is_numeric($victimEyeXRaw) ? (float) $victimEyeXRaw : 0.0;
		$victimEyeY = is_numeric($victimEyeYRaw) ? (float) $victimEyeYRaw : 0.0;
		$victimEyeZ = is_numeric($victimEyeZRaw) ? (float) $victimEyeZRaw : 0.0;
		$damagerPing = is_numeric($damagerPingRaw) ? (int) $damagerPingRaw : 0;
		$victimPing = is_numeric($victimPingRaw) ? (int) $victimPingRaw : 0;
		$defaultEyeDistance = is_numeric($defaultEyeDistanceRaw) ? (float) $defaultEyeDistanceRaw : 0.0041;
		$victimSprintingDistance = is_numeric($victimSprintingDistanceRaw) ? (float) $victimSprintingDistanceRaw : 0.97;
		$victimNotSprintingDistance = is_numeric($victimNotSprintingDistanceRaw) ? (float) $victimNotSprintingDistanceRaw : 0.87;
		$damagerSprintingDistance = is_numeric($damagerSprintingDistanceRaw) ? (float) $damagerSprintingDistanceRaw : 0.77;
		$damagerNotSprintingDistance = is_numeric($damagerNotSprintingDistanceRaw) ? (float) $damagerNotSprintingDistanceRaw : 0.67;
		$limit = is_numeric($limitRaw) ? (float) $limitRaw : 3.0;

		$distance = MathUtil::distanceFromComponents(
			$damagerEyeX,
			$damagerEyeY,
			$damagerEyeZ,
			$victimEyeX,
			$victimEyeY,
			$victimEyeZ
		);
		$distance -= $damagerPing * $defaultEyeDistance;
		$distance -= $victimPing * $defaultEyeDistance;
		$distance -= (bool) ($payload["victimSprinting"] ?? false)
			? $victimSprintingDistance
			: $victimNotSprintingDistance;
		$distance -= (bool) ($payload["damagerSprinting"] ?? false)
			? $damagerSprintingDistance
			: $damagerNotSprintingDistance;

		$debug = "distance={$distance}, limit={$limit}";
		if ($distance > $limit) {
			return ["failed" => true, "debug" => $debug];
		}

		return ["debug" => $debug];
	}

	/**
	 * Determines whether reach processing should be skipped for current combat state.
	 *
	 * @param Player $damager Attacking player.
	 * @param Player $victim Victim player.
	 * @param PlayerAPI $damagerAPI Damager context.
	 * @param PlayerAPI $victimAPI Victim context.
	 * @return bool True when the check should be skipped.
	 */
	private function shouldSkip(Player $damager, Player $victim, PlayerAPI $damagerAPI, PlayerAPI $victimAPI) : bool {
		return !$damager->isSurvival() ||
			!$victim->isSurvival() ||
			$victimAPI->getProjectileAttackTicks() < 40 ||
			$damagerAPI->getProjectileAttackTicks() < 40 ||
			$victimAPI->getBowShotTicks() < 40 ||
			$damagerAPI->getBowShotTicks() < 40 ||
			$victimAPI->isRecentlyCancelledEvent() ||
			$damagerAPI->isRecentlyCancelledEvent();
	}
}
