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
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
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
			$player = $event->getEntity();

			if ($player instanceof Player && $damager instanceof Player) {
				$damagerAPI = PlayerAPI::getAPIPlayer($damager);
				$playerAPI = PlayerAPI::getAPIPlayer($player);

				if (
					$damager->isSurvival() ||
					$entity->isSurvival() ||
					$playerAPI->getProjectileAttackTicks() < 40 ||
					$damagerAPI->getProjectileAttackTicks() < 40 ||
					$playerAPI->getBowShotTicks() < 40 ||
					$damagerAPI->getBowShotTicks() < 40 ||
					$playerAPI->recentlyCancelledEvent() < 40
				) { // false-positive in projectiles
					return;
				}

				$damagerPing = $damager->getNetworkSession()->getPing();
				$playerPing = $player->getNetworkSession()->getPing();
				$distance = $player->getEyePos()->distance(new Vector3($damager->getEyePos()->getX(), $player->getEyePos()->getY(), $damager->getEyePos()->getZ()));
				$distance -= $damagerPing * $this->getConstant("default-eye-distance");
				$distance -= $playerPing * $this->getConstant("default-eye-distance");
				$limit = $this->getConstant("reach-eye-limit");

				if ($player->isSprinting()) {
					$distance -= $this->getConstant("sprinting-eye-distance");
				} else {
					$distance -= $this->getConstant("not-sprinting-eye-distance");
				}

				if ($damager->isSprinting()) {
					$distance -= $this->getConstant("damager-sprinting-eye-distance");
				} elseif (!$damager->isSprinting()) {
					$distance -= $this->getConstant("not-sprinting-damager-eye-distance");
				}

				$this->debug($damagerAPI, "distance=$distance, limit=$limit");
				if ($distance > $limit) {
					$this->failed($damagerAPI);
				}
			}
		}
	}
}