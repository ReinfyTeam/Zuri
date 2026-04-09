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

use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\config\CacheData;
use ReinfyTeam\Zuri\config\CheckConstants;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function is_numeric;
use function microtime;

class InventoryCleaner extends Check {
	public function getName() : string {
		return "InventoryCleaner";
	}

	public function getSubType() : string {
		return "A";
	}

	public function ban() : bool {
		return false;
	}

	public function kick() : bool {
		return true;
	}

	public function flag() : bool {
		return false;
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		if (!$packet instanceof InventoryTransactionPacket || $packet->trData->getTypeId() !== 0) {
			return;
		}

		if (!$playerAPI->isInventoryOpen()) {
			$playerAPI->unsetExternalData(CacheData::INVENTORYCLEANER_TICKS_TRANSACTION);
			$playerAPI->unsetExternalData(CacheData::INVENTORYCLEANER_TRANSACTION);
			return;
		}

		$start = $playerAPI->getExternalData(CacheData::INVENTORYCLEANER_TICKS_TRANSACTION);
		$transactionRaw = $playerAPI->getExternalData(CacheData::INVENTORYCLEANER_TRANSACTION, 0);
		$transaction = is_numeric($transactionRaw) ? (int) $transactionRaw : 0;
		$now = microtime(true);

		if ($start === null) {
			$playerAPI->setExternalData(CacheData::INVENTORYCLEANER_TICKS_TRANSACTION, $now);
			$playerAPI->setExternalData(CacheData::INVENTORYCLEANER_TRANSACTION, 1);
			return;
		}

		$startFloat = is_numeric($start) ? (float) $start : $now;
		$diff = $now - $startFloat;
		$diffTicksRaw = $this->getConstant(CheckConstants::INVENTORYCLEANER_DIFF_TICKS);
		$diffTicks = is_numeric($diffTicksRaw) ? (float) $diffTicksRaw : 0.0;
		if ($diff > $diffTicks) {
			$playerAPI->setExternalData(CacheData::INVENTORYCLEANER_TICKS_TRANSACTION, $now);
			$playerAPI->setExternalData(CacheData::INVENTORYCLEANER_TRANSACTION, 1);
			return;
		}

		$transaction++;
		$playerAPI->setExternalData(CacheData::INVENTORYCLEANER_TRANSACTION, $transaction);
		$this->debug($playerAPI, "transaction={$transaction}, diff={$diff}");

		$maxTransactionRaw = $this->getConstant(CheckConstants::INVENTORYCLEANER_MAX_TRANSACTION);
		$maxTransaction = is_numeric($maxTransactionRaw) ? (int) $maxTransactionRaw : 0;
		if ($transaction > $maxTransaction) {
			$this->dispatchAsyncDecision($playerAPI, true);
			$playerAPI->setExternalData(CacheData::INVENTORYCLEANER_TICKS_TRANSACTION, $now);
			$playerAPI->setExternalData(CacheData::INVENTORYCLEANER_TRANSACTION, 0);
		}
	}
}