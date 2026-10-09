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

namespace ReinfyTeam\Zuri\player;

use JsonSerializable;
use pocketmine\entity\Location;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\Position;
use ReinfyTeam\Zuri\utils\Utils;
use ReinfyTeam\Zuri\ZuriAC;
use function abs;
use function array_filter;
use function count;
use function max;
use function microtime;

/**
 * Represents internal tracking data for a player used by ZuriAC checks.
 *
 * Stores movement, timing, and environmental state used by checks.
 *
 * @phpstan-type PlayerData array{
 *     isInventoryOpen:bool,
 *     isTransactionArmorInventory:bool,
 *     isUnderBlock:bool,
 *     isClimbing:bool,
 *     isOnPlant:bool,
 *     isOnDoor:bool,
 *     isOnCarpet:bool,
 *     isOnPlate:bool,
 *     isOnSnow:bool,
 *     isSniffing:bool,
 *     isLiquid:bool,
 *     isLava:bool,
 *     isOnStairs:bool,
 *     isIce:bool,
 *     isOnGround:bool,
 *     isDebug:bool,
 *     isTopBlock:bool,
 *     lastGroundY:float,
 *     lastNoGroundY:float,
 *     lastDelayedMovePacket:float,
 *     joinedAtTime:float,
 *     jumpTicks:float,
 *     teleportTicks:float,
 *     attackTicks:float,
 *     slimeBlockTicks:float,
 *     deathTicks:float,
 *     placingTicks:float,
 *     bowShotTicks:float,
 *     hurtTicks:float,
 *     projectileAttackTicks:float,
 *     lastMoveTick:float,
 *     teleportCommandTicks:float,
 *     cps:int,
 *     onlineTime:int,
 *     deltaTicks:float,
 *     verticalState:int,
 *     airTicks:int,
 *     groundTicks:int,
 *     lastY:float,
 *     predictedY:float,
 *     predictedVerticalDelta:float,
 *     verticalError:float,
 *     verticalVelocity:float,
 *     lastVerticalVelocity:float,
 *     hasInitialVelocity:bool,
 *     velocitySource:int,
 *     horizontalVelocitySource:int,
 *     externalVelocityTicks:int,
 *     pitch:float,
 *     isDead:bool,
 *     isCurrentChunkLoaded:bool,
 *     isSurvival:bool,
 *     isCreative:bool,
 *     isSpectator:bool,
 *     isFlying:bool,
 *     allowFlight:bool,
 *     hasNoClientPredictions:bool,
 *     isBlockAbove:bool,
 *     isRecentlyCancelledEvent:bool,
 *     isStartedJumping:bool,
 *     explosionTicks:float,
 *     isGroundSolid:bool,
 *     isSprinting:bool,
 *     isSneaking:bool,
 *     isGliding:bool,
 *     speedLevel:int,
 *     slownessLevel:int,
 *     jumpBoostLevel:int,
 *     isSoulSpeedSurface:bool,
 *     isUnderwater:bool,
 *     twoBlockPassage:bool,
 *     previousState:int,
 *     currentSurface:int,
 *     previousSurface:int,
 *     currentState:int,
 *     name:string,
 *     motion:array{x:float,y:float,z:float},
 *     movement:array{from:array{x:float,y:float,z:float},to:array{x:float,y:float,z:float}},
 *     previousPosition:array{x:float,y:float,z:float},
 *     safePosition:array{x:float,y:float,z:float},
 *     currentPosition:array{x:float,y:float,z:float},
 *     externalData:array<string,mixed>,
 * }
 */
class PlayerZuri extends Violation implements JsonSerializable, ExternalDataPath {
	public const STATE_GRACE = 0;
	public const STATE_GLIDING = 1;
	public const STATE_CREATIVE = 2;
	public const STATE_SPECTATOR = 3;
	public const STATE_SWIMMING = 4;
	public const STATE_CLIMBING = 5;
	public const STATE_ICE = 6;
	public const STATE_SOUL_SPEED = 7;
	public const STATE_SPRINT_JUMP = 8;
	public const STATE_SPRINT = 9;
	public const STATE_SNEAK = 10;
	public const STATE_WALK = 11;
	public const STATE_STAIRS = 12;
	public const STATE_LAVA = 13;
	public const VERTICAL_GROUND = 0;
	public const VERTICAL_JUMPING = 1;
	public const VERTICAL_RISING = 2;
	public const VERTICAL_APEX = 3;
	public const VERTICAL_FALLING = 4;
	public const VERTICAL_LANDING = 5;
	public const SOURCE_UNKNOWN = 0;
	public const SOURCE_JUMP = 1;
	public const SOURCE_EXTERNAL = 2;
	public const SOURCE_KNOCKBACK = 3;
	public const SOURCE_EXPLOSION = 4;
	public const SOURCE_PISTON = 5;
	public const SOURCE_PLUGIN = 6;
	public const SOURCE_CORRECTION = 7;
	public const SURFACE_UNKNOWN = 0;
	public const SURFACE_NORMAL = 1;
	public const SURFACE_ICE = 2;
	public const SURFACE_SOUL = 3;
	public const SURFACE_STAIRS = 4;
	public const SURFACE_WATER = 5;
	public const SURFACE_LAVA = 6;

