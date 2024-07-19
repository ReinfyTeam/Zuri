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
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\player\SurvivalBlockBreakHandler;
use ReflectionProperty;
use function abs;
use function count;
use function microtime;
use function spl_object_id;

class PlayerAPI implements IPlayerAPI {
	/** @var PlayerAPI[] */
	public static array $players = [];

	public static function getAPIPlayer(Player $player) : PlayerAPI {
		return self::$players[spl_object_id($player)] ??= new PlayerAPI($player);
	}

	public static function removeAPIPlayer(Player $player) : void {
		unset(self::$players[spl_object_id($player)]);
	}

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
	private float $lastMoveTick = 0.0;
	private int $cps = 0;
	private int $blocksBrokeASec = 0;
	private int $blocksPlacedASec = 0;
	private int $numberBlocksAllowBreak = 2; //2 is normal action
	private int $numberBlocksAllowPlace = 2; //2 is normal action
	private array $violations = [];
	private array $realViolations = [];
	private array $nLocation = [];
	private array $externalData = [];
	private string $captchaCode = "nocode";

	public function __construct(private Player $player) {
		// no-op
	}

	public function getPlayer() : Player {
		return $this->player;
	}

	//Captcha
	public function isCaptcha() : bool {
		return $this->isCaptcha;
	}

	public function setCaptcha(bool $data) : void {
		$this->isCaptcha = $data;
	}

	//Flagged
	public function isFlagged() : bool {
		return $this->flagged;
	}

	public function setFlagged(bool $data) : void {
		$this->flagged = $data;
	}

	public function isCurrentChunkIsLoaded() : bool {
		return $this->getPlayer()->getWorld()->isInLoadedTerrain($this->getPlayer()->getLocation());
	}

	//Break many blocks just one time break (This can check NUKER PLAYER)
	public function actionBreakingSpecial() : bool {
		return $this->actionBreakingSpecial;
	}

	public function setActionBreakingSpecial(bool $data) : void {
		$this->actionBreakingSpecial = $data;
	}

	//Place many blocks just one time place (This can check FILLBLOCK PLAYER)
	public function actionPlacingSpecial() : bool {
		return $this->actionPlacingSpecial;
	}

	public function setActionPlacingSpecial(bool $data) : void {
		$this->actionPlacingSpecial = $data;
	}

	//Inventory
	public function isInventoryOpen() : bool {
		return $this->inventoryOpen;
	}

	public function setInventoryOpen(bool $data) : void {
		$this->inventoryOpen = $data;
	}

	//Transaction armor inventory
	public function isTransactionArmorInventory() : bool {
		return $this->transactionArmorInventory;
	}

	public function setTransactionArmorInventory(bool $data) : void {
		$this->transactionArmorInventory = $data;
	}

	//Under block
	public function isUnderBlock() : bool {
		return $this->underBlock;
	}

	public function setUnderBlock(bool $data) : void {
		$this->underBlock = $data;
	}

	//Top block
	public function isTopBlock() : bool {
		return $this->topBlock;
	}

	public function setTopBlock(bool $data) : void {
		$this->topBlock = $data;
	}

	//On adhesion
	public function isOnAdhesion() : bool {
		return $this->onAdhesion;
	}

	public function setOnAdhesion(bool $data) : void {
		$this->onAdhesion = $data;
	}
	
	public function getDeviceOS() : int {
		return $this->getPlayer()->getPlayerInfo()->getExtraData()["DeviceOS"];
	}

