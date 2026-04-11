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

use pocketmine\block\BlockTypeIds;
use pocketmine\entity\Location;
use pocketmine\inventory\PlayerInventory;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\player\SurvivalBlockBreakHandler;
use ReflectionException;
use ReflectionProperty;
use ReinfyTeam\Zuri\utils\MathUtil;
use function count;
use function exp;
use function is_int;
use function is_numeric;
use function max;
use function microtime;
use function min;
use function spl_object_id;

/**
 * Mutable per-player anti-cheat state container and runtime timing accessor.
 *
 * @method bool isCaptcha()
 * @method void setCaptcha(bool $data)
 * @method bool isFlagged()
 * @method void setFlagged(bool $data)
 * @method bool actionBreakingSpecial()
 * @method void setActionBreakingSpecial(bool $data)
 * @method bool actionPlacingSpecial()
 * @method void setActionPlacingSpecial(bool $data)
 * @method bool isInventoryOpen()
 * @method void setInventoryOpen(bool $data)
 * @method bool isTransactionArmorInventory()
 * @method void setTransactionArmorInventory(bool $data)
 * @method bool isUnderBlock()
 * @method void setUnderBlock(bool $data)
 * @method bool isOnAdhesion()
 * @method void setOnAdhesion(bool $data)
 * @method bool isOnPlant()
 * @method void setOnPlant(bool $data)
 * @method bool isOnDoor()
 * @method void setOnDoor(bool $data)
 * @method bool isOnCarpet()
 * @method void setOnCarpet(bool $data)
 * @method bool isOnPlate()
 * @method void setOnPlate(bool $data)
 * @method bool isOnSnow()
 * @method void setOnSnow(bool $data)
 * @method bool isSprinting()
 * @method void setSprinting(bool $data)
 * @method bool isOnGround()
 * @method void setOnGround(bool $data)
 * @method bool isSniffing()
 * @method void setSniffing(bool $data)
 * @method bool isInLiquid()
 * @method void setInLiquid(bool $data)
 * @method bool isOnStairs()
 * @method void setOnStairs(bool $data)
 * @method bool isOnIce()
 * @method void setOnIce(bool $data)
 * @method bool isDigging()
 * @method bool isInWeb()
 * @method bool isInBoxBlock()
 * @method float getLastGroundY()
 * @method void setLastGroundY(float $data)
 * @method float getLastNoGroundY()
 * @method void setLastNoGroundY(float $data)
 * @method float getLastDelayedMovePacket()
 * @method void setLastDelayedMovePacket(float $data)
 * @method float getPing()
 * @method int getCPS()
 * @method void setCPS(int $data)
 * @method int getBlocksBrokeASec()
 * @method void setBlocksBrokeASec(int $data)
 * @method int getBlocksPlacedASec()
 * @method void setBlocksPlacedASec(int $data)
 * @method int getNumberBlocksAllowBreak()
 * @method void setNumberBlocksAllowBreak(int $data)
 * @method int getNumberBlocksAllowPlace()
 * @method void setNumberBlocksAllowPlace(int $data)
 * @method float getJoinedAtTheTime()
 * @method void setJoinedAtTheTime(float $data)
 * @method int getOnlineTime()
 * @method int getTeleportTicks()
 * @method void setTeleportTicks(float $data)
 * @method int getJumpTicks()
 * @method void setJumpTicks(float $data)
 * @method int getAttackTicks()
 * @method void setAttackTicks(float $data)
 * @method int getSlimeBlockTicks()
 * @method void setSlimeBlockTicks(float $data)
 * @method int getDeathTicks()
 * @method void setDeathTicks(float $data)
 * @method int getPlacingTicks()
 * @method void setPlacingTicks(float $data)
 * @method int getViolation(string $supplier)
 * @method void addViolation(string $supplier, int|float $amount = 1)
 * @method int getRealViolation(string $supplier)
 * @method void addRealViolation(string $supplier, int|float $amount = 1)
 * @method array<string, Location> getNLocation()
 * @method void setNLocation(Location $from, Location $to)
 * @method mixed getExternalData(string $dataName, mixed $default = null)
 * @method void setExternalData(string $dataName, mixed $amount)
 * @method void unsetExternalData(string $dataName)
 * @method string getCaptchaCode()
 * @method void setCaptchaCode(string $data)
 * @method void setDebug(bool $value = false)
 * @method bool isDebug()
 */
class PlayerAPI implements IPlayerAPI {
	/** @var PlayerAPI[] */
	public static array $players = [];

	/**
	 * Returns or creates the API wrapper associated with a live Player instance.
	 *
	 * @param Player $player Target player.
	 */
	public static function getAPIPlayer(Player $player) : PlayerAPI {
		return self::$players[spl_object_id($player)] ??= new PlayerAPI($player);
	}

