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
use function abs;
use function is_bool;
use function is_float;

/**
 * Captures immutable movement state for async worker evaluation.
 *
 * Used by: Fly, Speed, Step, Spider, Jesus, NoSlow, AirMovement,
 * AntiVoid, AntiImmobile, AirJump, WrongPitch, FakeGlide, OmniSprint
 */
class MovementSnapshot extends AsyncSnapshot {
	public const SCHEMA_VERSION = 1;

	/** Player position at capture time. */
	private float $posX;
	private float $posY;
	private float $posZ;

	/** Player eye position. */
	private float $eyeX;
	private float $eyeY;
	private float $eyeZ;

	/** Player motion. */
	private float $motionX;
	private float $motionY;
	private float $motionZ;

	/** Absolute motion values. */
	private float $absMotionX;
	private float $absMotionY;
	private float $absMotionZ;

	/** Player state flags. */
	private bool $onGround;
	private bool $onAdhesion;
	private bool $inWeb;
	private bool $gliding;
	private bool $sprinting;
	private bool $survival;

	/** Ticks since last action. */
	private int $jumpTicks;
	private int $attackTicks;
	private int $teleportTicks;
	private int $teleportCommandTicks;
	private int $hurtTicks;
	private int $onlineTime;

	/** Block/environment state. */
	private bool $groundSolid;
	private bool $chunkLoaded;
	private bool $recentlyCancelled;

	/** Network info. */
	private int $ping;

	/** Cached data results. */
	private mixed $cachedData = [];

	public function __construct(string $checkType, Player $player, PlayerAPI $playerAPI) {
		parent::__construct($checkType);

		$pos = $player->getLocation();
		$eyePos = $player->getEyePos();
		$motion = $playerAPI->getMotion();

		$this->posX = $pos->getX();
		$this->posY = $pos->getY();
		$this->posZ = $pos->getZ();

		$this->eyeX = $eyePos->getX();
		$this->eyeY = $eyePos->getY();
		$this->eyeZ = $eyePos->getZ();

		$this->motionX = $motion->getX();
		$this->motionY = $motion->getY();
		$this->motionZ = $motion->getZ();

		$this->absMotionX = abs($this->motionX);
		$this->absMotionY = abs($this->motionY);
		$this->absMotionZ = abs($this->motionZ);

		$this->onGround = $playerAPI->isOnGround();
		$this->onAdhesion = $playerAPI->isOnAdhesion();
		$this->inWeb = $playerAPI->isInWeb();
		$this->gliding = $playerAPI->isGliding();
		$this->sprinting = $player->isSprinting();
		$this->survival = $player->isSurvival();

		$this->jumpTicks = $playerAPI->getJumpTicks();
		$this->attackTicks = $playerAPI->getAttackTicks();
		$this->teleportTicks = $playerAPI->getTeleportTicks();
		$this->teleportCommandTicks = $playerAPI->getTeleportCommandTicks();
		$this->hurtTicks = $playerAPI->getHurtTicks();
		$this->onlineTime = $playerAPI->getOnlineTime();

		$this->ping = $player->getNetworkSession()->getPing();
	}

	/**
	 * Add a cached data field (e.g., last known Y position).
	 */
	public function addCachedData(string $key, mixed $value) : self {
		$this->cachedData[$key] = $value;
		return $this;
	}

	/**
	 * Set environment state (ground solid, chunk loaded, etc).
	 */
	public function setEnvironmentState(bool $groundSolid, bool $chunkLoaded, bool $recentlyCancelled) : self {
		$this->groundSolid = $groundSolid;
		$this->chunkLoaded = $chunkLoaded;
		$this->recentlyCancelled = $recentlyCancelled;
		return $this;
	}

	public function build() : array {
		return [
			"type" => $this->checkType,
			"schemaVersion" => self::SCHEMA_VERSION,
			"captureTime" => $this->captureTime,
			"posX" => $this->posX,
			"posY" => $this->posY,
			"posZ" => $this->posZ,
			"eyeX" => $this->eyeX,
			"eyeY" => $this->eyeY,
			"eyeZ" => $this->eyeZ,
			"motionX" => $this->motionX,
			"motionY" => $this->motionY,
			"motionZ" => $this->motionZ,
			"absMotionX" => $this->absMotionX,
			"absMotionY" => $this->absMotionY,
			"absMotionZ" => $this->absMotionZ,
			"onGround" => $this->onGround,
			"onAdhesion" => $this->onAdhesion,
			"inWeb" => $this->inWeb,
			"gliding" => $this->gliding,
			"sprinting" => $this->sprinting,
			"survival" => $this->survival,
			"jumpTicks" => $this->jumpTicks,
			"attackTicks" => $this->attackTicks,
			"teleportTicks" => $this->teleportTicks,
			"teleportCommandTicks" => $this->teleportCommandTicks,
			"hurtTicks" => $this->hurtTicks,
			"onlineTime" => $this->onlineTime,
			"ping" => $this->ping,
			"groundSolid" => $this->groundSolid,
			"chunkLoaded" => $this->chunkLoaded,
			"recentlyCancelled" => $this->recentlyCancelled,
			"cachedData" => $this->cachedData,
		];
	}

	public function validate() : void {
		// All fields are mandatory for movement checks
		if (!is_float($this->posX) || !is_float($this->posY) || !is_float($this->posZ)) {
			throw new SnapshotException("Invalid position data in movement snapshot");
		}
		if (!is_bool($this->survival)) {
			throw new SnapshotException("Missing survival state in movement snapshot");
		}
	}
}