	/**
	 * @param Player $player The underlying PocketMine player instance.
	 */
	public function __construct(
		private readonly Player $player
	) {
		$this->updateData($player);
	}

	private const DELTAL_TIME_CLICK = 1;

	private bool $inventoryOpen = false;
	private bool $transactionArmorInventory = false;
	private bool $underBlock = false;
	private bool $onAdhesion = false;
	private bool $onPlant = false;
	private bool $onDoor = false;
	private bool $onCarpet = false;
	private bool $onPlate = false;
	private bool $onSnow = false;
	private bool $sniffing = false;
	private bool $inLiquid = false;
	private bool $inLava = false;
	private bool $onStairs = false;
	private bool $onIce = false;
	private bool $debug = false;
	private bool $flagged = false;
	private bool $onGround = false;
	private bool $topBlock = false;
	private bool $onWeb = false;
	private bool $inBoxBlock = false;
	private bool $inBoundingBox = false;
	private bool $currentChunkLoaded = false;
	private bool $survival = false;
	private bool $creative = false;
	private bool $spectator = false;
	private bool $flying = false;
	private bool $allowFlight = false;
	private bool $blockAbove = false;
	private bool $noClientPredictions = false;
	private bool $startedJumping = false;
	private bool $groundSolid = false;
	private bool $sprinting = false;
	private bool $sneaking = false;
	private bool $gliding = false;
	private int $speedLevel = 0;
	private int $slownessLevel = 0;
	private int $jumpBoostLevel = 0;
	private bool $soulSpeedSurface = false;
	private bool $underwater = false;
	private bool $twoBlockPassage = false;
	private bool $dead = false;
	private int $verticalState = self::VERTICAL_GROUND;
	private int $previousState = self::STATE_GRACE;
	private int $currentSurface = self::SURFACE_UNKNOWN;
	private int $previousSurface = self::SURFACE_UNKNOWN;
	private int $airTicks = 0;
	private int $groundTicks = 0;
	private float $lastY = 0.0;
	private float $predictedY = 0.0;
	private float $predictedVerticalDelta = 0.0;
	private float $verticalError = 0.0;
	private float $verticalVelocity = 0.0;
	private float $lastVerticalVelocity = 0.0;
	private bool $hasInitialVelocity = false;
	private int $velocitySource = self::SOURCE_UNKNOWN;
	private int $horizontalVelocitySource = self::SOURCE_UNKNOWN;
	private int $externalVelocityTicks = 0;
	private Vector3 $previousPosition;
	private Vector3 $safePosition;
	private Vector3 $currentPosition;

	private float $lastGroundY = 0.0;
	private float $lastNoGroundY = 0.0;
	private float $lastDelayedMovePacket = 0.0;
	private float $joinedAtTime = 0.0;
	private float $jumpTicks = 0.0;
	private float $teleportTicks = 0.0;
	private float $attackTicks = 0.0;
	private float $slimeBlockTicks = 0.0;
	private float $deathTicks = 0.0;
	private float $placingTicks = 0.0;
	private float $bowShotTicks = 0.0;
	private float $hurtTicks = 0.0;
	private float $projectileAttackTicks = 0.0;
	private float $lastMoveTick = 0.0;
	private float $teleportCommandTicks = 0.0;
	private float $eventCancelled = 0.0;
	private float $explosionTicks = 0.0;
	private float $lastMovementTime = 0.0;
	private float $movementDeltaTicks = 1.0;


	private Location $location;
	private Position $position;

	/** @var array{from:Vector3,to:Vector3} */
	private array $movement;
	/** @var list<float> */
	private array $cpsData = [];

	private float $pitch = 0.0;
	private float $yaw = 0.0;
	private float $headYaw = 0.0;

	private int $inputMode = 0;
	private int $playMode = 0;
	private int $interactionMode = 0;
	private int $tick = 0;

	private Vector3 $delta;
	private Vector3 $motion;
	private Vector2 $rawMove;

	/**
	 * Factory: creates a new PlayerZuri for a Player instance.
	 */
	public static function create(Player $player) : self {
		return new self($player);
	}

