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

namespace ReinfyTeam\Zuri\checks\moving;

use ReinfyTeam\Zuri\config\CheckConstants;
use ReinfyTeam\Zuri\config\CacheData;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\Event;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\types\InputMode;
use pocketmine\network\mcpe\protocol\types\PlayerAuthInputFlags;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use ReinfyTeam\Zuri\utils\MathUtil;
use function max;

class OmniSprint extends Check {
	private const string BUFFER_KEY = CacheData::OMNISPRINT_A_BUFFER;
	private const string LAST_MOVE_XZ_KEY = CacheData::OMNISPRINT_A_LAST_MOVE_XZ;

	public function getName() : string {
		return "OmniSprint";
	}

	public function getSubType() : string {
		return "A";
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		if (!$packet instanceof PlayerAuthInputPacket) {
			return;
		}

		$player = $playerAPI->getPlayer();
		$maxPing = (int) ($this->getConstant(CheckConstants::OMNISPRINT_MAX_PING) ?? self::getData(self::PING_LAGGING));
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
		$minInputLength = (float) ($this->getConstant(CheckConstants::OMNISPRINT_MIN_INPUT_LENGTH) ?? 0.75);
		$maxSpeed = (float) ($this->getConstant(CheckConstants::OMNISPRINT_MAX_SPEED) ?? 0.09);
		$moveXZ = (float) $playerAPI->getExternalData(self::LAST_MOVE_XZ_KEY, 0.0);
		$movingFast = $moveXZ > $maxSpeed;
		$movingByInput = $inputLength >= $minInputLength;

		$buffer = (int) $playerAPI->getExternalData(self::BUFFER_KEY, 0);
		if ($player->isSprinting() && $invalidDirection && $movingFast && $movingByInput) {
			$buffer++;
		} else {
			$buffer = max(0, $buffer - 1);
		}

		$playerAPI->setExternalData(self::BUFFER_KEY, $buffer);
		$this->debug($playerAPI, "left=" . ($left ? "1" : "0") . ", right=" . ($right ? "1" : "0") . ", down=" . ($down ? "1" : "0") . ", up=" . ($up ? "1" : "0") . ", inputLength={$inputLength}, moveXZ={$moveXZ}, movingFast=" . ($movingFast ? "1" : "0") . ", invalidDirection=" . ($invalidDirection ? "1" : "0") . ", buffer={$buffer}");

		$bufferLimit = (int) ($this->getConstant(CheckConstants::OMNISPRINT_BUFFER_LIMIT) ?? 3);
		if ($buffer >= $bufferLimit) {
			$playerAPI->setExternalData(self::BUFFER_KEY, 0);
			$this->failed($playerAPI);
		}
	}

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
		$this->debug($playerAPI, "moveXZ={$moveXZ}, isSprinting=" . ($playerAPI->getPlayer()->isSprinting() ? "1" : "0"));
	}

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

	private function resetState(PlayerAPI $playerAPI) : void {
		$playerAPI->setExternalData(self::BUFFER_KEY, 0);
		$playerAPI->unsetExternalData(self::LAST_MOVE_XZ_KEY);
	}
}