	/**
	 * Removes cached API wrapper for a player leaving memory scope.
	 *
	 * @param Player $player Target player.
	 */
	public static function removeAPIPlayer(Player $player) : void {
		unset(self::$players[spl_object_id($player)]);
	}

	private Vector3 $motion;
	private bool $isCaptcha = false;
	private bool $flagged = false;
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
	private bool $onGround = false;
	private bool $onStairs = false;
	private bool $onIce = false;
	private bool $debug = false;
	private bool $topBlock = false;
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
	private float $lastLagSpike = 0.0;
	private float $lastWorldTransfer = 0.0;
	private int $cps = 0;
	private int $blocksBrokeASec = 0;
	private int $blocksPlacedASec = 0;
	private int $numberBlocksAllowBreak = 2; //2 is normal action
	private int $numberBlocksAllowPlace = 2; //2 is normal action
	/** @var array<string, list<float>> */
	private array $violations = [];
	/** @var array<string, list<float>> */
	private array $realViolations = [];
	/** @var array<string, int> */
	private array $asyncSequences = [];
	/** @var array<string, Location> */
	private array $nLocation = [];
	/** @var array<string, mixed> */
	private array $externalData = [];
	private string $captchaCode = "nocode";
	/** @var array<string, list<array{score: float, timestamp: float}>> */
	private array $confidenceScores = [];

	/**
	 * Creates a player API wrapper for the supplied player.
	 *
	 * @param Player $player Underlying player instance.
	 * @return void
	 */
	public function __construct(private readonly Player $player) {
		$this->onGround = $player->isOnGround();
	}

	/**
	 * Returns the underlying PocketMine player instance.
	 */
	public function getPlayer() : Player {
		return $this->player;
	}

	/**
	 * Returns whether captcha mode is currently enabled for the player.
	 */
	public function isCaptcha() : bool {
		return $this->isCaptcha;
	}

	/**
	 * Sets captcha mode state for the player.
	 *
	 * @param bool $data Captcha mode state.
	 */
	public function setCaptcha(bool $data) : void {
		$this->isCaptcha = $data;
	}

	/**
	 * Returns whether the player is flagged for event cancellation.
	 */
	public function isFlagged() : bool {
		return $this->flagged;
	}

	/**
	 * Sets the flagged state for the player.
	 *
	 * @param bool $data Flagged state.
	 */
	public function setFlagged(bool $data) : void {
		$this->flagged = $data;
	}

	/**
	 * Checks whether the player's current chunk is loaded.
	 */
	public function isCurrentChunkIsLoaded() : bool {
		return $this->getPlayer()->getWorld()->isInLoadedTerrain($this->getPlayer()->getLocation());
	}

	/**
	 * Checks whether the player is gliding based on state and motion heuristics.
	 */
	public function isGliding() : bool {
		$motion = $this->getPlayer()->getMotion();
		$isGliding = $this->getPlayer()->isGliding();
		$isFalling = $motion->y < 0; // Check player is falling when motion y falls down...
		$horizontalSpeed = MathUtil::distanceFromComponents(0.0, 0.0, 0.0, $motion->x, 0.0, $motion->z); // Check horizontal speed if it is on threshold

		if ($isFalling && $horizontalSpeed > 0.5 || $isGliding) { // Check flags if were accurate also...
			return true;
		}

		return false;
	}

	/**
	 * Returns the tracked aggregated motion vector.
	 */
	public function getMotion() : Vector3 {
		return $this->motion ??= Vector3::zero();
	}

	/**
	 * Updates the tracked aggregated motion vector.
	 *
	 * @param Vector3 $motion Motion vector.
	 */
	public function setMotion(Vector3 $motion) : void {
		$this->motion = $motion;
	}

	/**
	 * Returns whether the player is marked for special block-break behavior.
	 */
	public function actionBreakingSpecial() : bool {
		return $this->actionBreakingSpecial;
	}

	/**
	 * Sets the special block-break behavior marker.
	 *
	 * @param bool $data Marker state.
	 */
	public function setActionBreakingSpecial(bool $data) : void {
		$this->actionBreakingSpecial = $data;
	}

	/**
	 * Returns whether the player is marked for special block-place behavior.
	 */
	public function actionPlacingSpecial() : bool {
		return $this->actionPlacingSpecial;
	}

	/**
	 * Sets the special block-place behavior marker.
	 *
	 * @param bool $data Marker state.
	 */
	public function setActionPlacingSpecial(bool $data) : void {
		$this->actionPlacingSpecial = $data;
	}

	/**
	 * Returns whether the player's inventory is currently open.
	 */
	public function isInventoryOpen() : bool {
		return $this->inventoryOpen;
	}

