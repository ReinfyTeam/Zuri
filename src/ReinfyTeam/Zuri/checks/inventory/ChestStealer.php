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

use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\config\CacheData;
use ReinfyTeam\Zuri\config\CheckConstants;
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
		if (!$packet instanceof InventoryTransactionPacket || $packet->trData->getTypeId() !== 0) {
			return;
		}

		if (!$playerAPI->isInventoryOpen()) {
			$playerAPI->unsetExternalData(CacheData::CHESTSTEALER_TICKS);
			$playerAPI->unsetExternalData(CacheData::CHESTSTEALER_LAST_TIME);
			return;
		}

		$now = microtime(true);
		$streak = (int) $playerAPI->getExternalData(CacheData::CHESTSTEALER_TICKS, 0);
		$lastTime = $playerAPI->getExternalData(CacheData::CHESTSTEALER_LAST_TIME);
		if ($lastTime === null) {
			$playerAPI->setExternalData(CacheData::CHESTSTEALER_TICKS, 0);
			$playerAPI->setExternalData(CacheData::CHESTSTEALER_LAST_TIME, $now);
			return;
		}

		$diff = $now - (float) $lastTime;
		if ($diff <= (float) $this->getConstant(CheckConstants::CHESTSTEALER_DIFF_TIME)) {
			$streak++;
		} else {
			$streak = 0;
		}

		$playerAPI->setExternalData(CacheData::CHESTSTEALER_TICKS, $streak);
		$playerAPI->setExternalData(CacheData::CHESTSTEALER_LAST_TIME, $now);
		$this->debug($playerAPI, "streak={$streak}, diff={$diff}");

		if ($streak > (int) $this->getConstant(CheckConstants::CHESTSTEALER_DIFF_TICKS)) {
			$this->failed($playerAPI);
			$playerAPI->setExternalData(CacheData::CHESTSTEALER_TICKS, 0);
		}
	}
}