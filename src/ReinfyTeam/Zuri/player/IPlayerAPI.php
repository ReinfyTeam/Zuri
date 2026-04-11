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

use pocketmine\entity\Location;

/**
 * Contract for mutable per-player anti-cheat state, telemetry, and cooldown values.
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
interface IPlayerAPI {
	/**
	 * Returns captcha state.
	 */
	public function isCaptcha() : bool;

	/**
	 * Sets captcha state.
	 *
	 * @param bool $data Captcha state.
	 */
	public function setCaptcha(bool $data) : void;

	/**
	 * Returns flagged state.
	 */
	public function isFlagged() : bool;

	/**
	 * Sets flagged state.
	 *
	 * @param bool $data Flagged state.
	 */
	public function setFlagged(bool $data) : void;

	/**
	 * Returns special break marker state.
	 */
	public function actionBreakingSpecial() : bool;

	/**
	 * Sets special break marker state.
	 *
	 * @param bool $data Marker state.
	 */
	public function setActionBreakingSpecial(bool $data) : void;

	/**
	 * Returns special place marker state.
	 */
	public function actionPlacingSpecial() : bool;

	/**
	 * Sets special place marker state.
	 *
	 * @param bool $data Marker state.
	 */
	public function setActionPlacingSpecial(bool $data) : void;

	/**
	 * Returns inventory-open state.
	 */
	public function isInventoryOpen() : bool;

	/**
	 * Sets inventory-open state.
	 *
	 * @param bool $data Inventory-open state.
	 */
	public function setInventoryOpen(bool $data) : void;

	/**
	 * Returns armor-transaction marker state.
	 */
	public function isTransactionArmorInventory() : bool;

	/**
	 * Sets armor-transaction marker state.
	 *
	 * @param bool $data Marker state.
	 */
	public function setTransactionArmorInventory(bool $data) : void;

	/**
	 * Returns under-block state.
	 */
	public function isUnderBlock() : bool;

	/**
	 * Sets under-block state.
	 *
	 * @param bool $data Under-block state.
	 */
	public function setUnderBlock(bool $data) : void;

	/**
	 * Returns adhesion state.
	 */
	public function isOnAdhesion() : bool;

	/**
	 * Sets adhesion state.
	 *
	 * @param bool $data Adhesion state.
	 */
	public function setOnAdhesion(bool $data) : void;

	/**
	 * Returns plant-contact state.
	 */
	public function isOnPlant() : bool;

	/**
	 * Sets plant-contact state.
	 *
	 * @param bool $data Plant-contact state.
	 */
	public function setOnPlant(bool $data) : void;

	/**
	 * Returns door-contact state.
	 */
	public function isOnDoor() : bool;

	/**
	 * Sets door-contact state.
	 *
	 * @param bool $data Door-contact state.
	 */
	public function setOnDoor(bool $data) : void;

	/**
	 * Returns carpet-contact state.
	 */
	public function isOnCarpet() : bool;

	/**
	 * Sets carpet-contact state.
	 *
	 * @param bool $data Carpet-contact state.
	 */
	public function setOnCarpet(bool $data) : void;

	/**
	 * Returns plate-contact state.
	 */
	public function isOnPlate() : bool;

	/**
	 * Sets plate-contact state.
	 *
	 * @param bool $data Plate-contact state.
	 */
	public function setOnPlate(bool $data) : void;

	/**
	 * Returns snow-contact state.
	 */
	public function isOnSnow() : bool;

	/**
	 * Sets snow-contact state.
	 *
	 * @param bool $data Snow-contact state.
	 */
	public function setOnSnow(bool $data) : void;

	/**
	 * Returns sprinting state.
	 */
	public function isSprinting() : bool;

	/**
	 * Sets sprinting state.
	 *
	 * @param bool $data Sprinting state.
	 */
	public function setSprinting(bool $data) : void;

	/**
	 * Returns on-ground state.
	 */
	public function isOnGround() : bool;

	/**
	 * Sets on-ground state.
	 *
	 * @param bool $data On-ground state.
	 */
	public function setOnGround(bool $data) : void;

	/**
	 * Returns sniffing state.
	 */
	public function isSniffing() : bool;

	/**
	 * Sets sniffing state.
	 *
	 * @param bool $data Sniffing state.
	 */
	public function setSniffing(bool $data) : void;

	/**
	 * Returns liquid state.
	 */
	public function isInLiquid() : bool;

	/**
	 * Sets liquid state.
	 *
	 * @param bool $data Liquid state.
	 */
	public function setInLiquid(bool $data) : void;

	/**
	 * Returns stairs state.
	 */
	public function isOnStairs() : bool;

	/**
	 * Sets stairs state.
	 *
	 * @param bool $data Stairs state.
	 */
	public function setOnStairs(bool $data) : void;

	/**
	 * Returns ice state.
	 */
	public function isOnIce() : bool;

	/**
	 * Sets ice state.
	 *
	 * @param bool $data Ice state.
	 */
	public function setOnIce(bool $data) : void;

	/**
	 * Returns digging state.
	 */
	public function isDigging() : bool;

	/**
	 * Returns web-contact state.
	 */
	public function isInWeb() : bool;

	/**
	 * Returns in-box-block state.
	 */
	public function isInBoxBlock() : bool;

	/**
	 * Returns last ground Y value.
	 */
	public function getLastGroundY() : float;

	/**
	 * Sets last ground Y value.
	 *
	 * @param float $data Ground Y value.
	 */
	public function setLastGroundY(float $data) : void;

	/**
	 * Returns last no-ground Y value.
	 */
	public function getLastNoGroundY() : float;

	/**
	 * Sets last no-ground Y value.
	 *
	 * @param float $data No-ground Y value.
	 */
	public function setLastNoGroundY(float $data) : void;

	/**
	 * Returns last delayed move-packet timestamp.
	 */
	public function getLastDelayedMovePacket() : float;

	/**
	 * Sets last delayed move-packet timestamp.
	 *
	 * @param float $data Move-packet timestamp.
	 */
	public function setLastDelayedMovePacket(float $data) : void;

	/**
	 * Returns current ping.
	 */
	public function getPing() : float;

	/**
	 * Returns current clicks-per-second value.
	 */
	public function getCPS() : int;

	/**
	 * Sets current clicks-per-second value.
	 *
	 * @param int $data CPS value.
	 */
	public function setCPS(int $data) : void;

	/**
	 * Returns blocks broken per second.
	 */
	public function getBlocksBrokeASec() : int;

	/**
	 * Sets blocks broken per second.
	 *
	 * @param int $data Block-break count.
	 */
	public function setBlocksBrokeASec(int $data) : void;

	/**
	 * Returns blocks placed per second.
	 */
	public function getBlocksPlacedASec() : int;

	/**
	 * Sets blocks placed per second.
	 *
	 * @param int $data Block-place count.
	 */
	public function setBlocksPlacedASec(int $data) : void;

	/**
	 * Returns allowed blocks-to-break baseline.
	 */
	public function getNumberBlocksAllowBreak() : int;

	/**
	 * Sets allowed blocks-to-break baseline.
	 *
	 * @param int $data Allowed block-break count.
	 */
	public function setNumberBlocksAllowBreak(int $data) : void;

	/**
	 * Returns allowed blocks-to-place baseline.
	 */
	public function getNumberBlocksAllowPlace() : int;

	/**
	 * Sets allowed blocks-to-place baseline.
	 *
	 * @param int $data Allowed block-place count.
	 */
	public function setNumberBlocksAllowPlace(int $data) : void;

	/**
	 * Returns join timestamp.
	 */
	public function getJoinedAtTheTime() : float;

	/**
	 * Sets join timestamp.
	 *
	 * @param float $data Join timestamp.
	 */
	public function setJoinedAtTheTime(float $data) : void;

	/**
	 * Returns online time in seconds.
	 */
	public function getOnlineTime() : int;

	/**
	 * Returns ticks since teleport marker.
	 */
	public function getTeleportTicks() : int;

	/**
	 * Sets teleport marker.
	 *
	 * @param float $data Teleport timestamp.
	 */
	public function setTeleportTicks(float $data) : void;

	/**
	 * Returns ticks since jump marker.
	 */
	public function getJumpTicks() : int;

	/**
	 * Sets jump marker.
	 *
	 * @param float $data Jump timestamp.
	 */
	public function setJumpTicks(float $data) : void;

	/**
	 * Returns ticks since attack marker.
	 */
	public function getAttackTicks() : int;

	/**
	 * Sets attack marker.
	 *
	 * @param float $data Attack timestamp.
	 */
	public function setAttackTicks(float $data) : void;

	/**
	 * Returns ticks since slime-block marker.
	 */
	public function getSlimeBlockTicks() : int;

	/**
	 * Sets slime-block marker.
	 *
	 * @param float $data Slime-block timestamp.
	 */
	public function setSlimeBlockTicks(float $data) : void;

	/**
	 * Returns ticks since death marker.
	 */
	public function getDeathTicks() : int;

	/**
	 * Sets death marker.
	 *
	 * @param float $data Death timestamp.
	 */
	public function setDeathTicks(float $data) : void;

	/**
	 * Returns ticks since placing marker.
	 */
	public function getPlacingTicks() : int;

	/**
	 * Sets placing marker.
	 *
	 * @param float $data Place timestamp.
	 */
	public function setPlacingTicks(float $data) : void;

	/**
	 * Returns violation count for a supplier.
	 *
	 * @param string $supplier Supplier key.
	 */
	public function getViolation(string $supplier) : int;

	/**
	 * Adds violation entry for a supplier.
	 *
	 * @param string $supplier Supplier key.
	 * @param int|float $amount Violation amount hint.
	 */
	public function addViolation(string $supplier, int|float $amount = 1) : void;

	/**
	 * Returns real violation count for a supplier.
	 *
	 * @param string $supplier Supplier key.
	 */
	public function getRealViolation(string $supplier) : int;

	/**
	 * Adds real violation entry for a supplier.
	 *
	 * @param string $supplier Supplier key.
	 * @param int|float $amount Violation amount hint.
	 */
	public function addRealViolation(string $supplier, int|float $amount = 1) : void;

	/**
	 * Returns tracked movement locations.
	 *
	 * @return array<string, Location>
	 */
	public function getNLocation() : array;

	/**
	 * Sets tracked movement locations.
	 *
	 * @param Location $from From location.
	 * @param Location $to To location.
	 */
	public function setNLocation(Location $from, Location $to) : void;

	/**
	 * Returns external data by key.
	 *
	 * @param string $dataName Data key.
	 * @param mixed $default Default value when missing.
	 */
	public function getExternalData(string $dataName, mixed $default = null) : mixed;

	/**
	 * Sets external data by key.
	 *
	 * @param string $dataName Data key.
	 * @param mixed $amount Value to store.
	 */
	public function setExternalData(string $dataName, mixed $amount) : void;

	/**
	 * Unsets external data by key.
	 *
	 * @param string $dataName Data key.
	 */
	public function unsetExternalData(string $dataName) : void;

	/**
	 * Returns captcha code.
	 */
	public function getCaptchaCode() : string;

	/**
	 * Sets captcha code.
	 *
	 * @param string $data Captcha code.
	 */
	public function setCaptchaCode(string $data) : void;

	/**
	 * Sets debug mode.
	 *
	 * @param bool $value Debug state.
	 */
	public function setDebug(bool $value = false) : void;

	/**
	 * Returns debug mode state.
	 */
	public function isDebug() : bool;
}
