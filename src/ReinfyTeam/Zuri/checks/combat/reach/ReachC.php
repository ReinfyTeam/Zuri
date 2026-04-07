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

use ReinfyTeam\Zuri\config\CheckConstants;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Event;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;

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

		$eyeHeight = $damager->getEyePos();
		$cuboid = $victim->getBoundingBox();
		$distance = $eyeHeight->distance(new Vector3($cuboid->minX, $cuboid->minY, $cuboid->minZ));
		$limit = (float) $this->getConstant(CheckConstants::REACHC_MAX_REACH_EYE_DISTANCE);
		$this->debug($damagerAPI, "distance=$distance, limit=$limit");
		if ($distance > $limit) {
			$this->failed($damagerAPI);
		}
	}
}