	/**
	 * Gathers and updates internal state from the provided player.
	 */
	public function updateData(Player $player) : self {
		$this->setNoClientPredictions($player->hasNoClientPredictions());
		$this->setFlying($player->isFlying());
		$this->setCreative($player->isCreative());
		$this->setSurvival($player->isSurvival());
		$this->setSpectator($player->isSpectator());
		$this->setAllowFlight($player->getAllowFlight());

		return $this;
	}

	/**
	 * Returns the cached motion vector for the player.
	 */
	public function getMotion() : Vector3 {
		return $this->motion ??= Vector3::zero();
	}

	/**
	 * Set the current motion vector for the player.
	 */
	public function setMotion(Vector3 $motion) : void {
		$this->motion = $motion;
	}

	public function setHorizontalVelocitySource(int $source) : void {
		$this->horizontalVelocitySource = $source;
	}

	public function getHorizontalVelocitySource() : int {
		return $this->horizontalVelocitySource;
	}

	public function getExternalVelocityTicks() : int {
		return $this->externalVelocityTicks;
	}

	public function isInventoryOpen() : bool {
		return $this->inventoryOpen;
	}

	public function setInventoryOpen(bool $data) : void {
		$this->inventoryOpen = $data;
	}

	public function isTransactionArmorInventory() : bool {
		return $this->transactionArmorInventory;
	}

	public function setTransactionArmorInventory(bool $data) : void {
		$this->transactionArmorInventory = $data;
	}

	public function isUnderBlock() : bool {
		return $this->underBlock;
	}

	public function setUnderBlock(bool $data) : void {
		$this->underBlock = $data;
	}

	public function setRecentlyCancelledEvent(float $tick) : void {
		$this->eventCancelled = $tick;
	}

	public function isRecentlyCancelledEvent() : bool {
		if ($this->eventCancelled === 0.0 || abs($this->eventCancelled - microtime(true)) * 20 > 40) {
			$this->eventCancelled = 0;
			return false;
		}
		return true;
	}

	public function isTopBlock() : bool {
		return $this->topBlock;
	}

	public function setTopBlock(bool $data) : void {
		$this->topBlock = $data;
	}

	public function isClimbing() : bool {
		return $this->onAdhesion;
	}

	public function setClimbing(bool $data) : void {
		$this->onAdhesion = $data;
	}

	public function isOnPlant() : bool {
		return $this->onPlant;
	}

	public function setOnPlant(bool $data) : void {
		$this->onPlant = $data;
	}

	public function setLastMoveTick(float $data) : void {
		$this->lastMoveTick = $data;
	}

	public function getLastMoveTick() : float {
		return (microtime(true) - $this->lastMoveTick) * 20;
		;
	}

	public function setProjectileAttackTicks(float $data) : void {
		$this->projectileAttackTicks = $data;
	}

	public function getProjectileAttackTicks() : float {
		return (microtime(true) - $this->projectileAttackTicks) * 20;
	}


	public function setBowShotTicks(float $data) : void {
		$this->bowShotTicks = $data;
	}

	public function getBowShotTicks() : float {
		return (microtime(true) - $this->bowShotTicks) * 20;
	}

	public function setHurtTicks(float $data) : void {
		$this->hurtTicks = $data;
	}

	public function getTeleportCommandTicks() : float {
		return (microtime(true) - $this->teleportCommandTicks) * 20;
	}

	public function setTeleportCommandTicks(float $data) : void {
		$this->teleportCommandTicks = $data;
	}

	public function getHurtTicks() : float {
		return (microtime(true) - $this->hurtTicks) * 20;
	}

	public function isOnDoor() : bool {
		return $this->onDoor;
	}

	public function setOnDoor(bool $data) : void {
		$this->onDoor = $data;
	}

	public function isOnCarpet() : bool {
		return $this->onCarpet;
	}

	public function setOnCarpet(bool $data) : void {
		$this->onCarpet = $data;
	}

	public function isOnPlate() : bool {
		return $this->onPlate;
	}

	public function setOnPlate(bool $data) : void {
		$this->onPlate = $data;
	}

	public function isOnSnow() : bool {
		return $this->onSnow;
	}

	public function setSnow(bool $data) : void {
		$this->onSnow = $data;
	}

	public function isSprinting() : bool {
		return $this->sprinting;
	}

	public function setSprinting(bool $data) : void {
		$this->sprinting = $data;
	}

	public function isSneaking() : bool {
		return $this->sneaking;
	}

	public function setSneaking(bool $data) : void {
		$this->sneaking = $data;
	}

	public function isGliding() : bool {
		return $this->gliding;
	}

	public function setGliding(bool $data) : void {
		$this->gliding = $data;
	}

	public function isDead() : bool {
		return $this->dead;
	}