	//On plant
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
		return $this->lastMoveTick;
	}

	//On door
	public function isOnDoor() : bool {
		return $this->onDoor;
	}

	public function setOnDoor(bool $data) : void {
		$this->onDoor = $data;
	}

	//On carpet
	public function isOnCarpet() : bool {
		return $this->onCarpet;
	}

	public function setOnCarpet(bool $data) : void {
		$this->onCarpet = $data;
	}

	//On plate
	public function isOnPlate() : bool {
		return $this->onPlate;
	}

	public function setOnPlate(bool $data) : void {
		$this->onPlate = $data;
	}

	//On snow
	public function isOnSnow() : bool {
		return $this->onSnow;
	}

	public function setOnSnow(bool $data) : void {
		$this->onSnow = $data;
	}

	//Sprinting
	public function isSprinting() : bool {
		return $this->getPlayer()->isSprinting();
	}

	public function setSprinting(bool $data) : void {
		$this->getPlayer()->setSprinting($data);
	}

	//On ground
	public function isOnGround() : bool {
		if ($this->getPlayer() === null) {
			return false;
		}
		return $this->getPlayer()->onGround;
	}

	public function setOnGround(bool $data) : void {
		if ($this->getPlayer() === null) {
			return;
		}
		$this->getPlayer()->onGround = $data;
	}

	//Sniffing
	public function isSniffing() : bool {
		return $this->sniffing;
	}

	public function setSniffing(bool $data) : void {
		$this->sniffing = $data;
	}

	//In Liquid
	public function isInLiquid() : bool {
		return $this->inLiquid;
	}

	public function setInLiquid(bool $data) : void {
		$this->inLiquid = $data;
	}

	//On stairs
	public function isOnStairs() : bool {
		return $this->onStairs;
	}

	public function setOnStairs(bool $data) : void {
		$this->onStairs = $data;
	}

	//On Ice
	public function isOnIce() : bool {
		return $this->onIce;
	}

	public function setOnIce(bool $data) : void {
		$this->onIce = $data;
	}

	//Digging
	public function isDigging() : bool {
		if ($this->getBlockBreakHandler() !== null) {
			return true;
		}
		return false;
	}

	private function getBlockBreakHandler() : ?SurvivalBlockBreakHandler {
		static $ref = null;
		if ($this->getPlayer() === null) {
			return null;
		}
		if ($ref === null) {
			$ref = new ReflectionProperty($this->getPlayer(), "blockBreakHandler");
		}
		return $ref->getValue($this->getPlayer());
	}

	//In Web
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

	//In Box Block
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

	// is in bounding box
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

	//Last ground Y
	public function getLastGroundY() : float {
		return $this->lastGroundY;
	}

	public function setlastGroundY(float $data) : void {
		$this->lastGroundY = $data;
	}

	//Last no ground Y
	public function getLastNoGroundY() : float {
		return $this->lastNoGroundY;
	}

	public function setlastNoGroundY(float $data) : void {
		$this->lastNoGroundY = $data;
	}

	//Last delayed move packet
	public function getLastDelayedMovePacket() : float {
		return $this->lastDelayedMovePacket;
	}

	public function setLastDelayedMovePacket(float $data) : void {
		$this->lastDelayedMovePacket = $data;
	}

	//Ping
	public function getPing() : float {
		if (!$this->getPlayer()->isConnected() && !$this->getPlayer()->spawned) {
			return 0.0;
		} // always check first if player is currently connected before initilizing the main ping. This fixes the player if it is currently connected and ping has been initilized as well. Also, checking first player if its spawn is necessary to do checking after player is spawned as well.

		return $this->getPlayer()->getNetworkSession()->getPing() === null ? 0.0 : $this->getPlayer()->getNetworkSession()->getPing(); // TODO: 0.0 frrr ping?
	}

	//CPS
	public function getCPS() : int {
		return $this->cps;
	}

	public function setCPS(int $data) : void {
		$this->cps = $data;
	}

	//Number blocks broke one second
	public function getBlocksBrokeASec() : int {
		return $this->blocksBrokeASec;
	}

	public function setBlocksBrokeASec(int $data) : void {
		$this->blocksBrokeASec = $data;
	}

	//Number blocks place one second
	public function getBlocksPlacedASec() : int {
		return $this->blocksPlacedASec;
	}

	public function setBlocksPlacedASec(int $data) : void {
		$this->blocksPlacedASec = $data;
	}

	//Number blocks allow break per sec
	public function getNumberBlocksAllowBreak() : int {
		return $this->numberBlocksAllowBreak;
	}

	public function setNumberBlocksAllowBreak(int $data) : void {
		$this->numberBlocksAllowBreak = $data;
	}

	//Number blocks allow break per sec
	public function getNumberBlocksAllowPlace() : int {
		return $this->numberBlocksAllowPlace;
	}

	public function setNumberBlocksAllowPlace(int $data) : void {
		$this->numberBlocksAllowPlace = $data;
	}

	//Time when player join
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

	//Teleport ticks
	public function getTeleportTicks() : float {
		return (microtime(true) - $this->teleportTicks) * 20;
	}

	public function setTeleportTicks(float $data) : void {
		$this->teleportTicks = $data;
	}

	//Jump ticks
	public function getJumpTicks() : float {
		return (microtime(true) - $this->jumpTicks) * 20;
	}

	public function setJumpTicks(float $data) : void {
		$this->jumpTicks = $data;
	}

	//Attack ticks
	public function getAttackTicks() : float {
		return (microtime(true) - $this->attackTicks) * 20;
	}

	public function setAttackTicks(float $data) : void {
		$this->attackTicks = $data;
	}

	//On slime block ticks
	public function getSlimeBlockTicks() : float {
		return (microtime(true) - $this->slimeBlockTicks) * 20;
	}

	public function setSlimeBlockTicks(float $data) : void {
		$this->slimeBlockTicks = $data;
	}

	//Death ticks
	public function getDeathTicks() : float {
		return (microtime(true) - $this->deathTicks) * 20;
	}

	public function setDeathTicks(float $data) : void {
		$this->deathTicks = $data;
	}

	//Placing ticks
	public function getPlacingTicks() : float {
		return (microtime(true) - $this->placingTicks) * 20;
	}

	public function setPlacingTicks(float $data) : void {
		$this->placingTicks = $data;
	}

	//Violation
	public function getViolation(string $supplier) : int {
		if (isset($this->violations[$supplier])) {
			return count($this->violations[$supplier]);
		}
		return 0;
	}

	public function resetViolation(string $supplier) : void {
		if (isset($this->violations[$supplier])) {
			unset($this->violations[$supplier]);
		}
	}

	public function addViolation(string $supplier, int|float $amount = 1) : void {
		if (isset($this->violations[$supplier])) {
			foreach ($this->violations[$supplier] as $index => $time) {
				if (abs($time - microtime(true)) * 20 > 40) {
					unset($this->violations[$supplier][$index]);
				}
			}
		}

		$this->violations[$supplier][] = microtime(true);
	}

	//Real violation
	public function getRealViolation(string $supplier) : int {
		if (isset($this->realViolations[$supplier])) {
			return count($this->realViolations[$supplier]);
		}
		return 0;
	}

	public function resetRealViolation(string $supplier) : void {
		if (isset($this->realViolations[$supplier])) {
			unset($this->realViolations[$supplier]);
		}
	}

	public function addRealViolation(string $supplier, int|float $amount = 1) : void {
		if (isset($this->realViolations[$supplier])) {
			foreach ($this->realViolations[$supplier] as $index => $time) {
				if (abs($time - microtime(true)) * 20 > 300) {
					unset($this->realViolations[$supplier][$index]);
				}
			}
		}

		$this->realViolations[$supplier][] = microtime(true);
	}

	//Location
	public function getNLocation() : array {
		return $this->nLocation;
	}

	public function setNLocation(Location $from, Location $to) : void {
		$this->nLocation = ["from" => $from, "to" => $to];
	}

	//External Data
	public function getExternalData(string $dataName) {
		if (isset($this->externalData[$dataName])) {
			return $this->externalData[$dataName];
		}
		return null;
	}

	public function setExternalData(string $dataName, mixed $amount) : void {
		$this->externalData[$dataName] = $amount;
	}

	public function unsetExternalData(string $dataName) : void {
		if (isset($this->externalData[$dataName])) {
			unset($this->externalData[$dataName]);
		}
	}

	//Captcha code
	public function getCaptchaCode() : string {
		return $this->captchaCode;
	}

	public function setCaptchaCode(string $data) : void {
		$this->captchaCode = $data;
	}

	public function getInventory() {
		if ($this->getPlayer() === null) {
			return;
		}
		return $this->getPlayer()->getInventory();
	}

	public function getLocation() {
		return $this->getPlayer()->getLocation();
	}

	public function setDebug(bool $value = true) : void {
		$this->debug = $value;
	}

	public function isDebug() : bool {
		return $this->debug;
	}
}
