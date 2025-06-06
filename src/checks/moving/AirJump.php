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

namespace ReinfyTeam\Zuri\checks\moving;

use pocketmine\event\Event;
use pocketmine\event\player\PlayerMoveEvent;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\BlockUtil;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function abs;
use function round;

class AirJump extends Check {
	public function getName() : string {
		return "AirJump";
	}

	public function getSubType() : string {
		return "A";
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		$effects = [];
		$player = $playerAPI->getPlayer();
		if (!$player->spawned && !$player->isConnected()) {
			return;
		} // Effect::$effectInstance bug fix
		if ($event instanceof PlayerMoveEvent) {
			if (
				$playerAPI->getJumpTicks() > 40 ||
				!$player->isSurvival() ||
				$playerAPI->getAttackTicks() < 40 ||
				$playerAPI->getProjectileAttackTicks() < 20 ||
				$playerAPI->getBowShotTicks() < 20 ||
				$playerAPI->getHurtTicks() < 20 ||
				$playerAPI->getSlimeBlockTicks() < 20 ||
				$playerAPI->getTeleportCommandTicks() < 40 ||
				$playerAPI->getTeleportTicks() < 60 ||
				$playerAPI->isOnAdhesion() ||
				($player->isOnGround() && $player->getInAirTicks() < 5) ||
				$player->isFlying() ||
				$player->getAllowFlight() ||
				$player->hasNoClientPredictions() ||
				!$playerAPI->isCurrentChunkIsLoaded() ||
				BlockUtil::isGroundSolid($player) ||
				$playerAPI->isGliding()
			) {
				return;
			}

			$upDistance = round(($event->getTo()->getY() - $event->getFrom()->getY()), 3);
			$lastUpDistance = $playerAPI->getExternalData("lastUpDistance") ?? 0;
			$delta = abs(round(($upDistance - $lastUpDistance), 3));
			$limit = 0.852;

			if ($delta > $limit) { // what is this dumb check
				$this->failed($playerAPI);
			}

			$playerAPI->setExternalData("lastUpDistance", $upDistance);
			$this->debug($playerAPI, "upDistance=$upDistance, lastUpDistance=$lastUpDistance, delta=$delta, limit=$limit");
		}
	}
}