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
use function is_float;

/**
 * Captures immutable block interaction state for async worker evaluation.
 *
 * Used by: BlockBreak, BlockPlace, BlockInteract, WrongMining, InstaBreak,
 * FastBreak, Tower, Scaffold, FillBlock, BlockReach
 */
class BlockSnapshot extends AsyncSnapshot {
	public const SCHEMA_VERSION = 1;

	/** Block position. */
	private float $blockX;
	private float $blockY;
	private float $blockZ;

	/** Player position. */
	private float $playerX;
	private float $playerY;
	private float $playerZ;

	/** Eye position. */
	private float $eyeX;
	private float $eyeY;
	private float $eyeZ;

	/** Block state. */
	private int $blockId;
	private int $blockMeta;
	private float $blockHardness;

	/** Player state. */
	private int $ping;
	private bool $survival;
	private int $attackTicks;
	private int $teleportTicks;
	private bool $recentlyCancelled;

	/** Cached block data. */
	private mixed $cachedData = [];

	public function __construct(string $checkType, Player $player, PlayerAPI $playerAPI) {
		parent::__construct($checkType);

		$pos = $player->getLocation();
		$eyePos = $player->getEyePos();

		$this->playerX = $pos->getX();
		$this->playerY = $pos->getY();
		$this->playerZ = $pos->getZ();

		$this->eyeX = $eyePos->getX();
		$this->eyeY = $eyePos->getY();
		$this->eyeZ = $eyePos->getZ();

		$this->ping = $player->getNetworkSession()->getPing();
		$this->survival = $player->isSurvival();
		$this->attackTicks = $playerAPI->getAttackTicks();
		$this->teleportTicks = $playerAPI->getTeleportTicks();
		$this->recentlyCancelled = $playerAPI->isRecentlyCancelledEvent();
	}

	/**
	 * Set block position and state.
	 */
	public function setBlockState(float $x, float $y, float $z, int $id, int $meta, float $hardness) : self {
		$this->blockX = $x;
		$this->blockY = $y;
		$this->blockZ = $z;
		$this->blockId = $id;
		$this->blockMeta = $meta;
		$this->blockHardness = $hardness;
		return $this;
	}

	/**
	 * Add cached block interaction data.
	 */
	public function addCachedData(string $key, mixed $value) : self {
		$this->cachedData[$key] = $value;
		return $this;
	}

	public function build() : array {
		return [
			"type" => $this->checkType,
			"schemaVersion" => self::SCHEMA_VERSION,
			"captureTime" => $this->captureTime,
			"blockX" => $this->blockX,
			"blockY" => $this->blockY,
			"blockZ" => $this->blockZ,
			"playerX" => $this->playerX,
			"playerY" => $this->playerY,
			"playerZ" => $this->playerZ,
			"eyeX" => $this->eyeX,
			"eyeY" => $this->eyeY,
			"eyeZ" => $this->eyeZ,
			"blockId" => $this->blockId,
			"blockMeta" => $this->blockMeta,
			"blockHardness" => $this->blockHardness,
			"ping" => $this->ping,
			"survival" => $this->survival,
			"attackTicks" => $this->attackTicks,
			"teleportTicks" => $this->teleportTicks,
			"recentlyCancelled" => $this->recentlyCancelled,
			"cachedData" => $this->cachedData,
		];
	}

	public function validate() : void {
		if (!is_float($this->blockX) || !is_float($this->playerX)) {
			throw new SnapshotException("Invalid block position data in snapshot");
		}
	}
}
