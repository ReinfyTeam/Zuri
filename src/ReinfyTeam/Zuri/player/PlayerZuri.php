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
use pocketmine\Server;
use pocketmine\world\Position;
use ReinfyTeam\Zuri\config\ConfigPath;
use ReinfyTeam\Zuri\utils\BlockUtil;
use ReinfyTeam\Zuri\utils\Utils;
use ReinfyTeam\Zuri\utils\WorldUtil;
use ReinfyTeam\Zuri\ZuriAC;
use function abs;
use function array_filter;
use function count;
use function microtime;

class PlayerZuri extends Violation implements JsonSerializable, ExternalDataPath {
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
	private bool $jumping = false;
	private bool $groundSolid = false;
	private bool $sprinting = false;
	private bool $scaffolding = false;
	private bool $sniffing = false;
	private bool $sneaking = false;
	private bool $rawSneaking = false;
	private bool $gliding = false;
	private bool $crawling = false;
	private bool $swimming = false;
	private bool $onSoul = false;
	private bool $underwater = false;
	private bool $twoBlockPassage = false;
	private bool $dead = false;
	private bool $bypassPermission = false;

	private int $airTicks = 0;
	private int $groundTicks = 0;
	private int $lastMoveTicks = 0;

	// Input related things
	private int $inputMode = 0;
	private int $playMode = 0;
	private int $interactionMode = 0;

	// Raw movement
	private ?Vector2 $rawMovement = null;

	private float $lastGroundY = 0.0;
	private float $lastNoGroundY = 0.0;
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
	private float $teleportCommandTicks = 0.0;
	private float $eventCancelled = 0.0;
	private float $explosionTicks = 0.0;
	private float $dropTicks = 0.0;

	private Location $location;
	private Position $position;

	/** @var array{from:Vector3,to:Vector3} */
	private array $movement;
	/** @var list<float> */
	private array $cpsData = [];
	/** @var list<array{name: string, amplifier: int, duration: int}> */
	private array $effects = [];

	private float $pitch = 0.0;
	private float $yaw = 0.0;
	private float $headYaw = 0.0;
	private float $moveVecX = 0.0;
	private float $moveVecZ = 0.0;

	private int $tick = 0;

	private Vector3 $motion;
	private Vector3 $safePosition;


	/**
	 * Factory: creates a new PlayerZuri for a Player instance.
	 * @param Player $player The underlying PocketMine player instance.
	 * @return self A new PlayerZuri instance.
	 */
	public static function create(Player $player) : self {
		return new self($player);
	}

	/**
	 * Gathers and updates internal state from the provided player.
	 * @param Player $player The player instance to update data from.
	 * @return self Returns the current instance for method chaining.
	 */
	public function updateData(Player $player) : self {
		$this->setNoClientPredictions($player->hasNoClientPredictions());
		$this->setFlying($player->isFlying());
		$this->setCreative($player->isCreative());
		$this->setSurvival($player->isSurvival());
		$this->setSpectator($player->isSpectator());
		$this->setAllowFlight($player->getAllowFlight());
		$this->setCurrentChunkLoaded(WorldUtil::isCurrentChunkIsLoaded($player));
		$this->setNoClientPredictions($player->hasNoClientPredictions());
		$this->setSafePosition(BlockUtil::findSafePosition($player->getPosition()));
		$this->setEffects(Utils::effectsToArray($player->getEffects()));
		$this->setBypassPermission($player->hasPermission(ZuriAC::getConfigManager()->getString(ConfigPath::ZURI_BYPASS_PERMISSION)));
		return $this;
	}

	public function setBypassPermission(bool $data) : void {
		$this->bypassPermission = $data;
	}

	public function hasBypassPermission() : bool {
		return $this->bypassPermission;
	}

	/**
	 * Returns the array of effects.
	 * @return list<array{name: string, amplifier: int, duration: int}> Array of effects with name, amplifier, and duration.
	 */
	public function getEffects() : array {
		return $this->effects;
	}