	/**
	 * Sets whether the player's inventory is currently open.
	 *
	 * @param bool $data Inventory-open state.
	 */
	public function setInventoryOpen(bool $data) : void {
		$this->inventoryOpen = $data;
	}

	/**
	 * Returns whether an armor inventory transaction was detected.
	 */
	public function isTransactionArmorInventory() : bool {
		return $this->transactionArmorInventory;
	}

	/**
	 * Sets armor inventory transaction tracking state.
	 *
	 * @param bool $data Transaction state.
	 */
	public function setTransactionArmorInventory(bool $data) : void {
		$this->transactionArmorInventory = $data;
	}

	/**
	 * Returns whether the player is currently under a blocking structure.
	 */
	public function isUnderBlock() : bool {
		return $this->underBlock;
	}

	/**
	 * Sets whether the player is currently under a blocking structure.
	 *
	 * @param bool $data Under-block state.
	 */
	public function setUnderBlock(bool $data) : void {
		$this->underBlock = $data;
	}

	/**
	 * Stores the timestamp of a recently cancelled event.
	 *
	 * @param float $tick Event timestamp.
	 */
	public function setRecentlyCancelledEvent(float $tick) : void {
		$this->eventCancelled = $tick;
	}

	/**
	 * Checks whether a recently cancelled event marker is still active.
	 */
	public function isRecentlyCancelledEvent() : bool {
		if ($this->eventCancelled === 0.0) {
			return false;
		}
		if (!MathUtil::isRecent($this->eventCancelled, 40)) {
			$this->eventCancelled = 0;
			return false;
		}
		return true;
	}

	/**
	 * Returns whether a block exists directly above the player.
	 */
	public function isTopBlock() : bool {
		return $this->topBlock;
	}

	/**
	 * Sets whether a block exists directly above the player.
	 *
	 * @param bool $data Top-block state.
	 */
	public function setTopBlock(bool $data) : void {
		$this->topBlock = $data;
	}

	/**
	 * Returns whether the player is on an adhesion-type block.
	 */
	public function isOnAdhesion() : bool {
		return $this->onAdhesion;
	}

	/**
	 * Sets whether the player is on an adhesion-type block.
	 *
	 * @param bool $data Adhesion state.
	 */
	public function setOnAdhesion(bool $data) : void {
		$this->onAdhesion = $data;
	}

	/**
	 * Returns the detected device OS identifier from login metadata.
	 */
	public function getDeviceOS() : int {
		$deviceOS = $this->getPlayer()->getPlayerInfo()->getExtraData()["DeviceOS"] ?? 0;
		if (is_int($deviceOS)) {
			return $deviceOS;
		}
		if (is_numeric($deviceOS)) {
			return (int) $deviceOS;
		}
		return 0;
	}

	/**
	 * Returns whether the player is on a plant block.
	 */
	public function isOnPlant() : bool {
		return $this->onPlant;
	}

	/**
	 * Sets whether the player is on a plant block.
	 *
	 * @param bool $data Plant-contact state.
	 */
	public function setOnPlant(bool $data) : void {
		$this->onPlant = $data;
	}

	/**
	 * Stores the timestamp of the last movement update.
	 *
	 * @param float $data Movement timestamp.
	 */
	public function setLastMoveTick(float $data) : void {
		$this->lastMoveTick = $data;
	}

	/**
	 * Returns ticks elapsed since the last movement update.
	 */
	public function getLastMoveTick() : int {
		return MathUtil::ticksSince($this->lastMoveTick);
	}

	/**
	 * Stores the timestamp of the last projectile attack.
	 *
	 * @param float $data Projectile attack timestamp.
	 */
	public function setProjectileAttackTicks(float $data) : void {
		$this->projectileAttackTicks = $data;
	}

	/**
	 * Returns ticks elapsed since the last projectile attack.
	 */
	public function getProjectileAttackTicks() : int {
		return MathUtil::ticksSince($this->projectileAttackTicks);
	}


	/**
	 * Stores the timestamp of the last bow shot.
	 *
	 * @param float $data Bow shot timestamp.
	 */
	public function setBowShotTicks(float $data) : void {
		$this->bowShotTicks = $data;
	}

	/**
	 * Returns ticks elapsed since the last bow shot.
	 */
	public function getBowShotTicks() : int {
		return MathUtil::ticksSince($this->bowShotTicks);
	}

	/**
	 * Stores the timestamp of the last hurt event.
	 *
	 * @param float $data Hurt timestamp.
	 */
	public function setHurtTicks(float $data) : void {
		$this->hurtTicks = $data;
	}

	/**
	 * Returns ticks elapsed since the last teleport command marker.
	 */
	public function getTeleportCommandTicks() : int {
		return MathUtil::ticksSince($this->teleportCommandTicks);
	}

