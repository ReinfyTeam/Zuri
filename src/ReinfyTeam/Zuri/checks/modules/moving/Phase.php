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

use pocketmine\block\BlockTypeIds;
use pocketmine\event\Event;
use pocketmine\event\player\PlayerMoveEvent;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\config\CacheData;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\BlockUtil;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function array_flip;
use function array_keys;
use function is_numeric;
use function max;

/**
 * Detects movement through solid blocks.
 */
class Phase extends Check {
	private const BUFFER_KEY = CacheData::PHASE_A_BUFFER;
	private const BUFFER_LIMIT = 3;

	/** @var array<int,int>|null */
	private static ?array $skipFlipped = null;
	/** @var list<int>|null */
	private static ?array $skipIds = null;

	/**
	 * Gets the check name.
	 */
	public function getName() : string {
		return "Phase";
	}

	/**
	 * Gets the check subtype identifier.
	 */
	public function getSubType() : string {
		return "A";
	}

	/**
	 * Handles movement events for phase detection.
	 *
	 * @param Event $event Triggered event instance.
	 * @param PlayerAPI $playerAPI Player state wrapper.
	 *
	 * @throws DiscordWebhookException
	 */
	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if (!$event instanceof PlayerMoveEvent) {
			return;
		}

		$player = $event->getPlayer();
		$world = $player->getWorld();
		if (!$player->isConnected() || !$player->isOnline()) {
			return;
		}

		if ($event->isCancelled() || $playerAPI->isRecentlyCancelledEvent()) {
			$this->decreaseBuffer($playerAPI);
			return;
		}

		$to = $event->getTo();
		if (BlockUtil::isOnDoor($to, 0) || BlockUtil::isOnDoor($to, 1)) {
			$this->decreaseBuffer($playerAPI);
			return;
		}

		$above = $world->getBlock($to->add(0, 1, 0));
		$below = $world->getBlock($to->add(0, -1, 0));
		if (!$above->isSolid() || !$below->isSolid()) {
			$this->decreaseBuffer($playerAPI);
			return;
		}

		$skipFlipped = self::getSkipFlipped();
		if (
			!$player->isSurvival() ||
			$playerAPI->isOnCarpet() ||
			$playerAPI->isOnPlate() ||
			$playerAPI->isOnDoor() ||
			$playerAPI->isOnSnow() ||
			$playerAPI->isOnPlant() ||
			$playerAPI->isOnAdhesion() ||
			$playerAPI->isOnStairs() ||
			$playerAPI->isInLiquid() ||
			$playerAPI->isInWeb() ||
			isset($skipFlipped[$below->getTypeId()]) ||
			BlockUtil::isUnderBlock($to, self::getSkipIds(), 0)
		) {
			$this->decreaseBuffer($playerAPI);
			return;
		}

