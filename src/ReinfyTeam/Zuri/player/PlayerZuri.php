<?php

namespace ReinfyTeam\Zuri\player;

use JsonSerializable;
use ReinfyTeam\Zuri\check\Check;
use ReinfyTeam\Zuri\utils\Utils;
use pocketmine\player\Player;
use pocketmine\entity\Location;
use pocketmine\world\Position;
use pocketmine\math\Vector3;

class PlayerZuri extends Violation implements JsonSerializable, ExternalDataPath {

	public function __construct(
		private readonly Player $player
	) {
		$this->updateData($player);
	}

	private const DELTAL_TIME_CLICK = 1;

	private bool $actionBreakingSpecial = false;
	private bool $actionPlacingSpecial = false;
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
	private bool $onStairs = false;
	private bool $onIce = false;
	private bool $debug = false;
	private bool $onGround = false;
	private bool $topBlock = false;
	private bool $onWeb = false;
	private bool $inBoxBlock = false;
	private bool $inBoundingBox = false;
	private bool $isCurrentChunkLoaded = false;
	private bool $isSurvival = false;
	private bool $isCreative = false;
	private bool $isSpectator = false;
	private bool $flying = false;
	private bool $allowFlight = false;
	private bool $noClientPredictions = false;
	
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
	
	private int $blocksBrokeASec = 0;
	private int $blocksPlacedASec = 0;

	private Location $location;
	private Position $position;

	private array $movement;
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

	private array $externalData = [];

	/**
	 * Create a new instance class.
	 */
	public static function create(Player $player) : self {
		return new self($player);
	}

	/**
	 * Gather and update data from player.
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
	
	public function getMotion() : Vector3 {
		return $this->motion ??= Vector3::zero();
	}

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

	public function setRecentlyCancelledEvent(float $tick) : bool {
		return $this->eventCancelled = $tick;
	}

	public function isRecentlyCancelledEvent() : bool {
		if ($this->eventCancelled === 0 || abs($this->eventCancelled - microtime(true)) * 20 > 40) {
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
	
	public function isOnAdhesion() : bool {
		return $this->onAdhesion;
	}

	public function setOnAdhesion(bool $data) : void {
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

	public function setOnSnow(bool $data) : void {
		$this->onSnow = $data;
	}
	
	public function isSprinting() : bool {
		return $this->getPlayer()->isSprinting();
	}

	public function setSprinting(bool $data) : void {
		$this->getPlayer()->setSprinting($data);
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
		return count(array_filter($this->cpsData ?? [], static function(float $lastTime) use ($newTime) : bool {
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

	public function getMovement() : array {
		return $this->movement ??= ["from" => Vector3::zero(), "to" => Vector3::zero()];
	}

	public function setMovement(Vector3 $from, Vector3 $to) : void {
		$this->movement = ["from" => $from, "to" => $to];
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
		return $this->rawMove ??= Vector2::zero();
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

	public function getFlying() : bool {
		return $this->flying;
	}

	public function hasNoClientPredictions() : bool {
		return $this->noClientPredictions;
	}

	public function setNoClientPredictions(bool $noClientPredictions) : bool {
		$this->noClientPredictions = $noClientPredictions;
	}

	public function isSurvival() : bool {
		return $this->survival;
	}

	public function setSurvival(bool $survival) : bool {
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

	public function setExternalData(string $parameter, mixed $value) : void {
		$this->externalData[$parameter] = $value;
	}

	public function getExternalData(string $parameter) : mixed {
		return $this->externalData[$parameter] ?? null;
	}

	public function getAllExternalData() : array {
		return $this->externalData;
	}

	public function jsonSerialize() : array {
		return [
			"name" => $this->getPlayer()->getName(),
			"inventoryOpen" => $this->isInventoryOpen(),
			"transactionArmorInventory" => $this->isTransactionArmorInventory(),
			"underBlock" => $this->isUnderBlock(),
			"onAdhesion" => $this->isOnAdhesion(),
			"onPlant" => $this->isOnPlant(),
			"onDoor" => $this->isOnDoor(),
			"onCarpet" => $this->isOnCarpet(),
			"onPlate" => $this->isOnPlate(),
			"onSnow" => $this->isOnSnow(),
			"sniffing" => $this->isSniffing(),
			"inLiquid" => $this->isInLiquid(),
			"onStairs" => $this->isOnStairs(),
			"onIce" => $this->isOnIce(),
			"debug" => $this->isDebug(),
			"topBlock" => $this->isTopBlock(),
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
			"isCurrentChunkLoaded" => $this->isCurrentChunkLoaded(),
			"isSurvival" => $this->isSurvival(),
			"isCreative" => $this->isCreative(),
			"isSpectator" => $this->isSpectator(),
			"flying" => $this->isFlying()
			"allowFlight" => $this->getAllowFlight(),
			"noClientPredictions" => $this->hasNoClientPredictions(),
			"externalData" => $this->getAllExternalData()
		];
	}
}
