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
use function is_bool;
use function is_float;

/**
 * Captures immutable combat state for async worker evaluation.
 *
 * Used by: Reach, Hitbox, GhostHand, ItemLerp, AutoClick, KillAura,
 * Rotation, Velocity, FastBow
 */
class CombatSnapshot extends AsyncSnapshot {
	public const SCHEMA_VERSION = 1;

	/** Damager state. */
	private float $damagerEyeX;
	private float $damagerEyeY;
	private float $damagerEyeZ;
	private int $damagerPing;
	private bool $damagerSprinting;
	private bool $damagerSurvival;

	/** Victim state. */
	private float $victimEyeX;
	private float $victimEyeY;
	private float $victimEyeZ;
	private int $victimPing;
	private bool $victimSprinting;
	private bool $victimSurvival;

	/** Action ticks. */
	private int $damagerAttackTicks;
	private int $damagerProjectileTicks;
	private int $damagerBowTicks;
	private int $victimAttackTicks;
	private int $victimProjectileTicks;
	private int $victimBowTicks;

	/** State flags. */
	private bool $damagerRecentlyCancelled;
	private bool $victimRecentlyCancelled;

	/** Cached combat data. */
	private mixed $cachedData = [];

	public function __construct(
		string $checkType,
		Player $damager,
		PlayerAPI $damagerAPI,
		Player $victim,
		PlayerAPI $victimAPI
	) {
		parent::__construct($checkType);

		$damagerEye = $damager->getEyePos();
		$victimEye = $victim->getEyePos();

		$this->damagerEyeX = $damagerEye->getX();
		$this->damagerEyeY = $damagerEye->getY();
		$this->damagerEyeZ = $damagerEye->getZ();
		$this->damagerPing = $damager->getNetworkSession()->getPing();
		$this->damagerSprinting = $damager->isSprinting();
		$this->damagerSurvival = $damager->isSurvival();

		$this->victimEyeX = $victimEye->getX();
		$this->victimEyeY = $victimEye->getY();
		$this->victimEyeZ = $victimEye->getZ();
		$this->victimPing = $victim->getNetworkSession()->getPing();
		$this->victimSprinting = $victim->isSprinting();
		$this->victimSurvival = $victim->isSurvival();

		$this->damagerAttackTicks = $damagerAPI->getAttackTicks();
		$this->damagerProjectileTicks = $damagerAPI->getProjectileAttackTicks();
		$this->damagerBowTicks = $damagerAPI->getBowShotTicks();
		$this->victimAttackTicks = $victimAPI->getAttackTicks();
		$this->victimProjectileTicks = $victimAPI->getProjectileAttackTicks();
		$this->victimBowTicks = $victimAPI->getBowShotTicks();

		$this->damagerRecentlyCancelled = $damagerAPI->isRecentlyCancelledEvent();
		$this->victimRecentlyCancelled = $victimAPI->isRecentlyCancelledEvent();
	}

	/**
	 * Add cached combat data (e.g., last hit distance, rotation deltas).
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
			"damagerEyeX" => $this->damagerEyeX,
			"damagerEyeY" => $this->damagerEyeY,
			"damagerEyeZ" => $this->damagerEyeZ,
			"damagerPing" => $this->damagerPing,
			"damagerSprinting" => $this->damagerSprinting,
			"damagerSurvival" => $this->damagerSurvival,
			"victimEyeX" => $this->victimEyeX,
			"victimEyeY" => $this->victimEyeY,
			"victimEyeZ" => $this->victimEyeZ,
			"victimPing" => $this->victimPing,
			"victimSprinting" => $this->victimSprinting,
			"victimSurvival" => $this->victimSurvival,
			"damagerAttackTicks" => $this->damagerAttackTicks,
			"damagerProjectileTicks" => $this->damagerProjectileTicks,
			"damagerBowTicks" => $this->damagerBowTicks,
			"victimAttackTicks" => $this->victimAttackTicks,
			"victimProjectileTicks" => $this->victimProjectileTicks,
			"victimBowTicks" => $this->victimBowTicks,
			"damagerRecentlyCancelled" => $this->damagerRecentlyCancelled,
			"victimRecentlyCancelled" => $this->victimRecentlyCancelled,
			"cachedData" => $this->cachedData,
		];
	}

	public function validate() : void {
		if (!is_float($this->damagerEyeX) || !is_float($this->victimEyeX)) {
			throw new SnapshotException("Invalid combat position data in snapshot");
		}
		if (!is_bool($this->damagerSurvival) || !is_bool($this->victimSurvival)) {
			throw new SnapshotException("Missing survival state in combat snapshot");
		}
	}
}
