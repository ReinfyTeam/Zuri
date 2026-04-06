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

namespace ReinfyTeam\Zuri\checks\inventory;

use ReinfyTeam\Zuri\cache\CacheData;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function microtime;

class ChestStealer extends Check {
	public function getName() : string {
		return "ChestStealer";
	}

	public function getSubType() : string {
		return "A";
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		$ticks = $playerAPI->getExternalData(CacheData::CHESTSTEALER_TICKS);
		$lastTime = $playerAPI->getExternalData(CacheData::CHESTSTEALER_LAST_TIME);
		if ($packet instanceof InventoryTransactionPacket) {
			if ($packet->trData->getTypeId() === 0) {
				if ($ticks !== null && $lastTime !== null) {
					$diff = microtime(true) - $lastTime;
					if ($diff > $this->getConstant("diff-time")) {
						if ($ticks > $this->getConstant("diff-ticks")) {
							$this->failed($playerAPI);
						}
						$playerAPI->unsetExternalData(CacheData::CHESTSTEALER_TICKS);
						$playerAPI->unsetExternalData(CacheData::CHESTSTEALER_LAST_TIME);
					} else {
						$playerAPI->setExternalData(CacheData::CHESTSTEALER_TICKS, $ticks + 1);
					}
				} else {
					$playerAPI->setExternalData(CacheData::CHESTSTEALER_TICKS, 0);
					$playerAPI->setExternalData(CacheData::CHESTSTEALER_LAST_TIME, microtime(true));
				}
				$this->debug($playerAPI, "ticks=$ticks, lastTime=$lastTime");
			}
		}
	}
}