	/**
	 * Stores the timestamp of the last teleport command marker.
	 *
	 * @param float $data Teleport command timestamp.
	 */
	public function setTeleportCommandTicks(float $data) : void {
		$this->teleportCommandTicks = $data;
	}

	/**
	 * Returns ticks elapsed since the last hurt event.
	 */
	public function getHurtTicks() : int {
		return MathUtil::ticksSince($this->hurtTicks);
	}

	/**
	 * Returns whether the player is on a door block.
	 */
	public function isOnDoor() : bool {
		return $this->onDoor;
	}

	/**
	 * Sets whether the player is on a door block.
	 *
	 * @param bool $data Door-contact state.
	 */
	public function setOnDoor(bool $data) : void {
		$this->onDoor = $data;
	}

	/**
	 * Returns whether the player is on carpet.
	 */
	public function isOnCarpet() : bool {
		return $this->onCarpet;
	}

	/**
	 * Sets whether the player is on carpet.
	 *
	 * @param bool $data Carpet-contact state.
	 */
	public function setOnCarpet(bool $data) : void {
		$this->onCarpet = $data;
	}

	/**
	 * Returns whether the player is on a pressure plate.
	 */
	public function isOnPlate() : bool {
		return $this->onPlate;
	}

	/**
	 * Sets whether the player is on a pressure plate.
	 *
	 * @param bool $data Plate-contact state.
	 */
	public function setOnPlate(bool $data) : void {
		$this->onPlate = $data;
	}

	/**
	 * Returns whether the player is on snow.
	 */
	public function isOnSnow() : bool {
		return $this->onSnow;
	}

	/**
	 * Sets whether the player is on snow.
	 *
	 * @param bool $data Snow-contact state.
	 */
	public function setOnSnow(bool $data) : void {
		$this->onSnow = $data;
	}

	/**
	 * Returns whether the player is sprinting.
	 */
	public function isSprinting() : bool {
		return $this->getPlayer()->isSprinting();
	}

	/**
	 * Sets player sprinting state.
	 *
	 * @param bool $data Sprinting state.
	 */
	public function setSprinting(bool $data) : void {
		$this->getPlayer()->setSprinting($data);
	}

	/**
	 * Returns cached on-ground state for the player.
	 */
	public function isOnGround() : bool {
		return $this->onGround;
	}

	/**
	 * Sets cached on-ground state for the player.
	 *
	 * @param bool $data On-ground state.
	 */
	public function setOnGround(bool $data) : void {
		$this->onGround = $data;
	}

	/**
	 * Returns whether sniffing state is active.
	 */
	public function isSniffing() : bool {
		return $this->sniffing;
	}

	/**
	 * Sets sniffing state.
	 *
	 * @param bool $data Sniffing state.
	 */
	public function setSniffing(bool $data) : void {
		$this->sniffing = $data;
	}

	/**
	 * Returns whether the player is currently in liquid.
	 */
	public function isInLiquid() : bool {
		return $this->inLiquid;
	}

	/**
	 * Sets whether the player is currently in liquid.
	 *
	 * @param bool $data Liquid state.
	 */
	public function setInLiquid(bool $data) : void {
		$this->inLiquid = $data;
	}

	/**
	 * Returns whether the player is currently on stairs.
	 */
	public function isOnStairs() : bool {
		return $this->onStairs;
	}

	/**
	 * Sets whether the player is currently on stairs.
	 *
	 * @param bool $data Stairs state.
	 */
	public function setOnStairs(bool $data) : void {
		$this->onStairs = $data;
	}

	/**
	 * Returns whether the player is currently on ice.
	 */
	public function isOnIce() : bool {
		return $this->onIce;
	}

	/**
	 * Sets whether the player is currently on ice.
	 *
	 * @param bool $data Ice state.
	 */
	public function setOnIce(bool $data) : void {
		$this->onIce = $data;
	}

	//Digging

	/**
	 * Returns whether the player is currently digging.
	 *
	 * @throws ReflectionException
	 */
	public function isDigging() : bool {
		if ($this->getBlockBreakHandler() !== null) {
			return true;
		}
		return false;
	}

	/**
	 * Resolves the internal block-break handler from player state.
	 *
	 * @throws ReflectionException
	 */
	private function getBlockBreakHandler() : ?SurvivalBlockBreakHandler {
		static $ref = null;
		if ($ref === null) {
			$ref = new ReflectionProperty($this->getPlayer(), "blockBreakHandler");
		}
		return $ref->getValue($this->getPlayer());
	}

