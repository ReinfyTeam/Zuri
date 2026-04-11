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

namespace ReinfyTeam\Zuri\checks\modules\badpackets\inputspoof;

use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\config\CheckConstants;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use ReinfyTeam\Zuri\utils\MathUtil;
use function abs;
use function is_numeric;

/**
 * Detects spoofed movement input state in packets.
 */
class InputSpoofA extends Check {
	/**
	 * Gets the check name.
	 */
	public function getName() : string {
		return "InputSpoof";
	}

	/**
	 * Gets the check subtype identifier.
	 */
	public function getSubType() : string {
		return "A";
	}

	/**
	 * Processes packets for input spoof validation.
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

		$moveX = $packet->getMoveVecX();
		$moveZ = $packet->getMoveVecZ();
		$maxAxisRaw = $this->getConstant(CheckConstants::INPUTSPOOFA_MAX_AXIS);
		$maxVectorLengthRaw = $this->getConstant(CheckConstants::INPUTSPOOFA_MAX_VECTOR_LENGTH);
		$maxAxis = is_numeric($maxAxisRaw) ? (float) $maxAxisRaw : 0.0;
		$maxVectorLength = is_numeric($maxVectorLengthRaw) ? (float) $maxVectorLengthRaw : 0.0;
		$vectorLength = MathUtil::horizontalLength($moveX, $moveZ);

		$this->debug($playerAPI, "moveX={$moveX}, moveZ={$moveZ}, vectorLength={$vectorLength}");

		if (abs($moveX) > $maxAxis || abs($moveZ) > $maxAxis || $vectorLength > $maxVectorLength) {
			$this->dispatchAsyncDecision($playerAPI, true);
		}
	}
}