	/**
	 * Sets the array of effects.
	 * Use {@link Utils::effectsToArray()} to convert a player's active effects manager into the structurally compliant format required here.
	 * @param list<array{name: string, amplifier: int, duration: int}> $effects Array of effects with name, amplifier, and duration.
	 */
	public function setEffects(array $effects) : void {
		$this->effects = $effects;
	}

	public function getMoveVecX() : float {
		return $this->moveVecX;
	}

	public function setMoveVecX(float $data) : void {
		$this->moveVecX = $data;
	}

	public function getMoveVecZ() : float {
		return $this->moveVecZ;
	}

	public function setMoveVecZ(float $data) : void {
		$this->moveVecZ = $data;
	}

	public function isOnScaffold() : bool {
		return $this->scaffolding;
	}

	public function setOnScaffold(bool $data) : void {
		$this->scaffolding = $data;
	}

	/**
	 * Returns the cached motion vector for the player.
	 */
	public function getMotion() : Vector3 {
		return $this->motion ??= Vector3::zero();
	}

	public function isInLava() : bool {
		return $this->inLava;
	}

	public function setInLava(bool $data) : void {
		$this->inLava = $data;
	}

	public function isCrawling() : bool {
		return $this->crawling;
	}

	public function setCrawling(bool $data) : void {
		$this->crawling = $data;
	}

	public function isSwimming() : bool {
		return $this->swimming;
	}

	public function setSwimming(bool $data) : void {
		$this->swimming = $data;
	}

	public function getInputMode() : int {
		return $this->inputMode;
	}

	public function setPlayMode(int $data) : void {
		$this->playMode = $data;
	}

	public function getPlayMode() : int {
		return $this->playMode;
	}

	public function setInputMode(int $mode = 0) : void {
		$this->inputMode = $mode;
	}

	public function setRawMove(?Vector2 $data) : void {
		$this->rawMovement = $data;
	}

	public function getRawMove() : ?Vector2 {
		return $this->rawMovement;
	}

	public function setInteractionMode(int $data = 0) : void {
		$this->interactionMode = $data;
	}

	public function getInteractMode() : int {
		return $this->interactionMode;
	}

