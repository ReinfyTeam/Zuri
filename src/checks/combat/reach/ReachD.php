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

namespace ReinfyTeam\Zuri\checks\combat\reach;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Event;
use pocketmine\player\Player;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\MathUtil;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;

class ReachD extends Check {
	public function getName() : string {
		return "Reach";
	}

	public function getSubType() : string {
		return "D";
	}

	/**
	 * @throws DiscordWebhookException
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

				$useAsync = (bool) ($this->getConstant("async-enabled") ?? false);
				if ($useAsync) {
					$payload = $this->buildAsyncPayload($damager, $victim, $damagerAPI);
					$payload["_minInterval"] = 0.05;
					$this->dispatchAsyncCheck($damager->getName(), $payload);
					return;
				}

				$this->evaluateSync($damager, $victim, $damagerAPI);
			}
		}
	}

	public static function evaluateAsync(array $payload) : array {
		if (($payload["type"] ?? null) !== "ReachD") {
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

		$distance = MathUtil::distanceFromComponents(
			(float) ($payload["damagerEyeX"] ?? 0.0),
			(float) ($payload["damagerEyeY"] ?? 0.0),
			(float) ($payload["damagerEyeZ"] ?? 0.0),
			(float) ($payload["victimEyeX"] ?? 0.0),
			(float) ($payload["victimEyeY"] ?? 0.0),
			(float) ($payload["victimEyeZ"] ?? 0.0)
		);
		$distance -= (int) ($payload["damagerPing"] ?? 0) * (float) ($payload["defaultEyeDistance"] ?? 0.0);
		$distance -= (int) ($payload["victimPing"] ?? 0) * (float) ($payload["defaultEyeDistance"] ?? 0.0);
		$distance -= (bool) ($payload["victimSprinting"] ?? false)
			? (float) ($payload["victimSprintingDistance"] ?? 0.0)
			: (float) ($payload["victimNotSprintingDistance"] ?? 0.0);
		$distance -= (bool) ($payload["damagerSprinting"] ?? false)
			? (float) ($payload["damagerSprintingDistance"] ?? 0.0)
			: (float) ($payload["damagerNotSprintingDistance"] ?? 0.0);

		$limit = (float) ($payload["limit"] ?? 3.0);
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

	private function buildAsyncPayload(Player $damager, Player $victim, PlayerAPI $damagerAPI) : array {
		return [
			"type" => "ReachD",
			"damagerEyeX" => $damager->getEyePos()->getX(),
			"damagerEyeY" => $damager->getEyePos()->getY(),
			"damagerEyeZ" => $damager->getEyePos()->getZ(),
			"victimEyeX" => $victim->getEyePos()->getX(),
			"victimEyeY" => $victim->getEyePos()->getY(),
			"victimEyeZ" => $victim->getEyePos()->getZ(),
			"damagerPing" => $damager->getNetworkSession()->getPing(),
			"victimPing" => $victim->getNetworkSession()->getPing(),
			"defaultEyeDistance" => (float) ($this->getConstant("default-eye-distance") ?? 0.0041),
			"victimSprintingDistance" => (float) ($this->getConstant("sprinting-eye-distance") ?? 0.97),
			"victimNotSprintingDistance" => (float) ($this->getConstant("not-sprinting-eye-distance") ?? 0.87),
			"damagerSprintingDistance" => (float) ($this->getConstant("damager-sprinting-eye-distance") ?? 0.77),
			"damagerNotSprintingDistance" => (float) ($this->getConstant("not-sprinting-damager-eye-distance") ?? 0.67),
			"damagerSprinting" => $damager->isSprinting(),
			"victimSprinting" => $victim->isSprinting(),
			"limit" => (float) ($this->getConstant("reach-eye-limit") ?? 3.0),
			"damagerSurvival" => $damager->isSurvival(),
			"victimSurvival" => $victim->isSurvival(),
			"victimProjectileTicks" => $victimAPI->getProjectileAttackTicks(),
			"damagerProjectileTicks" => $damagerAPI->getProjectileAttackTicks(),
			"victimBowTicks" => $victimAPI->getBowShotTicks(),
			"damagerBowTicks" => $damagerAPI->getBowShotTicks(),
			"victimRecentlyCancelled" => $victimAPI->isRecentlyCancelledEvent(),
			"damagerRecentlyCancelled" => $damagerAPI->isRecentlyCancelledEvent(),
		];
	}

	private function evaluateSync(Player $damager, Player $victim, PlayerAPI $damagerAPI) : void {
		$distance = MathUtil::distanceFromComponents(
			$damager->getEyePos()->getX(),
			$damager->getEyePos()->getY(),
			$damager->getEyePos()->getZ(),
			$victim->getEyePos()->getX(),
			$victim->getEyePos()->getY(),
			$victim->getEyePos()->getZ()
		);
		$distance -= $damager->getNetworkSession()->getPing() * (float) $this->getConstant("default-eye-distance");
		$distance -= $victim->getNetworkSession()->getPing() * (float) $this->getConstant("default-eye-distance");
		$distance -= $victim->isSprinting() ? (float) $this->getConstant("sprinting-eye-distance") : (float) $this->getConstant("not-sprinting-eye-distance");
		$distance -= $damager->isSprinting() ? (float) $this->getConstant("damager-sprinting-eye-distance") : (float) $this->getConstant("not-sprinting-damager-eye-distance");
		$limit = (float) $this->getConstant("reach-eye-limit");

		$this->debug($damagerAPI, "distance={$distance}, limit={$limit}");
		if ($distance > $limit) {
			$this->failed($damagerAPI);
		}
}
}