		$bufferRaw = $playerAPI->getExternalData(self::BUFFER_KEY, 0);
		$buffer = (is_numeric($bufferRaw) ? (int) $bufferRaw : 0) + 1;
		$playerAPI->setExternalData(self::BUFFER_KEY, $buffer);
		$this->debug($playerAPI, "fromY=" . $event->getFrom()->getY() . ", toY=" . $to->getY() . ", buffer={$buffer}");
		if ($buffer >= self::BUFFER_LIMIT) {
			$playerAPI->setExternalData(self::BUFFER_KEY, 0);
			$this->dispatchAsyncDecision($playerAPI, true);
		}
	}

	/**
	 * Decreases the local phase buffer value.
	 *
	 * @param PlayerAPI $playerAPI Player state wrapper.
	 */
	private function decreaseBuffer(PlayerAPI $playerAPI) : void {
		$bufferRaw = $playerAPI->getExternalData(self::BUFFER_KEY, 0);
		$buffer = max(0, (is_numeric($bufferRaw) ? (int) $bufferRaw : 0) - 1);
		$playerAPI->setExternalData(self::BUFFER_KEY, $buffer);
	}

	/**
	 * Gets a flipped lookup of block IDs that should be skipped.
	 *
	 * @return array<int,int>
	 */
	private static function getSkipFlipped() : array {
		if (self::$skipFlipped !== null) {
			return self::$skipFlipped;
		}

		self::$skipFlipped = array_flip([
			BlockTypeIds::SAND,
			BlockTypeIds::GRAVEL,
			BlockTypeIds::ANVIL,
			BlockTypeIds::AIR,
			BlockTypeIds::TORCH,
			BlockTypeIds::ACACIA_SIGN,
			BlockTypeIds::ACACIA_WALL_SIGN,
			BlockTypeIds::REDSTONE_TORCH,
			BlockTypeIds::REDSTONE_WIRE,
			BlockTypeIds::SEA_PICKLE,
			BlockTypeIds::REDSTONE_REPEATER,
			BlockTypeIds::LANTERN,
			BlockTypeIds::REDSTONE_COMPARATOR,
			BlockTypeIds::BIRCH_WALL_SIGN,
			BlockTypeIds::DARK_OAK_WALL_SIGN,
			BlockTypeIds::JUNGLE_WALL_SIGN,
			BlockTypeIds::OAK_WALL_SIGN,
			BlockTypeIds::SPRUCE_WALL_SIGN,
			BlockTypeIds::MANGROVE_WALL_SIGN,
			BlockTypeIds::CRIMSON_WALL_SIGN,
			BlockTypeIds::WARPED_WALL_SIGN,
			BlockTypeIds::CHERRY_WALL_SIGN,
			BlockTypeIds::BIRCH_SIGN,
			BlockTypeIds::DARK_OAK_SIGN,
			BlockTypeIds::JUNGLE_SIGN,
			BlockTypeIds::OAK_SIGN,
			BlockTypeIds::SPRUCE_SIGN,
			BlockTypeIds::MANGROVE_SIGN,
			BlockTypeIds::CRIMSON_SIGN,
			BlockTypeIds::WARPED_SIGN,
			BlockTypeIds::CHERRY_SIGN,
			BlockTypeIds::GLASS_PANE,
			BlockTypeIds::HARDENED_GLASS_PANE,
			BlockTypeIds::STAINED_GLASS_PANE,
			BlockTypeIds::STAINED_HARDENED_GLASS_PANE,
			BlockTypeIds::COBWEB,
			BlockTypeIds::BED,
			BlockTypeIds::BELL,
			BlockTypeIds::CACTUS,
			BlockTypeIds::CARPET,
			BlockTypeIds::COBBLESTONE_WALL,
			BlockTypeIds::ACACIA_FENCE,
			BlockTypeIds::OAK_FENCE,
			BlockTypeIds::BIRCH_FENCE,
			BlockTypeIds::DARK_OAK_FENCE,
			BlockTypeIds::JUNGLE_FENCE,
			BlockTypeIds::NETHER_BRICK_FENCE,
			BlockTypeIds::SPRUCE_FENCE,
			BlockTypeIds::WARPED_FENCE,
			BlockTypeIds::MANGROVE_FENCE,
			BlockTypeIds::CRIMSON_FENCE,
			BlockTypeIds::CHERRY_FENCE,
			BlockTypeIds::ACACIA_FENCE_GATE,
			BlockTypeIds::OAK_FENCE_GATE,
			BlockTypeIds::BIRCH_FENCE_GATE,
			BlockTypeIds::DARK_OAK_FENCE_GATE,
			BlockTypeIds::JUNGLE_FENCE_GATE,
			BlockTypeIds::SPRUCE_FENCE_GATE,
			BlockTypeIds::WARPED_FENCE_GATE,
			BlockTypeIds::MANGROVE_FENCE_GATE,
			BlockTypeIds::CRIMSON_FENCE_GATE,
			BlockTypeIds::CHERRY_FENCE_GATE,
			BlockTypeIds::BEETROOTS,
			BlockTypeIds::CAKE,
			BlockTypeIds::CARROTS,
			BlockTypeIds::FIRE,
			BlockTypeIds::BAMBOO,
			BlockTypeIds::BAMBOO_SAPLING,
		]);

		return self::$skipFlipped;
	}

	/**
	 * Gets the block IDs that should be skipped for phase checks.
	 *
	 * @return list<int>
	 */
	private static function getSkipIds() : array {
		if (self::$skipIds !== null) {
			return self::$skipIds;
		}

		self::$skipIds = array_keys(self::getSkipFlipped());
		return self::$skipIds;
	}
}
