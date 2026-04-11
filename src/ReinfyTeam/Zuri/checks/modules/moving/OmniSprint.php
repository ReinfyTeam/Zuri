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

namespace ReinfyTeam\Zuri\checks\modules\moving;

use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\Event;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\types\InputMode;
use pocketmine\network\mcpe\protocol\types\PlayerAuthInputFlags;
use pocketmine\Server;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\config\CacheData;
use ReinfyTeam\Zuri\config\CheckConstants;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use ReinfyTeam\Zuri\utils\MathUtil;
use function is_numeric;
use function max;

/**
 * Detects sprinting behavior while moving in invalid directions.
 */
class OmniSprint extends Check {
	private const BUFFER_KEY = CacheData::OMNISPRINT_A_BUFFER;
	private const LAST_MOVE_XZ_KEY = CacheData::OMNISPRINT_A_LAST_MOVE_XZ;
	private const LAST_MOVE_TICK_KEY = CacheData::OMNISPRINT_A_LAST_MOVE_TICK;

	/**
	 * Gets the check name.
	 */
	public function getName() : string {
		return "OmniSprint";
	}

	/**
	 * Gets the check subtype identifier.
	 */
	public function getSubType() : string {
		return "A";
	}

	/**
	 * Processes input packets for omnisprint detection.
	 *
	 * @param DataPacket $packet Incoming network packet.
	 * @param PlayerAPI $playerAPI Player state wrapper.
	 *
	 * @throws DiscordWebhookException
	 */
	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		if (!$packet instanceof PlayerAuthInputPacket) {
			return;
		}

		$player = $playerAPI->getPlayer();
		$maxPingRaw = $this->getConstant(CheckConstants::OMNISPRINT_MAX_PING) ?? self::getData(self::PING_LAGGING);
		$maxPing = is_numeric($maxPingRaw) ? (int) $maxPingRaw : 0;
		if ($this->isExempt($playerAPI) || $playerAPI->getHurtTicks() < 20 || (int) $playerAPI->getPing() > $maxPing) {
			$this->resetState($playerAPI);
			return;
		}

		if ($packet->getInputMode() !== InputMode::MOUSE_KEYBOARD && $packet->getInputMode() !== InputMode::TOUCHSCREEN) {
			$this->resetState($playerAPI);
			return;
		}

		$inputFlags = $packet->getInputFlags();
		$left = $inputFlags->get(PlayerAuthInputFlags::LEFT);
		$right = $inputFlags->get(PlayerAuthInputFlags::RIGHT);
		$down = $inputFlags->get(PlayerAuthInputFlags::DOWN);
		$up = $inputFlags->get(PlayerAuthInputFlags::UP);

		$backward = $down && !$up;
		$sidewaysOnly = ($left xor $right) && !$up && !$down;
		$invalidDirection = $backward || $sidewaysOnly;

		$inputLength = MathUtil::horizontalLength($packet->getMoveVecX(), $packet->getMoveVecZ());
		$minInputLengthRaw = $this->getConstant(CheckConstants::OMNISPRINT_MIN_INPUT_LENGTH) ?? 0.75;
		$maxSpeedRaw = $this->getConstant(CheckConstants::OMNISPRINT_MAX_SPEED) ?? 0.09;
		$minInputLength = is_numeric($minInputLengthRaw) ? (float) $minInputLengthRaw : 0.75;
		$maxSpeed = is_numeric($maxSpeedRaw) ? (float) $maxSpeedRaw : 0.09;
		$currentTick = Server::getInstance()->getTick();
		$lastMoveTickRaw = $playerAPI->getExternalData(self::LAST_MOVE_TICK_KEY, 0);
		$lastMoveTick = is_numeric($lastMoveTickRaw) ? (int) $lastMoveTickRaw : 0;
		if ($lastMoveTick <= 0 || ($currentTick - $lastMoveTick) > 3) {
			$this->resetState($playerAPI);
			return;
		}