	/**
	 * Checks whether the player is inside cobweb blocks.
	 */
	public function isInWeb() : bool {
		$world = $this->getPlayer()->getWorld();
		$location = $this->getPlayer()->getLocation();
		$blocksAround = [
			$world->getBlock($location),
			$world->getBlock($location->add(0, 1, 0)),
			$world->getBlock($location->add(0, 2, 0)),
			$world->getBlock($location->subtract(0, 1, 0)),
			$world->getBlock($location->subtract(0, 2, 0))
		];
		foreach ($blocksAround as $block) {
			if ($block->getTypeId() === BlockTypeIds::COBWEB) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Checks whether the player is boxed by nearby non-air blocks.
	 */
	public function isInBoxBlock() : bool {
		$world = $this->getPlayer()->getWorld();
		$location = $this->getPlayer()->getLocation();
		$blocksAround = [
			$world->getBlock($location->getSide(Facing::NORTH)->add(0, 1, 0)),
			$world->getBlock($location->getSide(Facing::WEST)->add(0, 1, 0)),
			$world->getBlock($location->getSide(Facing::EAST)->add(0, 1, 0))
		];
		foreach ($blocksAround as $block) {
			if ($block->getTypeId() !== BlockTypeIds::AIR) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Checks whether solid blocks are intersecting the player's surrounding box.
	 */
	public function isInBoundingBox() : bool {
		$player = $this->getPlayer();
		$pos = $player->getPosition();
		foreach ([
			$player->getWorld()->getBlock(new Vector3($pos->x + 1, $pos->y, $pos->z)),
			$player->getWorld()->getBlock(new Vector3($pos->x - 1, $pos->y, $pos->z)),
			$player->getWorld()->getBlock(new Vector3($pos->x, $pos->y, $pos->z + 1)),
			$player->getWorld()->getBlock(new Vector3($pos->x, $pos->y, $pos->z - 1)),
			$player->getWorld()->getBlock(new Vector3($pos->x, $pos->y + 1, $pos->z)),
		] as $block) {
			if ($block->isSolid()) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns the last known ground Y position.
	 */
	public function getLastGroundY() : float {
		return $this->lastGroundY;
	}

	/**
	 * Sets the last known ground Y position.
	 *
	 * @param float $data Ground Y position.
	 */
	public function setLastGroundY(float $data) : void {
		$this->lastGroundY = $data;
	}

	/**
	 * Returns the last known non-ground Y position.
	 */
	public function getLastNoGroundY() : float {
		return $this->lastNoGroundY;
	}

	/**
	 * Sets the last known non-ground Y position.
	 *
	 * @param float $data Non-ground Y position.
	 */
	public function setlastNoGroundY(float $data) : void {
		$this->lastNoGroundY = $data;
	}

	/**
	 * Returns the last delayed move packet timestamp.
	 */
	public function getLastDelayedMovePacket() : float {
		return $this->lastDelayedMovePacket;
	}

	/**
	 * Sets the last delayed move packet timestamp.
	 *
	 * @param float $data Move packet timestamp.
	 */
	public function setLastDelayedMovePacket(float $data) : void {
		$this->lastDelayedMovePacket = $data;
	}

	/**
	 * Returns the player's current ping, or zero when unavailable.
	 */
	public function getPing() : float {
		if (!$this->getPlayer()->isConnected() || !$this->getPlayer()->isOnline()) {
			return 0.0;
		} // always check first if player is currently connected before initilizing the main ping. This fixes the player if it is currently connected and ping has been initilized as well.

		return $this->getPlayer()->getNetworkSession()->getPing() === null ? 0.0 : $this->getPlayer()->getNetworkSession()->getPing(); // TODO: 0.0 frrr ping?
	}

	/**
	 * Returns the tracked clicks-per-second value.
	 */
	public function getCPS() : int {
		return $this->cps;
	}

	/**
	 * Sets the tracked clicks-per-second value.
	 *
	 * @param int $data CPS value.
	 */
	public function setCPS(int $data) : void {
		$this->cps = $data;
	}

	/**
	 * Returns blocks broken in the current one-second tracking window.
	 */
	public function getBlocksBrokeASec() : int {
		return $this->blocksBrokeASec;
	}

	/**
	 * Sets blocks broken in the current one-second tracking window.
	 *
	 * @param int $data Block break count.
	 */
	public function setBlocksBrokeASec(int $data) : void {
		$this->blocksBrokeASec = $data;
	}

	/**
	 * Returns blocks placed in the current one-second tracking window.
	 */
	public function getBlocksPlacedASec() : int {
		return $this->blocksPlacedASec;
	}

	/**
	 * Sets blocks placed in the current one-second tracking window.
	 *
	 * @param int $data Block place count.
	 */
	public function setBlocksPlacedASec(int $data) : void {
		$this->blocksPlacedASec = $data;
	}

	/**
	 * Returns the per-second allowed block-break baseline.
	 */
	public function getNumberBlocksAllowBreak() : int {
		return $this->numberBlocksAllowBreak;
	}

	/**
	 * Sets the per-second allowed block-break baseline.
	 *
	 * @param int $data Allowed block-break count.
	 */
	public function setNumberBlocksAllowBreak(int $data) : void {
		$this->numberBlocksAllowBreak = $data;
	}

	/**
	 * Returns the per-second allowed block-place baseline.
	 */
	public function getNumberBlocksAllowPlace() : int {
		return $this->numberBlocksAllowPlace;
	}

	/**
	 * Sets the per-second allowed block-place baseline.
	 *
	 * @param int $data Allowed block-place count.
	 */
	public function setNumberBlocksAllowPlace(int $data) : void {
		$this->numberBlocksAllowPlace = $data;
	}

	/**
	 * Returns the join timestamp used for uptime calculations.
	 */
	public function getJoinedAtTheTime() : float {
		return $this->joinedAtTime;
	}

	/**
	 * Sets the join timestamp used for uptime calculations.
	 *
	 * @param float $data Join timestamp.
	 */
	public function setJoinedAtTheTime(float $data) : void {
		$this->joinedAtTime = $data;
	}

	/**
	 * Returns player online duration in seconds.
	 */
	public function getOnlineTime() : int {
		if ($this->joinedAtTime < 1) {
			return 0;
		}
		return (int) (microtime(true) - $this->joinedAtTime);
	}

	/**
	 * Returns ticks elapsed since last teleport marker.
	 */
	public function getTeleportTicks() : int {
		return MathUtil::ticksSince($this->teleportTicks);
	}

	/**
	 * Sets the teleport marker timestamp.
	 *
	 * @param float $data Teleport timestamp.
	 */
	public function setTeleportTicks(float $data) : void {
		$this->teleportTicks = $data;
	}

	/**
	 * Returns ticks elapsed since last jump marker.
	 */
	public function getJumpTicks() : int {
		return MathUtil::ticksSince($this->jumpTicks);
	}

	/**
	 * Sets the jump marker timestamp.
	 *
	 * @param float $data Jump timestamp.
	 */
	public function setJumpTicks(float $data) : void {
		$this->jumpTicks = $data;
	}

	/**
	 * Returns ticks elapsed since last attack marker.
	 */
	public function getAttackTicks() : int {
		return MathUtil::ticksSince($this->attackTicks);
	}

	/**
	 * Sets the attack marker timestamp.
	 *
	 * @param float $data Attack timestamp.
	 */
	public function setAttackTicks(float $data) : void {
		$this->attackTicks = $data;
	}

	/**
	 * Returns ticks elapsed since last slime-block contact marker.
	 */
	public function getSlimeBlockTicks() : int {
		return MathUtil::ticksSince($this->slimeBlockTicks);
	}

	/**
	 * Sets the slime-block contact marker timestamp.
	 *
	 * @param float $data Slime-block timestamp.
	 */
	public function setSlimeBlockTicks(float $data) : void {
		$this->slimeBlockTicks = $data;
	}

	/**
	 * Returns ticks elapsed since last death marker.
	 */
	public function getDeathTicks() : int {
		return MathUtil::ticksSince($this->deathTicks);
	}

	/**
	 * Sets the death marker timestamp.
	 *
	 * @param float $data Death timestamp.
	 */
	public function setDeathTicks(float $data) : void {
		$this->deathTicks = $data;
	}

	/**
	 * Returns ticks elapsed since last block-place marker.
	 */
	public function getPlacingTicks() : int {
		return MathUtil::ticksSince($this->placingTicks);
	}

	/**
	 * Sets the block-place marker timestamp.
	 *
	 * @param float $data Block-place timestamp.
	 */
	public function setPlacingTicks(float $data) : void {
		$this->placingTicks = $data;
	}

	/**
	 * Returns the active violation count for a supplier.
	 *
	 * @param string $supplier Check supplier key.
	 */
	public function getViolation(string $supplier) : int {
		if (isset($this->violations[$supplier])) {
			return count($this->violations[$supplier]);
		}
		return 0;
	}

	/**
	 * Resets active violations for a supplier.
	 *
	 * @param string $supplier Check supplier key.
	 */
	public function resetViolation(string $supplier) : void {
		if (isset($this->violations[$supplier])) {
			unset($this->violations[$supplier]);
		}
	}

	/**
	 * Adds active violation entries for a supplier.
	 *
	 * @param string $supplier Check supplier key.
	 * @param int|float $amount Violation amount hint.
	 */
	public function addViolation(string $supplier, int|float $amount = 1) : void {
		if (isset($this->violations[$supplier])) {
			foreach ($this->violations[$supplier] as $index => $time) {
				if (!MathUtil::isRecent($time, 40)) {
					unset($this->violations[$supplier][$index]);
				}
			}
		}

		$this->violations[$supplier][] = microtime(true);
	}

	/**
	 * Returns the real-violation count for a supplier.
	 *
	 * @param string $supplier Check supplier key.
	 */
	public function getRealViolation(string $supplier) : int {
		if (isset($this->realViolations[$supplier])) {
			return count($this->realViolations[$supplier]);
		}
		return 0;
	}

	/**
	 * Resets real violations for a supplier.
	 *
	 * @param string $supplier Check supplier key.
	 */
	public function resetRealViolation(string $supplier) : void {
		if (isset($this->realViolations[$supplier])) {
			unset($this->realViolations[$supplier]);
		}
	}

	/**
	 * Adds a real-violation entry for a supplier.
	 *
	 * @param string $supplier Check supplier key.
	 * @param int|float $amount Violation amount hint.
	 */
	public function addRealViolation(string $supplier, int|float $amount = 1) : void {
		if (isset($this->realViolations[$supplier])) {
			foreach ($this->realViolations[$supplier] as $index => $time) {
				if (!MathUtil::isRecent($time, 300)) {
					unset($this->realViolations[$supplier][$index]);
				}
			}
		}

		$this->realViolations[$supplier][] = microtime(true);
	}

	//Confidence scoring
	/**
	 * Get the accumulated confidence score for a check.
	 * Uses exponential decay: older scores contribute less.
	 *
	 * @param string $supplier Check supplier key.
	 */
	public function getConfidenceScore(string $supplier) : float {
		if (!isset($this->confidenceScores[$supplier]) || $this->confidenceScores[$supplier] === []) {
			return 0.0;
		}

		$now = microtime(true);
		$decayRate = 0.1; // Decay over ~10 seconds
		$totalScore = 0.0;

		foreach ($this->confidenceScores[$supplier] as $index => $entry) {
			$age = $now - $entry['timestamp'];
			if ($age > 30.0) { // Expire after 30 seconds
				unset($this->confidenceScores[$supplier][$index]);
				continue;
			}
			$decayedScore = $entry['score'] * exp(-$decayRate * $age);
			$totalScore += $decayedScore;
		}

		return min(1.0, $totalScore);
	}

	/**
	 * Add a confidence score for a check.
	 *
	 * @param string $supplier Check supplier key.
	 * @param float $score Confidence score to add.
	 */
	public function addConfidenceScore(string $supplier, float $score) : void {
		if (!isset($this->confidenceScores[$supplier])) {
			$this->confidenceScores[$supplier] = [];
		}

		// Clean old entries
		$now = microtime(true);
		foreach ($this->confidenceScores[$supplier] as $index => $entry) {
			if (($now - $entry['timestamp']) > 30.0) {
				unset($this->confidenceScores[$supplier][$index]);
			}
		}

		$this->confidenceScores[$supplier][] = [
			'score' => max(0.0, min(1.0, $score)),
			'timestamp' => $now,
		];
	}

	/**
	 * Reset confidence scores for a check.
	 *
	 * @param string $supplier Check supplier key.
	 */
	public function resetConfidenceScore(string $supplier) : void {
		if (isset($this->confidenceScores[$supplier])) {
			unset($this->confidenceScores[$supplier]);
		}
	}

	/**
	 * Get all confidence scores (for debugging/telemetry).
	 *
	 * @return array<string, float>
	 */
	public function getAllConfidenceScores() : array {
		$scores = [];
		foreach ($this->confidenceScores as $supplier => $_) {
			$scores[$supplier] = $this->getConfidenceScore($supplier);
		}
		return $scores;
	}

	/**
	 * Returns the current async sequence number for a supplier.
	 *
	 * @param string $supplier Check supplier key.
	 */
	public function getAsyncSequence(string $supplier) : int {
		return $this->asyncSequences[$supplier] ?? 0;
	}

	/**
	 * Increments and returns async sequence number for a supplier.
	 *
	 * @param string $supplier Check supplier key.
	 */
	public function nextAsyncSequence(string $supplier) : int {
		return $this->asyncSequences[$supplier] = $this->getAsyncSequence($supplier) + 1;
	}

	/**
	 * Checks whether a sequence matches the current supplier sequence.
	 *
	 * @param string $supplier Check supplier key.
	 * @param int $sequence Sequence number to verify.
	 */
	public function isAsyncSequenceCurrent(string $supplier, int $sequence) : bool {
		return $this->getAsyncSequence($supplier) === $sequence;
	}

	/**
	 * Resets async sequence for a single supplier.
	 *
	 * @param string $supplier Check supplier key.
	 */
	public function resetAsyncSequence(string $supplier) : void {
		if (isset($this->asyncSequences[$supplier])) {
			unset($this->asyncSequences[$supplier]);
		}
	}

	/**
	 * Resets async sequences for all suppliers.
	 */
	public function resetAsyncSequences() : void {
		$this->asyncSequences = [];
	}

	/**
	 * Returns the last tracked movement location pair.
	 *
	 * @return array<string, Location>
	 */
	public function getNLocation() : array {
		return $this->nLocation;
	}

	/**
	 * Updates the tracked movement location pair.
	 *
	 * @param Location $from From location.
	 * @param Location $to To location.
	 */
	public function setNLocation(Location $from, Location $to) : void {
		$this->nLocation = ["from" => $from, "to" => $to];
	}

	/**
	 * Returns externally stored player data by key.
	 *
	 * @param string $dataName Data key.
	 * @param mixed $default Default value when key is missing.
	 */
	public function getExternalData(string $dataName, mixed $default = null) : mixed {
		if (isset($this->externalData[$dataName])) {
			return $this->externalData[$dataName];
		}
		return $default;
	}

	/**
	 * Stores externally managed player data.
	 *
	 * @param string $dataName Data key.
	 * @param mixed $value Value to store.
	 */
	public function setExternalData(string $dataName, mixed $value) : void {
		$this->externalData[$dataName] = $value;
	}

	/**
	 * Removes externally managed player data by key.
	 *
	 * @param string $dataName Data key.
	 */
	public function unsetExternalData(string $dataName) : void {
		if (isset($this->externalData[$dataName])) {
			unset($this->externalData[$dataName]);
		}
	}

	/**
	 * Returns the player's current captcha code.
	 */
	public function getCaptchaCode() : string {
		return $this->captchaCode;
	}

	/**
	 * Sets the player's current captcha code.
	 *
	 * @param string $data Captcha code.
	 */
	public function setCaptchaCode(string $data) : void {
		$this->captchaCode = $data;
	}

	/**
	 * Returns the player's inventory instance.
	 */
	public function getInventory() : PlayerInventory {
		return $this->getPlayer()->getInventory();
	}

	/**
	 * Returns the player's current location.
	 */
	public function getLocation() : Location {
		return $this->getPlayer()->getLocation();
	}

	/**
	 * Enables or disables per-player debug mode.
	 *
	 * @param bool $value Debug mode state.
	 */
	public function setDebug(bool $value = true) : void {
		$this->debug = $value;
	}

	/**
	 * Returns whether per-player debug mode is enabled.
	 */
	public function isDebug() : bool {
		return $this->debug;
	}

	/**
	 * Stores timestamp of the last lag spike marker.
	 *
	 * @param float $time Lag spike timestamp.
	 */
	public function setLastLagSpike(float $time) : void {
		$this->lastLagSpike = $time;
	}

	/**
	 * Returns timestamp of the last lag spike marker.
	 */
	public function getLastLagSpike() : float {
		return $this->lastLagSpike;
	}

	/**
	 * Returns whether lag cooldown is currently active.
	 *
	 * @param float $cooldownSeconds Cooldown duration in seconds.
	 */
	public function isInLagCooldown(float $cooldownSeconds = 3.0) : bool {
		if ($this->lastLagSpike < 1.0) {
			return false;
		}
		return (microtime(true) - $this->lastLagSpike) < $cooldownSeconds;
	}

	/**
	 * Stores timestamp of the last world-transfer marker.
	 *
	 * @param float $time World-transfer timestamp.
	 */
	public function setLastWorldTransfer(float $time) : void {
		$this->lastWorldTransfer = $time;
	}

	/**
	 * Returns timestamp of the last world-transfer marker.
	 */
	public function getLastWorldTransfer() : float {
		return $this->lastWorldTransfer;
	}

	/**
	 * Returns whether world-transfer cooldown is currently active.
	 *
	 * @param float $cooldownSeconds Cooldown duration in seconds.
	 */
	public function isInWorldTransferCooldown(float $cooldownSeconds = 5.0) : bool {
		if ($this->lastWorldTransfer < 1.0) {
			return false;
		}
		return (microtime(true) - $this->lastWorldTransfer) < $cooldownSeconds;
	}

	/**
	 * Check if player is in any false-positive cooldown window.
	 * Returns true if checks should be skipped.
	 */
	public function isInFPCooldown() : bool {
		// Skip checks right after joining (first 3 seconds)
		if ($this->getOnlineTime() < 3) {
			return true;
		}

		// Skip checks during lag cooldown (3 seconds after lag spike)
		if ($this->isInLagCooldown(3.0)) {
			return true;
		}

		// Skip checks during world transfer cooldown (5 seconds after teleport to new world)
		if ($this->isInWorldTransferCooldown(5.0)) {
			return true;
		}

		// Skip checks right after teleport (2 seconds)
		if ($this->getTeleportTicks() < 40) { // 40 ticks = 2 seconds
			return true;
		}

		return false;
	}
}
