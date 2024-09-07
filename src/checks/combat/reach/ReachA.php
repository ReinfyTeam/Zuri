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
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Event;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use ReinfyTeam\Zuri\utils\MathUtil;
use function abs;

class ReachA extends Check {
	public function getName() : string {
		return "Reach";
	}

	public function getSubType() : string {
		return "A";
	}

	public function maxViolations() : int {
		return 3;
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function checkJustEvent(Event $event) : void {
		if ($event instanceof EntityDamageByEntityEvent) {
			$cause = $event->getCause();
			$entity = $event->getEntity();
			$damager = $event->getDamager();
			$locEntity = $entity->getLocation();
			$locDamager = $damager->getLocation();
			if ($cause === EntityDamageEvent::CAUSE_ENTITY_ATTACK && $damager instanceof Player && $entity instanceof Player) {
				$playerAPI = PlayerAPI::getAPIPlayer($damager);
				$player = $playerAPI->getPlayer();
				$isPlayerTop = $locEntity->getY() > $locDamager->getY() ? abs($locEntity->getY() - $locDamager->getY()) : 0;
				$distance = MathUtil::distance($locEntity, $locDamager) - $isPlayerTop;
				$isSurvival = $player->getGameMode() === GameMode::SURVIVAL();
				$this->debug($playerAPI, "isPlayerTop=$isPlayerTop, distance=$distance, isSurvival=$isSurvival");
				if ($isSurvival && $distance > 4.3) {
					$this->failed($playerAPI);
				}
			}
		}
	}
}