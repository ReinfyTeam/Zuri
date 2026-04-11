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

namespace ReinfyTeam\Zuri\checks\modules\moving;

use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\Event;
use pocketmine\event\player\PlayerMoveEvent;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\config\CacheData;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\BlockUtil;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function abs;
use function is_numeric;
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
		$player = $playerAPI->getPlayer();
		if (!$player->isConnected() || !$player->isOnline()) {
			return;
		} // Effect::$effectInstance bug fix
		if ($event instanceof PlayerMoveEvent) {
			$upDistance = round(($event->getTo()->getY() - $event->getFrom()->getY()), 3);
			$pingLaggingRaw = self::getData(self::PING_LAGGING);
			$pingLagging = is_numeric($pingLaggingRaw) ? (int) $pingLaggingRaw : 0;
			if (
				$playerAPI->getJumpTicks() > 40 ||
				!$player->isSurvival() ||
				$playerAPI->isInFPCooldown() ||
				($pingLagging > 0 && $playerAPI->getPing() > $pingLagging) ||
				$playerAPI->isInLiquid() ||
				$playerAPI->isInWeb() ||
				$playerAPI->isOnIce() ||
				$playerAPI->getAttackTicks() < 40 ||
				$playerAPI->getProjectileAttackTicks() < 20 ||
				$playerAPI->getBowShotTicks() < 20 ||
				$playerAPI->getHurtTicks() < 20 ||
				$playerAPI->getSlimeBlockTicks() < 20 ||
				$playerAPI->getTeleportCommandTicks() < 40 ||
				$playerAPI->getTeleportTicks() < 60 ||
				$playerAPI->isOnAdhesion() ||
				$playerAPI->isOnGround() ||
				($player->isOnGround() && $player->getInAirTicks() < 5) ||
				$player->isFlying() ||
				$player->getAllowFlight() ||
				$player->hasNoClientPredictions() ||
				!$playerAPI->isCurrentChunkIsLoaded() ||
				BlockUtil::isGroundSolid($player) ||
				$playerAPI->isGliding() ||
				$player->getInAirTicks() < 2 ||
				$upDistance <= 0.0 ||
				$playerAPI->isRecentlyCancelledEvent()
			) {
				return;
			}

			$lastUpDistanceRaw = $playerAPI->getExternalData(CacheData::AIRJUMP_LAST_UP_DISTANCE) ?? 0.0;
			$lastUpDistance = is_numeric($lastUpDistanceRaw) ? (float) $lastUpDistanceRaw : 0.0;
			$delta = abs(round(($upDistance - $lastUpDistance), 3));
			// Vanilla jump ascent per move is around 0.42 (slightly more with network jitter).
			$limit = 0.47;

			if (($effect = $player->getEffects()->get(VanillaEffects::JUMP_BOOST())) !== null) {
				$level = $effect->getEffectLevel() + 1;
				$limit += (0.1 * $level) + 0.03;
			}

			if ($upDistance > $limit) {
				$this->dispatchAsyncDecision($playerAPI, true, "", [], [], 0.10);
			}

			$playerAPI->setExternalData(CacheData::AIRJUMP_LAST_UP_DISTANCE, $upDistance);
			$this->debug($playerAPI, "upDistance=$upDistance, lastUpDistance=$lastUpDistance, delta=$delta, limit=$limit, inAirTicks=" . $player->getInAirTicks());
		}
	}
}