	/**
	 * Set the current motion vector for the player.
	 */
	public function setMotion(Vector3 $motion) : void {
		$this->motion = $motion;
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

	public function hasTopBlock() : bool {
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

	public function setProjectileAttackTicks(float $data) : void {
		$this->projectileAttackTicks = $data;
	}

	public function getProjectileAttackTicks() : float {
		return (microtime(true) - $this->projectileAttackTicks) * 20;
	}

	public function setDropTicks(float $data) : void {
		$this->dropTicks = $data;
	}

	public function getDropTicks() : float {
		return (microtime(true) - $this->dropTicks) * 20;
	}

	public function setLastMoveTicks(int $data) : void {
		$this->lastMoveTicks = $data;
	}

	public function getLastMoveTicks() : int {
		return Server::getInstance()->getTick() - $this->lastMoveTicks;
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

	public function setOnSnow(bool $data) : void {
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

	public function isRawSneaking() : bool {
		return $this->rawSneaking;
	}

	public function setRawSneaking(bool $data) : void {
		$this->rawSneaking = $data;
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

	public function getAirTicks() : int {
		return $this->airTicks;
	}

	public function setAirTicks(int $airTicks) : void {
		$this->airTicks = $airTicks;
	}

	public function getGroundTicks() : int {
		return $this->groundTicks;
	}

	public function setGroundTicks(int $groundTicks) : void {
		$this->groundTicks = $groundTicks;
	}

	public function isOnSoul() : bool {
		return $this->onSoul;
	}

	public function setOnSoul(bool $data) : void {
		$this->onSoul = $data;
	}

	public function isOnUnderwater() : bool {
		return $this->underwater;
	}

	public function setOnUnderwater(bool $data) : void {
		$this->underwater = $data;
	}

	public function hasTwoBlockPassage() : bool {
		return $this->twoBlockPassage;
	}

	public function setTwoBlockPassage(bool $data) : void {
		$this->twoBlockPassage = $data;
	}

	public function getSafePosition() : Vector3 {
		return $this->safePosition ??= Vector3::zero();
	}

	public function setSafePosition(?Vector3 $data) : void {
		$this->safePosition = $data ?? Vector3::zero();
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

	public function isInLiquid() : bool {
		return $this->inLiquid;
	}

	public function setInLiquid(bool $data) : void {
		$this->inLiquid = $data;
	}

	public function isOnLava() : bool {
		return $this->inLava;
	}

	public function setOnLava(bool $data) : void {
		$this->inLava = $data;
	}

	public function isOnStairs() : bool {
		return $this->onStairs;
	}

	public function setOnStairs(bool $data) : void {
		$this->onStairs = $data;
	}

	public function isOnIce() : bool {
		return $this->onIce;
	}

	public function setOnIce(bool $data) : void {
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

	public function isRecentlyJumped() : bool {
		return $this->jumpTicks <= 5;
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


	public function getTick() : int {
		return $this->tick;
	}

	public function setTick(int $tick) : void {
		$this->tick = $tick;
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

	public function setExplosionTicks(float $explosionTick) : void {
		$this->explosionTicks = $explosionTick;
	}

	public function getExplosionTicks() : float {
		return (microtime(true) - $this->explosionTicks) * 20;
	}

	public function isJumping() : bool {
		return $this->jumping;
	}

	public function setJumping(bool $data) : void {
		$this->jumping = $data;
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
	 * @return array<string, mixed> An associative array of player data for serialization.
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
			"isInLiquid" => $this->isInLiquid(),
			"isOnLava" => $this->isOnLava(),
			"isOnStairs" => $this->isOnStairs(),
			"isOnIce" => $this->isOnIce(),
			"isOnGround" => $this->isOnGround(),
			"isDebug" => $this->isDebug(),
			"hasTopBlock" => $this->hasTopBlock(),
			"lastGroundY" => $this->getLastGroundY(),
			"lastNoGroundY" => $this->getLastNoGroundY(),
			"joinedAtTime" => $this->getJoinedAtTheTime(),
			"jumpTicks" => $this->getJumpTicks(),
			"isRecentlyJumped" => $this->isRecentlyJumped(),
			"teleportTicks" => $this->getTeleportTicks(),
			"attackTicks" => $this->getAttackTicks(),
			"slimeBlockTicks" => $this->getSlimeBlockTicks(),
			"deathTicks" => $this->getDeathTicks(),
			"placingTicks" => $this->getPlacingTicks(),
			"bowShotTicks" => $this->getBowShotTicks(),
			"hurtTicks" => $this->getHurtTicks(),
			"projectileAttackTicks" => $this->getProjectileAttackTicks(),
			"teleportCommandTicks" => $this->getTeleportCommandTicks(),
			"cps" => $this->getCPS(),
			"motion" => Utils::vector3ToArray($this->getMotion()),
			"onlineTime" => $this->getOnlineTime(),
			"movement" => [
				"from" => Utils::vector3ToArray($this->getMovement()["from"]),
				"to" => Utils::vector3ToArray($this->getMovement()["to"])
			],
			"airTicks" => $this->getAirTicks(),
			"groundTicks" => $this->getGroundTicks(),
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
			"isJumping" => $this->isJumping(),
			"explosionTicks" => $this->getExplosionTicks(),
			"isGroundSolid" => $this->isGroundSolid(),
			"isSprinting" => $this->isSprinting(),
			"isSneaking" => $this->isSneaking(),
			"isRawSneaking" => $this->isRawSneaking(),
			"isGliding" => $this->isGliding(),
			"isSwimming" => $this->isSwimming(),
			"effects" => $this->getEffects(),
			"isOnScaffold" => $this->isOnScaffold(),
			"lastMoveTicks" => $this->getLastMoveTicks(),
			"isOnSoul" => $this->isOnSoul(),
			"isOnUnderwater" => $this->isOnUnderwater(),
			"hasTwoBlockPassage" => $this->hasTwoBlockPassage(),
			"safePosition" => Utils::vector3ToArray($this->getSafePosition()),
		];
	}
}
