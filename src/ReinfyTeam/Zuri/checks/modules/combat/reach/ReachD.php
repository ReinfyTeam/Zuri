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

class ReachD extends Check {
	public function getName() : string {
		return "Reach";
	}

	public function getSubType() : string {
		return "D";
	}

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

				$snapshot = new CombatSnapshot("ReachD", $damager, $damagerAPI, $victim, $victimAPI);
				$snapshot->addCachedData("defaultEyeDistance", (float) ($this->getConstant(CheckConstants::REACHD_DEFAULT_EYE_DISTANCE) ?? 0.0041));
				$snapshot->addCachedData("victimSprintingDistance", (float) ($this->getConstant(CheckConstants::REACHD_SPRINTING_EYE_DISTANCE) ?? 0.97));
				$snapshot->addCachedData("victimNotSprintingDistance", (float) ($this->getConstant(CheckConstants::REACHD_NOT_SPRINTING_EYE_DISTANCE) ?? 0.87));
				$snapshot->addCachedData("damagerSprintingDistance", (float) ($this->getConstant(CheckConstants::REACHD_DAMAGER_SPRINTING_EYE_DISTANCE) ?? 0.77));
				$snapshot->addCachedData("damagerNotSprintingDistance", (float) ($this->getConstant(CheckConstants::REACHD_NOT_SPRINTING_DAMAGER_EYE_DISTANCE) ?? 0.67));
				$snapshot->addCachedData("limit", (float) ($this->getConstant(CheckConstants::REACHD_REACH_EYE_LIMIT) ?? 3.0));
				$snapshot->validate();
				$this->dispatchAsyncCheck($damager->getName(), $snapshot->build());
			}
		}
	}

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

		if (
			!(bool) ($payload["damagerSurvival"] ?? false) ||
			!(bool) ($payload["victimSurvival"] ?? false) ||
			(int) ($payload["victimProjectileTicks"] ?? 0) < 40 ||
			(int) ($payload["damagerProjectileTicks"] ?? 0) < 40 ||
			(int) ($payload["victimBowTicks"] ?? 0) < 40 ||
			(int) ($payload["damagerBowTicks"] ?? 0) < 40 ||
			(bool) ($payload["victimRecentlyCancelled"] ?? false) ||
			(bool) ($payload["damagerRecentlyCancelled"] ?? false)
		) {
			return [];
		}

		$cachedData = $payload["cachedData"] ?? [];
		$distance = MathUtil::distanceFromComponents(
			(float) ($payload["damagerEyeX"] ?? 0.0),
			(float) ($payload["damagerEyeY"] ?? 0.0),
			(float) ($payload["damagerEyeZ"] ?? 0.0),
			(float) ($payload["victimEyeX"] ?? 0.0),
			(float) ($payload["victimEyeY"] ?? 0.0),
			(float) ($payload["victimEyeZ"] ?? 0.0)
		);
		$distance -= (int) ($payload["damagerPing"] ?? 0) * (float) ($cachedData["defaultEyeDistance"] ?? 0.0041);
		$distance -= (int) ($payload["victimPing"] ?? 0) * (float) ($cachedData["defaultEyeDistance"] ?? 0.0041);
		$distance -= (bool) ($payload["victimSprinting"] ?? false)
			? (float) ($cachedData["victimSprintingDistance"] ?? 0.97)
			: (float) ($cachedData["victimNotSprintingDistance"] ?? 0.87);
		$distance -= (bool) ($payload["damagerSprinting"] ?? false)
			? (float) ($cachedData["damagerSprintingDistance"] ?? 0.77)
			: (float) ($cachedData["damagerNotSprintingDistance"] ?? 0.67);

		$limit = (float) ($cachedData["limit"] ?? 3.0);
		$debug = "distance={$distance}, limit={$limit}";
		if ($distance > $limit) {
			return ["failed" => true, "debug" => $debug];
		}

		return ["debug" => $debug];
	}

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