		$moveXZRaw = $playerAPI->getExternalData(self::LAST_MOVE_XZ_KEY, 0.0);
		$moveXZ = is_numeric($moveXZRaw) ? (float) $moveXZRaw : 0.0;
		$movingFast = $moveXZ > $maxSpeed;
		$movingByInput = $inputLength >= $minInputLength;

		$bufferRaw = $playerAPI->getExternalData(self::BUFFER_KEY, 0);
		$buffer = is_numeric($bufferRaw) ? (int) $bufferRaw : 0;
		if ($player->isSprinting() && $invalidDirection && $movingFast && $movingByInput) {
			$buffer++;
		} else {
			$buffer = max(0, $buffer - 1);
		}

		$playerAPI->setExternalData(self::BUFFER_KEY, $buffer);
		$this->debug($playerAPI, "left=" . ($left ? "1" : "0") . ", right=" . ($right ? "1" : "0") . ", down=" . ($down ? "1" : "0") . ", up=" . ($up ? "1" : "0") . ", inputLength={$inputLength}, moveXZ={$moveXZ}, movingFast=" . ($movingFast ? "1" : "0") . ", invalidDirection=" . ($invalidDirection ? "1" : "0") . ", buffer={$buffer}");

		$bufferLimitRaw = $this->getConstant(CheckConstants::OMNISPRINT_BUFFER_LIMIT) ?? 3;
		$bufferLimit = is_numeric($bufferLimitRaw) ? (int) $bufferLimitRaw : 3;
		if ($buffer >= $bufferLimit) {
			$playerAPI->setExternalData(self::BUFFER_KEY, 0);
			$this->dispatchAsyncDecision($playerAPI, true);
		}
	}

	/**
	 * Handles move events used by the omnisprint state tracker.
	 *
	 * @param Event $event Triggered event instance.
	 * @param PlayerAPI $playerAPI Player state wrapper.
	 */
	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if (!$event instanceof PlayerMoveEvent) {
			return;
		}

		if ($this->isExempt($playerAPI)) {
			$this->resetState($playerAPI);
			return;
		}

		$moveXZ = MathUtil::XZDistanceSquared($event->getFrom(), $event->getTo());
		$playerAPI->setExternalData(self::LAST_MOVE_XZ_KEY, $moveXZ);
		$playerAPI->setExternalData(self::LAST_MOVE_TICK_KEY, Server::getInstance()->getTick());
		$this->debug($playerAPI, "moveXZ={$moveXZ}, isSprinting=" . ($playerAPI->getPlayer()->isSprinting() ? "1" : "0"));
	}

	/**
	 * Determines whether a player should be exempt from this check.
	 *
	 * @param PlayerAPI $playerAPI Player state wrapper.
	 */
	private function isExempt(PlayerAPI $playerAPI) : bool {
		$player = $playerAPI->getPlayer();
		return
			!$player->isSurvival() ||
			$player->isCreative() ||
			$player->isSpectator() ||
			!$playerAPI->isCurrentChunkIsLoaded() ||
			$player->getAllowFlight() ||
			$player->isFlying() ||
			$player->hasNoClientPredictions() ||
			$playerAPI->isGliding() ||
			$playerAPI->getLastMoveTick() > 5 ||
			!$playerAPI->isOnGround() ||
			$playerAPI->isInLiquid() ||
			$playerAPI->isOnIce() ||
			$playerAPI->isOnStairs() ||
			$playerAPI->isOnAdhesion() ||
			$playerAPI->isInWeb() ||
			$playerAPI->getTeleportTicks() < 40 ||
			$playerAPI->getTeleportCommandTicks() < 40 ||
			$player->getEffects()->has(VanillaEffects::SPEED()) ||
			$playerAPI->isRecentlyCancelledEvent();
	}

	/**
	 * Resets persisted omni-sprint tracking state.
	 *
	 * @param PlayerAPI $playerAPI Player state wrapper.
	 */
	private function resetState(PlayerAPI $playerAPI) : void {
		$playerAPI->setExternalData(self::BUFFER_KEY, 0);
		$playerAPI->unsetExternalData(self::LAST_MOVE_XZ_KEY);
		$playerAPI->unsetExternalData(self::LAST_MOVE_TICK_KEY);
	}
}
