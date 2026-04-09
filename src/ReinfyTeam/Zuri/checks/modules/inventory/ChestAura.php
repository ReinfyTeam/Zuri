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

namespace ReinfyTeam\Zuri\checks\modules\inventory;

use pocketmine\event\Event;
use pocketmine\event\inventory\InventoryCloseEvent;
use pocketmine\event\inventory\InventoryOpenEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\inventory\PlayerCraftingInventory;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\config\CacheData;
use ReinfyTeam\Zuri\config\CheckConstants;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function is_numeric;
use function max;
use function microtime;

class ChestAura extends Check {
	public function getName() : string {
		return "ChestAura";
	}

	public function getSubType() : string {
		return "A";
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if ($event instanceof InventoryOpenEvent) {
			if (!($event->getInventory() instanceof PlayerCraftingInventory)) {
				$playerAPI->setExternalData(CacheData::CHESTAURA_TIME_OPEN_CHEST, microtime(true));
				$playerAPI->setExternalData(CacheData::CHESTAURA_COUNT_TRANSACTION, 0);
			}
			return;
		}

		$timeOpenChest = $playerAPI->getExternalData(CacheData::CHESTAURA_TIME_OPEN_CHEST);
		$countTransactionRaw = $playerAPI->getExternalData(CacheData::CHESTAURA_COUNT_TRANSACTION, 0);
		$countTransaction = is_numeric($countTransactionRaw) ? (int) $countTransactionRaw : 0;

		if ($event instanceof InventoryCloseEvent) {
			if ($timeOpenChest !== null) {
				$timeDiff = microtime(true) - (is_numeric($timeOpenChest) ? (float) $timeOpenChest : microtime(true));
				$transactionDivisibleRaw = $this->getConstant(CheckConstants::CHESTAURA_TRANSACTION_DIVISIBLE);
				$transactionDivisible = is_numeric($transactionDivisibleRaw) ? (float) $transactionDivisibleRaw : 0.0;
				$rate = $countTransaction / max(0.001, $timeDiff);
				$this->debug($playerAPI, "timeDiff={$timeDiff}, count={$countTransaction}, rate={$rate}");
				if ($countTransaction >= 6 && $rate > $transactionDivisible) {
					$this->dispatchAsyncDecision($playerAPI, true);
				}
			}
			$playerAPI->unsetExternalData(CacheData::CHESTAURA_TIME_OPEN_CHEST);
			$playerAPI->unsetExternalData(CacheData::CHESTAURA_COUNT_TRANSACTION);
			return;
		}

		if ($event instanceof InventoryTransactionEvent) {
			if ($timeOpenChest !== null) {
				$playerAPI->setExternalData(CacheData::CHESTAURA_COUNT_TRANSACTION, $countTransaction + 1);
				$this->debug($playerAPI, "count=" . ($countTransaction + 1));
			}
		}
	}
}