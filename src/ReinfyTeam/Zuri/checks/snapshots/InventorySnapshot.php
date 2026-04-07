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

namespace ReinfyTeam\Zuri\checks\snapshots;

use pocketmine\player\Player;
use ReinfyTeam\Zuri\player\PlayerAPI;
use function is_array;

/**
 * Captures immutable inventory state for async worker evaluation.
 *
 * Used by: AutoArmor, ChestAura, ChestStealer, InventoryCleaner,
 * InventoryMove
 */
class InventorySnapshot extends AsyncSnapshot {
	/** Inventory contents snapshot (serializable). */
	private array $inventoryContents;

	/** Armor contents snapshot (serializable). */
	private array $armorContents;

	/** Cursor item (serializable). */
	private mixed $cursorItem;

	/** Selected slot. */
	private int $selectedSlot;

	/** Action state. */
	private int $ping;
	private bool $survival;
	private int $attackTicks;
	private int $teleportTicks;

	/** Cached inventory data. */
	private mixed $cachedData = [];

	public function __construct(string $checkType, Player $player, PlayerAPI $playerAPI) {
		parent::__construct($checkType);

		$inventory = $player->getInventory();
		$armorInventory = $player->getArmorInventory();

		// Serialize inventory contents to avoid object references
		$this->inventoryContents = [];
		foreach ($inventory->getContents() as $slot => $item) {
			$this->inventoryContents[$slot] = [
				"id" => $item->getId(),
				"count" => $item->getCount(),
				"meta" => $item->getMeta(),
			];
		}

		$this->armorContents = [];
		foreach ($armorInventory->getContents() as $slot => $item) {
			$this->armorContents[$slot] = [
				"id" => $item->getId(),
				"count" => $item->getCount(),
				"meta" => $item->getMeta(),
			];
		}

		$cursorItem = $inventory->getCursorItem();
		$this->cursorItem = [
			"id" => $cursorItem->getId(),
			"count" => $cursorItem->getCount(),
			"meta" => $cursorItem->getMeta(),
		];

		$this->selectedSlot = $inventory->getHeldItemIndex();
		$this->ping = $player->getNetworkSession()->getPing();
		$this->survival = $player->isSurvival();
		$this->attackTicks = $playerAPI->getAttackTicks();
		$this->teleportTicks = $playerAPI->getTeleportTicks();
	}

	/**
	 * Add cached inventory data.
	 */
	public function addCachedData(string $key, mixed $value) : self {
		$this->cachedData[$key] = $value;
		return $this;
	}

	public function build() : array {
		return [
			"type" => $this->checkType,
			"inventoryContents" => $this->inventoryContents,
			"armorContents" => $this->armorContents,
			"cursorItem" => $this->cursorItem,
			"selectedSlot" => $this->selectedSlot,
			"ping" => $this->ping,
			"survival" => $this->survival,
			"attackTicks" => $this->attackTicks,
			"teleportTicks" => $this->teleportTicks,
			"cachedData" => $this->cachedData,
		];
	}

	public function validate() : void {
		if (!is_array($this->inventoryContents) || !is_array($this->armorContents)) {
			throw new SnapshotException("Invalid inventory contents in snapshot");
		}
		if ($this->selectedSlot < 0 || $this->selectedSlot >= 9) {
			throw new SnapshotException("Invalid selected slot in inventory snapshot");
		}
	}
}
