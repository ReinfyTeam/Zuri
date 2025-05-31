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

namespace ReinfyTeam\Zuri\checks\moving\speed;

use pocketmine\network\mcpe\protocol\DataPacket;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;

class AntiVoid extends Check {
	public function getName() : string {
		return "AntiVoid";
	}

	public function getSubType() : string {
		return "A";
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		if (
			$playerAPI->isOnAdhesion() ||
			$playerAPI->isInLiquid() ||
			$playerAPI->isInWeb() ||
			$playerAPI->getDeathTicks() < 100 ||
			$playerAPI->getJumpTicks() < 60 ||
			$playerAPI->getTeleportCommandTicks() < 100
		) {
			return;
		}
		$lastY = $playerAPI->getExternalData("lastYB");
		$playerAPI->setExternalData("lastYB", $playerAPI->getPlayer()->getLocation()->getY());
		if ($lastY !== null && $playerAPI->isOnGround()) {
			if ($lastY < $playerAPI->getPlayer()->getLocation()->getY()) {
				$this->failed($playerAPI);
			}
			$playerAPI->unsetExternalData("lastYB");
		}

		$lastYB = $playerAPI->getExternalData("lastYB");
		$this->debug($playerAPI, "lastY=$lastY, lastYB=$lastYB");
	}
}