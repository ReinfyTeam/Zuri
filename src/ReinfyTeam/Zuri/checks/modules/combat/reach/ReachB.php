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

class ReachB extends Check {
	public function getName() : string {
		return "Reach";
	}

	public function getSubType() : string {
		return "B";
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

		$snapshot = new CombatSnapshot("ReachB", $damager, $damagerAPI, $entity, $entityAPI);
		$snapshot->addCachedData("maxDistance", (float) $this->getConstant(CheckConstants::REACHB_SURVIVAL_MAX_DISTANCE));
		$this->dispatchAsyncCheck($damager->getName(), $snapshot->build());
	}

	public static function evaluateAsync(array $payload) : array {
		if (($payload["type"] ?? null) !== "ReachB") {
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
		$distance = ($payload["damagerEyeX"] - $payload["victimEyeX"]) ** 2 +
			($payload["damagerEyeZ"] - $payload["victimEyeZ"]) ** 2;
		$limit = (float) ($cachedData["maxDistance"] ?? 0.0);
		$debug = "distance={$distance}, limit={$limit}";
		if ($distance > $limit) {
			return ["failed" => true, "debug" => $debug];
		}
		return ["debug" => $debug];
	}
}