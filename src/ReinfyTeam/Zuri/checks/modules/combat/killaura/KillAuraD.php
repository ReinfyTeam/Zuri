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

namespace ReinfyTeam\Zuri\checks\modules\combat\killaura;

use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\network\mcpe\protocol\DataPacket;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function intval;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

/**
 * Detects invalid arm-swing packet patterns associated with aura clients.
 */
class KillAuraD extends Check {
	/**
	 * Returns the check name.
	 *
	 * @return string Check identifier.
	 */
	public function getName() : string {
		return "KillAura";
	}

	/**
	 * Returns the check subtype.
	 *
	 * @return string Check subtype identifier.
	 */
	public function getSubType() : string {
		return "D";
	}

	/**
	 * Processes animation packets for KillAura D evaluation.
	 *
	 * @param DataPacket $packet Incoming packet.
	 * @param PlayerAPI $playerAPI Player context.
	 * @throws DiscordWebhookException
	 */
	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		$player = $playerAPI->getPlayer();
		if ($packet instanceof AnimatePacket) {
			$this->dispatchAsyncCheck($player->getName(), [
				"checkName" => $this->getName(),
				"checkSubType" => $this->getSubType(),
				"isDigging" => $playerAPI->isDigging(),
				"placingTicks" => $playerAPI->getPlacingTicks(),
				"attackTicks" => $playerAPI->getAttackTicks(),
				"survival" => $player->isSurvival(),
				"recentlyCancelled" => $playerAPI->isRecentlyCancelledEvent(),
				"action" => $packet->action,
			]);
		}
	}

	/**
	 * Evaluates an async payload for KillAura D violations.
	 *
	 * @param array<string,mixed> $payload Serialized check payload.
	 * @return array<string,mixed> Async decision data.
	 */
	public static function evaluateAsync(array $payload) : array {
		$check = new self();
		if (($payload["checkName"] ?? null) !== $check->getName() || ($payload["checkSubType"] ?? null) !== $check->getSubType()) {
			return [];
		}

		$actionRaw = $payload["action"] ?? -1;
		$placingTicksRaw = $payload["placingTicks"] ?? 0;
		$attackTicksRaw = $payload["attackTicks"] ?? 0;
		$survivalRaw = $payload["survival"];
		$toInt = static function (mixed $value, int $default = 0) : int {
			if (is_int($value)) {
				return $value;
			}

			if (is_float($value) || is_string($value) || is_bool($value) || $value === null || is_array($value)) {
				return intval($value);
			}

			return $default;
		};
		$action = $toInt($actionRaw, -1);
		$placingTicks = $toInt($placingTicksRaw);
		$attackTicks = $toInt($attackTicksRaw);
		$survival = (bool) $survivalRaw;
		$isDigging = (bool) ($payload["isDigging"] ?? false);

		if (
			$isDigging ||
			$placingTicks < 100 ||
			$attackTicks < 20 ||
			!$survival ||
			(bool) ($payload["recentlyCancelled"] ?? false)
		) {
			return [];
		}

		$debug = "isDigging=false, placingTicks={$placingTicks}, attackTicks={$attackTicks}, isSurvival=true";
		if ($action !== AnimatePacket::ACTION_SWING_ARM && $attackTicks > 40) {
			return ["failed" => true, "debug" => $debug];
		}

		return ["debug" => $debug];
	}
}