	public function setDead(bool $data) : void {
		$this->dead = $data;
	}

	public function setVerticalVelocity(float $velocity, int $source = self::SOURCE_UNKNOWN) : void {
		$this->verticalVelocity = $velocity;
		$this->hasInitialVelocity = true;
		$this->velocitySource = $source;
		$this->horizontalVelocitySource = $source;
		$this->externalVelocityTicks = $source === self::SOURCE_UNKNOWN ? 0 : 10;
	}

	public function updateVerticalMovement(
		float $fromY,
		float $toY,
		int $elapsedTicks,
		bool $wasOnGround,
		bool $isOnGround,
		bool $specialMovement,
		int $jumpBoostLevel = 0,
		bool $ceilingCollision = false
	) : void {
		$elapsedTicks = max(1, $elapsedTicks);
		$this->externalVelocityTicks = max(0, $this->externalVelocityTicks - $elapsedTicks);
		if ($this->externalVelocityTicks === 0) {
			$this->horizontalVelocitySource = self::SOURCE_UNKNOWN;
		}
		$this->lastY = $fromY;

		if ($specialMovement) {
			$this->verticalState = self::VERTICAL_GROUND;
			$this->airTicks = 0;
			$this->groundTicks++;
			$this->lastVerticalVelocity = $this->verticalVelocity;
			$this->verticalVelocity = 0.0;
			$this->velocitySource = self::SOURCE_UNKNOWN;
			$this->predictedVerticalDelta = $toY - $fromY;
			$this->predictedY = $toY;
			$this->verticalError = 0.0;
			return;
		}

		if ($ceilingCollision && $toY >= $fromY) {
			$this->verticalState = self::VERTICAL_APEX;
			$this->lastVerticalVelocity = $this->verticalVelocity;
			$this->verticalVelocity = 0.0;
			$this->hasInitialVelocity = false;
			$this->predictedVerticalDelta = $toY - $fromY;
			$this->predictedY = $toY;
			$this->verticalError = 0.0;
			return;
		}

		if ($isOnGround) {
			$this->verticalState = $wasOnGround ? self::VERTICAL_GROUND : self::VERTICAL_LANDING;
			$this->airTicks = 0;
			$this->groundTicks++;
			$this->lastVerticalVelocity = $this->verticalVelocity;
			$this->verticalVelocity = 0.0;
			$this->hasInitialVelocity = false;
			$this->velocitySource = self::SOURCE_UNKNOWN;
			$this->predictedVerticalDelta = 0.0;
			$this->predictedY = $fromY;
			$this->verticalError = $toY - $fromY;
			return;
		}

		$this->groundTicks = 0;
		$this->airTicks += $elapsedTicks;
		$initialVelocity = $this->verticalVelocity;
		if ($wasOnGround && $this->startedJumping) {
			$initialVelocity = 0.42 + (0.1 * max(0, $jumpBoostLevel));
			$this->verticalState = self::VERTICAL_JUMPING;
			$this->hasInitialVelocity = true;
			$this->velocitySource = self::SOURCE_JUMP;
		} else {
			$this->verticalState = $initialVelocity > 0.01
				? self::VERTICAL_RISING
				: ($initialVelocity < -0.01 ? self::VERTICAL_FALLING : self::VERTICAL_APEX);
		}

		$this->lastVerticalVelocity = $initialVelocity;
		$this->predictedVerticalDelta = \ReinfyTeam\Zuri\utils\MathUtil::verticalDisplacement($initialVelocity, $elapsedTicks);
		$this->predictedY = $fromY + $this->predictedVerticalDelta;
		$this->verticalError = ($toY - $fromY) - $this->predictedVerticalDelta;
		$this->verticalVelocity = \ReinfyTeam\Zuri\utils\MathUtil::verticalVelocityAfterTicks($initialVelocity, $elapsedTicks);
	}

	public function getVerticalState() : int {
		return $this->verticalState;
	}

	public function getAirTicks() : int {
		return $this->airTicks;
	}

	public function getGroundTicks() : int {
		return $this->groundTicks;
	}

	public function getLastY() : float {
		return $this->lastY;
	}

	public function getPredictedY() : float {
		return $this->predictedY;
	}

	public function getPredictedVerticalDelta() : float {
		return $this->predictedVerticalDelta;
	}

	public function getVerticalError() : float {
		return $this->verticalError;
	}

	public function getVerticalVelocity() : float {
		return $this->verticalVelocity;
	}

	public function getLastVerticalVelocity() : float {
		return $this->lastVerticalVelocity;
	}

	public function hasInitialVelocity() : bool {
		return $this->hasInitialVelocity;
	}

	public function getVelocitySource() : int {
		return $this->velocitySource;
	}

