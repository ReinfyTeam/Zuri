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

namespace ReinfyTeam\Zuri\components\player;

use pocketmine\entity\Location;

interface IPlayerAPI {
	public function isCaptcha() : bool;

	public function setCaptcha(bool $data) : void;

	public function isFlagged() : bool;

	public function setFlagged(bool $data) : void;

	public function actionBreakingSpecial() : bool;

	public function setActionBreakingSpecial(bool $data) : void;

	public function actionPlacingSpecial() : bool;

	public function setActionPlacingSpecial(bool $data) : void;

	public function isInventoryOpen() : bool;

	public function setInventoryOpen(bool $data) : void;

	public function isTransactionArmorInventory() : bool;

	public function setTransactionArmorInventory(bool $data) : void;

	public function isUnderBlock() : bool;

	public function setUnderBlock(bool $data) : void;

	public function isOnAdhesion() : bool;

	public function setOnAdhesion(bool $data) : void;

	public function isOnPlant() : bool;

	public function setOnPlant(bool $data) : void;

	public function isOnDoor() : bool;

	public function setOnDoor(bool $data) : void;

	public function isOnCarpet() : bool;

	public function setOnCarpet(bool $data) : void;

	public function isOnPlate() : bool;

	public function setOnPlate(bool $data) : void;

	public function isOnSnow() : bool;

	public function setOnSnow(bool $data) : void;

	public function isRCSprinting() : bool;

	public function setRCSprinting(bool $data) : void;

	public function isOnGround() : bool;

	public function setOnGround(bool $data) : void;

	public function isSniffing() : bool;

	public function setSniffing(bool $data) : void;

	public function isInLiquid() : bool;

	public function setInLiquid(bool $data) : void;

	public function isOnStairs() : bool;

	public function setOnStairs(bool $data) : void;

	public function isOnIce() : bool;

	public function setOnIce(bool $data) : void;

	public function isDigging() : bool;

	public function isInWeb() : bool;

	public function isInBoxBlock() : bool;

	public function getLastGroundY() : float;

	public function setlastGroundY(float $data) : void;

	public function getLastNoGroundY() : float;

	public function setlastNoGroundY(float $data) : void;

	public function getLastDelayedMovePacket() : float;

	public function setLastDelayedMovePacket(float $data) : void;

	public function getPing() : float;

	public function getCPS() : int;

	public function setCPS(int $data) : void;

	public function getBlocksBrokeASec() : int;

	public function setBlocksBrokeASec(int $data) : void;

	public function getBlocksPlacedASec() : int;

	public function setBlocksPlacedASec(int $data) : void;

	public function getNumberBlocksAllowBreak() : int;

	public function setNumberBlocksAllowBreak(int $data) : void;

	public function getNumberBlocksAllowPlace() : int;

	public function setNumberBlocksAllowPlace(int $data) : void;

	public function getJoinedAtTheTime() : float;

	public function setJoinedAtTheTime(float $data) : void;

	public function getOnlineTime() : int;

	public function getTeleportTicks() : float;

	public function setTeleportTicks(float $data) : void;

	public function getJumpTicks() : float;

	public function setJumpTicks(float $data) : void;

	public function getAttackTicks() : float;

	public function setAttackTicks(float $data) : void;

	public function getSlimeBlockTicks() : float;

	public function setSlimeBlockTicks(float $data) : void;

	public function getDeathTicks() : float;

	public function setDeathTicks(float $data) : void;

	public function getPlacingTicks() : float;

	public function setPlacingTicks(float $data) : void;

	public function getViolation(string $supplier) : int;

	public function setViolation(string $supplier, int $amount) : void;

	public function addViolation(string $supplier) : void;

	public function getRealViolation(string $supplier) : int;

	public function setRealViolation(string $supplier, int $amount) : void;

	public function addRealViolation(string $supplier) : void;

	public function getNLocation() : array;

	public function setNLocation(Location $from, Location $to) : void;

	public function getExternalData(string $dataName);

	public function setExternalData(string $dataName, mixed $amount) : void;

	public function unsetExternalData(string $dataName) : void;

	public function getCaptchaCode() : string;

	public function setCaptchaCode(string $data) : void;
}