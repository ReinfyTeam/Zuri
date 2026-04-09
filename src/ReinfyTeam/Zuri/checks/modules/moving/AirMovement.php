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

use pocketmine\lang\Translatable;
use pocketmine\network\mcpe\protocol\DataPacket;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\config\CheckConstants;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function is_numeric;
use function is_string;

class AirMovement extends Check {
	public function getName() : string {
		return "AirMovement";
	}

	public function getSubType() : string {
		return "A";
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		$effects = [];
		$player = $playerAPI->getPlayer();
		if (!$player->isConnected() || !$player->isOnline()) {
			return;
		} // Effect::$effectInstance bug fix
		foreach ($player->getEffects()->all() as $effect) {
			$typeName = $effect->getType()->getName();
			if ($typeName instanceof Translatable) {
				$transtable = $typeName->getText();
			} elseif (is_string($typeName)) {
				$transtable = $typeName;
			} else {
				continue;
			}
			$effects[$transtable] = $effect->getEffectLevel() + 1;
		}
		$nLocation = $playerAPI->getNLocation();
		if (!empty($nLocation)) {
			if (
				$playerAPI->getAttackTicks() > 100 &&
				$playerAPI->getTeleportTicks() > 100 &&
				$playerAPI->getSlimeBlockTicks() > 200 &&
				$playerAPI->getBowShotTicks() < 20 &&
				!$player->getAllowFlight() &&
				!$playerAPI->isInLiquid() &&
				!$playerAPI->isInWeb() &&
				!$playerAPI->isOnGround() &&
				!$playerAPI->isOnAdhesion() &&
				$player->isSurvival() &&
				$playerAPI->getLastGroundY() !== 0.0 &&
				$nLocation["to"]->getY() > $playerAPI->getLastGroundY() &&
				$nLocation["to"]->getY() > $nLocation["from"]->getY() &&
				$playerAPI->getOnlineTime() >= 30 &&
				$playerAPI->getPing() < self::getData(self::PING_LAGGING) &&
				!$playerAPI->isRecentlyCancelledEvent()
			) {
				$distance = $nLocation["to"]->getY() - $playerAPI->getLastGroundY();
				$limitRaw = $this->getConstant(CheckConstants::AIRMOVEMENT_AIR_LIMIT);
				$amplifierRaw = $this->getConstant(CheckConstants::AIRMOVEMENT_EFFECT_AMPLIFIER);
				$multiplierRaw = $this->getConstant(CheckConstants::AIRMOVEMENT_EFFECT_MULTIPLIER);
				$constRaw = $this->getConstant(CheckConstants::AIRMOVEMENT_EFFECT_CONST);
				$limit = is_numeric($limitRaw) ? (float) $limitRaw : 0.0;
				$amplifier = is_numeric($amplifierRaw) ? (float) $amplifierRaw : 0.0;
				$multiplier = is_numeric($multiplierRaw) ? (float) $multiplierRaw : 1.0;
				$effectConst = is_numeric($constRaw) ? (float) $constRaw : 1.0;
				if (isset($effects["potion.jump"]) && $effectConst != 0.0) {
					$jumpLevel = (float) $effects["potion.jump"];
					$limit += (($jumpLevel + $amplifier) ** $multiplier) / $effectConst;
				}
				if ($distance > $limit) {
					$this->dispatchAsyncDecision($playerAPI, true);
				}
				$this->debug($playerAPI, "distance=$distance, limit=$limit");
			}
		}
	}
}