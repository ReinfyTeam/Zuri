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

namespace ReinfyTeam\Zuri\checks\modules\blockplace\scaffold;

use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\Event;
use pocketmine\network\mcpe\protocol\DataPacket;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\config\CacheData;
use ReinfyTeam\Zuri\config\CheckConstants;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function abs;
use function is_numeric;

class ScaffoldB extends Check {
	public function getName() : string {
		return "Scaffold";
	}

	public function getSubType() : string {
		return "B";
	}

	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if ($event instanceof BlockPlaceEvent) {
			$pitch = abs($playerAPI->getLocation()->getPitch());
			$distanceY = $event->getBlockAgainst()->getPosition()->getY() < $playerAPI->getLocation()->getY();
			$oldPitchRaw = $playerAPI->getExternalData(CacheData::SCAFFOLD_B_OLD_PITCH) ?? 0;
			$oldPitch = is_numeric($oldPitchRaw) ? (float) $oldPitchRaw : 0.0;
			$this->debug($playerAPI, "oldPitch={$oldPitch} distanceY={$distanceY}, newPitch={$pitch}, ping=" . $playerAPI->getPing());
			$suspiciousPitchLimitRaw = $this->getConstant(CheckConstants::SCAFFOLDB_SUSPECIOUS_PITCH_LIMIT);
			$suspiciousPitchLimit = is_numeric($suspiciousPitchLimitRaw) ? (float) $suspiciousPitchLimitRaw : 0.0;
			if (
				$pitch < $suspiciousPitchLimit && // is this has good calculation enough?
				$distanceY && // it depends on block placed is under the player..
				$oldPitch === $pitch && // for using bedrock long bridging lol anti-false kick
				$playerAPI->getPing() < self::getData(self::PING_LAGGING)
			) {
				$this->dispatchAsyncDecision($playerAPI, true);
			}
			$playerAPI->setExternalData(CacheData::SCAFFOLD_B_OLD_PITCH, $pitch); // patching new pitch here..
		}
	}
}