	public function setMovementDeltaTicks(float $ticks) : void {
		$this->movementDeltaTicks = max(1.0, $ticks);
	}

	public function getMovementDeltaTicks() : float {
		return $this->movementDeltaTicks;
	}

	public function getLastMovementTime() : float {
		return $this->lastMovementTime;
	}

	public function setLastMovementTime(float $time) : void {
		$this->lastMovementTime = $time;
	}

	public function getSpeedLevel() : int {
		return $this->speedLevel;
	}

	public function setSpeedLevel(int $data) : void {
		$this->speedLevel = $data;
	}

	public function getSlownessLevel() : int {
		return $this->slownessLevel;
	}

	public function setSlownessLevel(int $data) : void {
		$this->slownessLevel = $data;
	}

	public function getJumpBoostLevel() : int {
		return $this->jumpBoostLevel;
	}

	public function setJumpBoostLevel(int $data) : void {
		$this->jumpBoostLevel = $data;
	}

	public function isSoulSpeedSurface() : bool {
		return $this->soulSpeedSurface;
	}

	public function setSoulSpeedSurface(bool $data) : void {
		$this->soulSpeedSurface = $data;
	}

	public function isUnderwater() : bool {
		return $this->underwater;
	}

	public function setUnderwater(bool $data) : void {
		$this->underwater = $data;
	}

	public function hasTwoBlockPassage() : bool {
		return $this->twoBlockPassage;
	}

	public function setTwoBlockPassage(bool $data) : void {
		$this->twoBlockPassage = $data;
	}

	public function getPreviousState() : int {
		return $this->previousState;
	}

	public function setPreviousState(int $state) : void {
		$this->previousState = $state;
	}

	public function getCurrentSurface() : int {
		return $this->currentSurface;
	}

	public function setCurrentSurface(int $surface) : void {
		$this->previousSurface = $this->currentSurface;
		$this->currentSurface = $surface;
	}

	public function getPreviousSurface() : int {
		return $this->previousSurface;
	}

	public function synchronizePositions(Vector3 $position) : void {
		$this->previousPosition = $position;
		$this->safePosition = $position;
		$this->currentPosition = $position;
		$this->setMovement($position, $position);
		$this->lastY = $position->y;
		$this->predictedY = $position->y;
		$this->verticalError = 0.0;
		$this->verticalVelocity = 0.0;
		$this->lastVerticalVelocity = 0.0;
		$this->hasInitialVelocity = false;
		$this->velocitySource = self::SOURCE_UNKNOWN;
		$this->horizontalVelocitySource = self::SOURCE_UNKNOWN;
		$this->externalVelocityTicks = 0;
	}

	public function getPreviousPosition() : Vector3 {
		return $this->previousPosition ??= Vector3::zero();
	}

	public function getSafePosition() : Vector3 {
		return $this->safePosition ??= Vector3::zero();
	}

	public function getCurrentPosition() : Vector3 {
		return $this->currentPosition ??= Vector3::zero();
	}

	public function getCurrentState() : int {
		if ($this->getTeleportTicks() < 20 || $this->getOnlineTime() < 2 || $this->isDead() || $this->isRecentlyCancelledEvent()) {
			return self::STATE_GRACE;
		}
		if ($this->isGliding()) {
			return self::STATE_GLIDING;
		}
		if ($this->isSpectator()) {
			return self::STATE_SPECTATOR;
		}
		if ($this->isCreative() && $this->isFlying()) {
			return self::STATE_CREATIVE;
		}
		if ($this->isLava()) {
			return self::STATE_LAVA;
		}
		if ($this->isLiquid()) {
			return self::STATE_SWIMMING;
		}
		if ($this->isClimbing()) {
			return self::STATE_CLIMBING;
		}
		if ($this->isOnStairs()) {
			return self::STATE_STAIRS;
		}
		if ($this->isIce()) {
			return self::STATE_ICE;
		}
		if ($this->isSoulSpeedSurface()) {
			return self::STATE_SOUL_SPEED;
		}
		if ($this->isSprinting() && ($this->isStartedJumping() || !$this->isOnGround())) {
			return self::STATE_SPRINT_JUMP;
		}
		if ($this->isSprinting()) {
			return self::STATE_SPRINT;
		}
		if ($this->isSneaking()) {
			return self::STATE_SNEAK;
		}
		return self::STATE_WALK;
	}

	public function isOnGround() : bool {
		return $this->onGround;
	}

	public function setOnGround(bool $data) : void {
		$this->onGround = $data;
	}

	public function isSniffing() : bool {
		return $this->sniffing;
	}

	public function setSniffing(bool $data) : void {
		$this->sniffing = $data;
	}

	public function isLiquid() : bool {
		return $this->inLiquid;
	}

