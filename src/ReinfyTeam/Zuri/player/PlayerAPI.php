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
 * @author ReinfyTeam
 * @link https://github.com/ReinfyTeam/
 *
 *
 */

declare(strict_types=1);

namespace ReinfyTeam\Zuri\player;

use pocketmine\entity\Location;
use pocketmine\math\Facing;
use pocketmine\player\Player;
use pocketmine\player\SurvivalBlockBreakHandler;
use pocketmine\Server;
use pocketmine\block\BlockTypeIds;
use ReinfyTeam\Zuri\components\player\IPlayerAPI;
use function microtime;

class PlayerAPI implements IPlayerAPI {
	/** @var PlayerAPI[] */
	public static array $players = [];

	public static function getAPIPlayer(Player $player) : PlayerAPI {
		return self::$players[$player->getName()] ??= new PlayerAPI($player);
	}

	public static function removeAPIPlayer(Player $player) : void {
		unset(self::$players[$player->getName()]);
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

	//On adhesion
	public function isOnAdhesion() : bool {
		return $this->onAdhesion;
	}

	public function setOnAdhesion(bool $data) : void {
		$this->onAdhesion = $data;
	}

	//On plant
	public function isOnPlant() : bool {
		return $this->onPlant;
	}

	public function setOnPlant(bool $data) : void {
		$this->onPlant = $data;
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
	public function isRCSprinting() : bool {
		return $this->player->isSprinting();
	}

	public function setRCSprinting(bool $data) : void {
		$this->player->setSprinting($data);
	}

	//On ground
	public function isOnGround() : bool {
		return $this->player->onGround;
	}

	public function setOnGround(bool $data) : void {
		$this->player->onGround = $data;
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
		if ($ref === null) {
			$ref = new \ReflectionProperty(Player::class, "blockBreakHandler");
			$ref->setAccessible(true);
		}
		return $ref->getValue($this->player);
	}

	//In Web
	public function isInWeb() : bool {
		$world = $this->player->getWorld();
		$location = $this->player->getLocation();
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
		$world = $this->player->getWorld();
		$location = $this->player->getLocation();
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
		return $this->player->getNetworkSession()->getPing() === null ? 0.0 : $this->player->getNetworkSession()->getPing();
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
		if (isset($this->violations[$name = $this->player->getName()][$supplier])) {
			return $this->violations[$name][$supplier]["vl"];
		}
		return 1;
	}

	public function setViolation(string $supplier, int $amount) : void {
		$this->violations[$this->player->getName()][$supplier]["vl"] = $amount;
	}

	public function addViolation(string $supplier) : void {
		if (isset($this->violations[$name = $this->player->getName()][$supplier])) {
			$delayTime = microtime(true) - $this->violations[$name][$supplier]["time"];
			if ($delayTime < 2) {
				$this->violations[$name][$supplier]["vl"] += 1;
			} else {
				unset($this->violations[$name][$supplier]);
			}
		} else {
			$this->violations[$name][$supplier] = ["vl" => 1, "time" => microtime(true)];
		}
	}

	//Real violation
	public function getRealViolation(string $supplier) : int {
		if (isset($this->realViolations[$name = $this->player->getName()][$supplier])) {
			return $this->realViolations[$name][$supplier]["vl"];
		}
		return 0;
	}

	public function setRealViolation(string $supplier, int $amount) : void {
		$this->realViolations[$this->player->getName()][$supplier]["vl"] = $amount;
	}

	public function addRealViolation(string $supplier) : void {
		if (isset($this->realViolations[$name = $this->player->getName()][$supplier])) {
			$delayTime = microtime(true) - $this->realViolations[$name][$supplier]["time"];
			if ($delayTime < 600) {
				$this->realViolations[$name][$supplier]["vl"] += 1;
			} else {
				unset($this->realViolations[$name][$supplier]);
			}
		} else {
			$this->realViolations[$name][$supplier] = ["vl" => 1, "time" => microtime(true)];
		}
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
		if (isset($this->externalData[$name = $this->player->getName()][$dataName])) {
			return $this->externalData[$name][$dataName];
		}
		return null;
	}

	public function setExternalData(string $dataName, mixed $amount) : void {
		$this->externalData[$this->player->getName()][$dataName] = $amount;
	}

	public function unsetExternalData(string $dataName) : void {
		if (isset($this->externalData[$name = $this->player->getName()][$dataName])) {
			unset($this->externalData[$name][$dataName]);
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
		return $this->player->getInventory();
	}

	public function getLocation() {
		return $this->player->getLocation();
	}

	public function sendAdminMessage(string $message) : void {
		foreach (Server::getInstance()->getOnlinePlayers() as $player) {
			if ($player->hasPermission("zuri.admin") || Server::getInstance()->isOp($player->getName())) {
				$player->sendMessage($message);
			}
		}
	}
}
