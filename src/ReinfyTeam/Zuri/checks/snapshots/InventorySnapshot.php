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
use ReinfyTeam\Zuri\lang\Lang;
use ReinfyTeam\Zuri\lang\LangKeys;
use ReinfyTeam\Zuri\player\PlayerAPI;

/**
 * Captures immutable inventory state for async worker evaluation.
 *
 * Used by: AutoArmor, ChestAura, ChestStealer, InventoryCleaner,
 * InventoryMove
 */
class InventorySnapshot extends AsyncSnapshot {
	public const SCHEMA_VERSION = 1;

	/** @var array<int,array{id:int,count:int,meta:int}> Inventory contents snapshot (serializable). */
	private array $inventoryContents;

	/** @var array<int,array{id:int,count:int,meta:int}> Armor contents snapshot (serializable). */
	private array $armorContents;

	/** @var array{id:int,count:int,meta:int} Cursor item (serializable). */
	private array $cursorItem;

	/** Selected slot. */
	private int $selectedSlot;

	/** Action state. */
	private int $ping;
	private bool $survival;
	private int $attackTicks;
	private int $teleportTicks;

	/** @var array<string,mixed> Cached inventory data. */
	private array $cachedData = [];

	/**
	 * Captures immutable inventory state for async checks.
	 *
	 * @param string $checkType Check type identifier.
	 * @param Player $player Player entity providing inventory state.
	 * @param PlayerAPI $playerAPI Player API wrapper for tracked ticks.
	 * @return void
	 */
	public function __construct(string $checkType, Player $player, PlayerAPI $playerAPI) {
		parent::__construct($checkType);

		$inventory = $player->getInventory();
		$armorInventory = $player->getArmorInventory();

		// Serialize inventory contents to avoid object references
		$this->inventoryContents = [];
		foreach ($inventory->getContents() as $slot => $item) {
			$this->inventoryContents[$slot] = [
				"id" => $item->getTypeId(),
				"count" => $item->getCount(),
				"meta" => $item->getStateId(),
			];
		}

		$this->armorContents = [];
		foreach ($armorInventory->getContents() as $slot => $item) {
			$this->armorContents[$slot] = [
				"id" => $item->getTypeId(),
				"count" => $item->getCount(),
				"meta" => $item->getStateId(),
			];
		}

		$cursorItem = $inventory->getItemInHand();
		$this->cursorItem = [
			"id" => $cursorItem->getTypeId(),
			"count" => $cursorItem->getCount(),
			"meta" => $cursorItem->getStateId(),
		];

		$this->selectedSlot = $inventory->getHeldItemIndex();
		$this->ping = (int) ($player->getNetworkSession()->getPing() ?? 0);
		$this->survival = $player->isSurvival();
		$this->attackTicks = $playerAPI->getAttackTicks();
		$this->teleportTicks = $playerAPI->getTeleportTicks();
	}

	/**
	 * Add cached inventory data.
	 *
	 * @param string $key Cached data key.
	 * @param mixed $value Cached data value.
	 * @return self Current instance for fluent chaining.
	 */
	public function addCachedData(string $key, mixed $value) : self {
		$this->cachedData[$key] = $value;
		return $this;
	}

	/**
	 * Builds the immutable payload for async processing.
	 *
	 * @return array<string,mixed> Serialized inventory snapshot payload.
	 */
	public function build() : array {
		return [
			"type" => $this->checkType,
			"schemaVersion" => self::SCHEMA_VERSION,
			"captureTime" => $this->captureTime,
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

	/**
	 * Validates snapshot values before async dispatch.
	 *
	 * @throws SnapshotException If snapshot values are invalid.
	 */
	public function validate() : void {
		if ($this->selectedSlot < 0 || $this->selectedSlot >= 9) {
			throw new SnapshotException(Lang::get(LangKeys::DEBUG_SNAPSHOT_INVALID_SELECTED_SLOT));
		}
	}
}