	public function setLiquid(bool $data) : void {
		$this->inLiquid = $data;
	}

	public function isLava() : bool {
		return $this->inLava;
	}

	public function setLava(bool $data) : void {
		$this->inLava = $data;
	}

	public function isOnStairs() : bool {
		return $this->onStairs;
	}

	public function setOnStairs(bool $data) : void {
		$this->onStairs = $data;
	}

	public function isIce() : bool {
		return $this->onIce;
	}

	public function setIce(bool $data) : void {
		$this->onIce = $data;
	}

	public function isOnWeb() : bool {
		return $this->onWeb;
	}

	public function setOnWeb(bool $data) : void {
		$this->onWeb = $data;
	}

	public function isInBoxBlock() : bool {
		return $this->inBoxBlock;
	}

	public function setInBoxBlock(bool $data) : void {
		$this->inBoxBlock = $data;
	}

	public function isInBoundingBox() : bool {
		return $this->inBoundingBox;
	}

	public function setInBoundingBox(bool $data) : void {
		$this->inBoundingBox = $data;
	}

	public function getLastGroundY() : float {
		return $this->lastGroundY;
	}

	public function setLastGroundY(float $data) : void {
		$this->lastGroundY = $data;
	}

	public function getLastNoGroundY() : float {
		return $this->lastNoGroundY;
	}

	public function setLastNoGroundY(float $data) : void {
		$this->lastNoGroundY = $data;
	}

	public function getLastDelayedMovePacket() : float {
		return $this->lastDelayedMovePacket;
	}

	public function setLastDelayedMovePacket(float $data) : void {
		$this->lastDelayedMovePacket = $data;
	}

	public function addCPS() : void {
		$this->cpsData[] = microtime(true);
	}

	public function getCPS() : int {
		$newTime = microtime(true);
		return count(array_filter($this->cpsData, static function(float $lastTime) use ($newTime) : bool {
			return ($newTime - $lastTime) <= self::DELTAL_TIME_CLICK;
		}));
	}

	public function getJoinedAtTheTime() : float {
		return $this->joinedAtTime;
	}

	public function setJoinedAtTheTime(float $data) : void {
		$this->joinedAtTime = $data;
	}

	public function getOnlineTime() : int {
		if ($this->joinedAtTime < 1) {
			return 0;
		}
		return (int) (microtime(true) - $this->joinedAtTime);
	}

	public function getTeleportTicks() : float {
		return (microtime(true) - $this->teleportTicks) * 20;
	}

	public function setTeleportTicks(float $data) : void {
		$this->teleportTicks = $data;
	}

	public function setJumpTicks(float $data) : void {
		$this->jumpTicks = $data;
	}

	public function getJumpTicks() : float {
		return (microtime(true) - $this->jumpTicks) * 20;
	}

	public function getPlacingTicks() : float {
		return (microtime(true) - $this->placingTicks) * 20;
	}

	public function setPlacingTicks(float $data) : void {
		$this->placingTicks = $data;
	}

	public function setDebug(bool $value = true) : void {
		$this->debug = $value;
	}

	public function setFlagged(bool $flagged) : void {
		$this->flagged = $flagged;
	}

	public function isFlagged() : bool {
		return $this->flagged;
	}

	public function isDebug() : bool {
		return $this->debug;
	}

	public function getYaw() : float {
		return $this->yaw;
	}

	public function setYaw(float $yaw) : void {
		$this->yaw = $yaw;
	}

	public function getPitch() : float {
		return $this->pitch;
	}

	public function setPitch(float $pitch) : void {
		$this->pitch = $pitch;
	}

	public function getHeadYaw() : float {
		return $this->headYaw;
	}

	public function setHeadYaw(float $headYaw) : void {
		$this->headYaw = $headYaw;
	}

	public function getPlayer() : Player {
		return $this->player;
	}

	public function getPosition() : Position {
		return $this->position;
	}

	public function setPosition(Position $position) : void {
		$this->position = $position;
	}

	public function getLocation() : Location {
		return $this->location;
	}

	public function setLocation(Location $location) : void {
		$this->location = $location;
	}

	public function getAttackTicks() : float {
		return (microtime(true) - $this->attackTicks) * 20;
	}

	public function setAttackTicks(float $data) : void {
		$this->attackTicks = $data;
	}

	public function getSlimeBlockTicks() : float {
		return (microtime(true) - $this->slimeBlockTicks) * 20;
	}

	public function setSlimeBlockTicks(float $data) : void {
		$this->slimeBlockTicks = $data;
	}

	public function getDeathTicks() : float {
		return (microtime(true) - $this->deathTicks) * 20;
	}

	public function setDeathTicks(float $data) : void {
		$this->deathTicks = $data;
	}

	/**
	 * Returns the player's last movement snapshot.
	 *
	 * @return array{from:Vector3,to:Vector3}
	 */
	public function getMovement() : array {
		return $this->movement ??= ["from" => Vector3::zero(), "to" => Vector3::zero()];
	}

	/**
	 * Set the player's movement snapshot.
	 *
	 * @param Vector3 $from Previous position vector.
	 * @param Vector3 $to Next position vector.
	 */
	public function setMovement(Vector3 $from, Vector3 $to) : void {
		$this->movement = ["from" => $from, "to" => $to];
	}

	public function synchronizeMovementPositions(Vector3 $from, Vector3 $to) : void {
		$this->previousPosition = $from;
		$this->currentPosition = $to;
		$safePosition = $this->getSafePosition();
		if (
			$this->getTeleportTicks() < 20 ||
			($safePosition->x === 0.0 && $safePosition->y === 0.0 && $safePosition->z === 0.0)
		) {
			$this->safePosition = $to;
		}
	}

	public function getInputMode() : int {
		return $this->inputMode;
	}

	public function setInputMode(int $inputMode) : void {
		$this->inputMode = $inputMode;
	}

	public function setPlayMode(int $playMode) : void {
		$this->playMode = $playMode;
	}

	public function getPlayMode() : int {
		return $this->playMode;
	}

	public function getInteractionMode() : int {
		return $this->interactionMode;
	}

	public function setInteractionMode(int $interactionMode) : void {
		$this->interactionMode = $interactionMode;
	}

	public function getTick() : int {
		return $this->tick;
	}

	public function setTick(int $tick) : void {
		$this->tick = $tick;
	}

	public function getDelta() : Vector3 {
		return $this->delta ??= Vector3::zero();
	}

	public function setDelta(Vector3 $delta) : void {
		$this->delta = $delta;
	}

	public function getRawMove() : Vector2 {
		return $this->rawMove ??= new Vector2(0.0, 0.0);
	}

	public function setRawMove(Vector2 $rawMove) : void {
		$this->rawMove = $rawMove;
	}

	public function setAllowFlight(bool $allowFlight) : void {
		$this->allowFlight = $allowFlight;
	}

	public function getAllowFlight() : bool {
		return $this->allowFlight;
	}

	public function setFlying(bool $flying) : void {
		$this->flying = $flying;
	}

	public function isFlying() : bool {
		return $this->flying;
	}

	public function hasNoClientPredictions() : bool {
		return $this->noClientPredictions;
	}

	public function setNoClientPredictions(bool $noClientPredictions) : void {
		$this->noClientPredictions = $noClientPredictions;
	}

	public function isSurvival() : bool {
		return $this->survival;
	}

	public function setSurvival(bool $survival) : void {
		$this->survival = $survival;
	}

	public function isCreative() : bool {
		return $this->creative;
	}

	public function setCreative(bool $creative) : void {
		$this->creative = $creative;
	}

	public function isSpectator() : bool {
		return $this->spectator;
	}

	public function setSpectator(bool $spectator) : void {
		$this->spectator = $spectator;
	}

	public function isCurrentChunkLoaded() : bool {
		return $this->currentChunkLoaded;
	}

	public function setCurrentChunkLoaded(bool $currentChunkLoaded) : void {
		$this->currentChunkLoaded = $currentChunkLoaded;
	}

	public function isBlockAbove() : bool {
		return $this->blockAbove;
	}

	public function setBlockAbove(bool $blockAbove) : void {
		$this->blockAbove = $blockAbove;
	}

	public function isStartedJumping() : bool {
		return $this->startedJumping;
	}

	public function setStartedJumping(bool $startedJumping) : void {
		$this->startedJumping = $startedJumping;
	}

	public function setExplosionTicks(float $explosionTick) : void {
		$this->explosionTicks = $explosionTick;
	}

	public function getExplosionTicks() : float {
		return (microtime(true) - $this->explosionTicks) * 20;
	}

	public function isGroundSolid() : bool {
		return $this->groundSolid;
	}

	public function setGroundSolid(bool $groundSolid) : void {
		$this->groundSolid = $groundSolid;
	}

	/**
	 * Serializes relevant player-tracking data for async checks.
	 *
	 * @return PlayerData
	 */
	public function jsonSerialize() : array {
		return [
			"name" => $this->getPlayer()->getName(),
			"isInventoryOpen" => $this->isInventoryOpen(),
			"isTransactionArmorInventory" => $this->isTransactionArmorInventory(),
			"isUnderBlock" => $this->isUnderBlock(),
			"isClimbing" => $this->isClimbing(),
			"isOnPlant" => $this->isOnPlant(),
			"isOnDoor" => $this->isOnDoor(),
			"isOnCarpet" => $this->isOnCarpet(),
			"isOnPlate" => $this->isOnPlate(),
			"isOnSnow" => $this->isOnSnow(),
			"isSniffing" => $this->isSniffing(),
			"isLiquid" => $this->isLiquid(),
			"isLava" => $this->isLava(),
			"isOnStairs" => $this->isOnStairs(),
			"isIce" => $this->isIce(),
			"isOnGround" => $this->isOnGround(),
			"isDebug" => $this->isDebug(),
			"isTopBlock" => $this->isTopBlock(),
			"lastGroundY" => $this->getLastGroundY(),
			"lastNoGroundY" => $this->getLastNoGroundY(),
			"lastDelayedMovePacket" => $this->getLastDelayedMovePacket(),
			"joinedAtTime" => $this->getJoinedAtTheTime(),
			"jumpTicks" => $this->getJumpTicks(),
			"teleportTicks" => $this->getTeleportTicks(),
			"attackTicks" => $this->getAttackTicks(),
			"slimeBlockTicks" => $this->getSlimeBlockTicks(),
			"deathTicks" => $this->getDeathTicks(),
			"placingTicks" => $this->getPlacingTicks(),
			"bowShotTicks" => $this->getBowShotTicks(),
			"hurtTicks" => $this->getHurtTicks(),
			"projectileAttackTicks" => $this->getProjectileAttackTicks(),
			"lastMoveTick" => $this->getLastMoveTick(),
			"teleportCommandTicks" => $this->getTeleportCommandTicks(),
			"cps" => $this->getCPS(),
			"motion" => Utils::vector3ToArray($this->getMotion()),
			"onlineTime" => $this->getOnlineTime(),
			"movement" => [
				"from" => Utils::vector3ToArray($this->getMovement()["from"]),
				"to" => Utils::vector3ToArray($this->getMovement()["to"])
			],
			"deltaTicks" => $this->getMovementDeltaTicks(),
			"verticalState" => $this->getVerticalState(),
			"airTicks" => $this->getAirTicks(),
			"groundTicks" => $this->getGroundTicks(),
			"lastY" => $this->getLastY(),
			"predictedY" => $this->getPredictedY(),
			"predictedVerticalDelta" => $this->getPredictedVerticalDelta(),
			"verticalError" => $this->getVerticalError(),
			"verticalVelocity" => $this->getVerticalVelocity(),
			"lastVerticalVelocity" => $this->getLastVerticalVelocity(),
			"hasInitialVelocity" => $this->hasInitialVelocity(),
			"velocitySource" => $this->getVelocitySource(),
			"horizontalVelocitySource" => $this->getHorizontalVelocitySource(),
			"externalVelocityTicks" => $this->getExternalVelocityTicks(),
			"pitch" => $this->getPitch(),
			"isDead" => $this->isDead(),
			"isCurrentChunkLoaded" => $this->isCurrentChunkLoaded(),
			"isSurvival" => $this->isSurvival(),
			"isCreative" => $this->isCreative(),
			"isSpectator" => $this->isSpectator(),
			"isFlying" => $this->isFlying(),
			"allowFlight" => $this->getAllowFlight(),
			"hasNoClientPredictions" => $this->hasNoClientPredictions(),
			"isBlockAbove" => $this->isBlockAbove(),
			"isRecentlyCancelledEvent" => $this->isRecentlyCancelledEvent(),
			"isStartedJumping" => $this->isStartedJumping(),
			"explosionTicks" => $this->getExplosionTicks(),
			"isGroundSolid" => $this->isGroundSolid(),
			"isSprinting" => $this->isSprinting(),
			"isSneaking" => $this->isSneaking(),
			"isGliding" => $this->isGliding(),
			"speedLevel" => $this->getSpeedLevel(),
			"slownessLevel" => $this->getSlownessLevel(),
			"jumpBoostLevel" => $this->getJumpBoostLevel(),
			"isSoulSpeedSurface" => $this->isSoulSpeedSurface(),
			"isUnderwater" => $this->isUnderwater(),
			"twoBlockPassage" => $this->hasTwoBlockPassage(),
			"previousState" => $this->getPreviousState(),
			"currentSurface" => $this->getCurrentSurface(),
			"previousSurface" => $this->getPreviousSurface(),
			"previousPosition" => Utils::vector3ToArray($this->getPreviousPosition()),
			"safePosition" => Utils::vector3ToArray($this->getSafePosition()),
			"currentPosition" => Utils::vector3ToArray($this->getCurrentPosition()),
			"currentState" => $this->getCurrentState(),
			"externalData" => ZuriAC::getExternalData()->getAllExternalData($this)
		];
	}
}
