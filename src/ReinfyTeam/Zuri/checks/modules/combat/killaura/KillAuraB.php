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

use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\config\CheckConstants;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function cos;
use function is_numeric;

/**
 * Detects impossible yaw and pitch relationships in combat packets.
 */
class KillAuraB extends Check {
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
		return "B";
	}

	/**
	 * Processes auth input packets for KillAura B evaluation.
	 *
	 * @param DataPacket $packet Incoming packet.
	 * @param PlayerAPI $playerAPI Player context.
	 * @throws DiscordWebhookException
	 */
	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		if ($packet instanceof PlayerAuthInputPacket) {
			$player = $playerAPI->getPlayer();
			$deltaPitchRaw = $this->getConstant(CheckConstants::KILLAURAB_DELTA_PITCH);
			$deltaYawRaw = $this->getConstant(CheckConstants::KILLAURAB_DELTA_YAW);
			$this->dispatchAsyncCheck($player->getName(), [
				"checkName" => $this->getName(),
				"checkSubType" => $this->getSubType(),
				"flying" => $player->isFlying(),
				"allowFlight" => $player->getAllowFlight(),
				"attackTicks" => $playerAPI->getAttackTicks(),
				"teleportTicks" => $playerAPI->getTeleportTicks(),
				"survival" => $player->isSurvival(),
				"recentlyCancelled" => $playerAPI->isRecentlyCancelledEvent(),
				"pitch" => $packet->getPitch(),
				"yaw" => $packet->getYaw(),
				"deltaPitch" => is_numeric($deltaPitchRaw) ? (float) $deltaPitchRaw : 0.0,
				"deltaYaw" => is_numeric($deltaYawRaw) ? (float) $deltaYawRaw : 0.0,
			]);
		}
	}

	/**
	 * Evaluates an async payload for KillAura B violations.
	 *
	 * @param array<string,mixed> $payload Serialized check payload.
	 * @return array<string,mixed> Async decision data.
	 */
	public static function evaluateAsync(array $payload) : array {
    // Thread-safe: execute in async worker thread only; use only $payload (no Player objects)
    if (\pocketmine\thread\Thread::getCurrentThreadId() === 0) {
        throw new \RuntimeException("evaluateAsync must not be called on the main thread");
    }
		if (($payload["checkName"] ?? null) !== "KillAura" || ($payload["checkSubType"] ?? null) !== "B") {
			return [];
		}

		if (
			!(bool) ($payload["flying"] ?? false) ||
			!(bool) ($payload["allowFlight"] ?? false) ||
			(is_numeric($payload["attackTicks"] ?? 0) ? (int) ($payload["attackTicks"] ?? 0) : 0) < 100 ||
			(is_numeric($payload["teleportTicks"] ?? 0) ? (int) ($payload["teleportTicks"] ?? 0) : 0) < 100 ||
			(bool) ($payload["survival"] ?? false) ||
			(bool) ($payload["recentlyCancelled"] ?? false)
		) {
			return [];
		}

		$pitchRaw = $payload["pitch"] ?? 0.0;
		$yawRaw = $payload["yaw"] ?? 0.0;
		$deltaPitchConstRaw = $payload["deltaPitch"] ?? 0.0;
		$deltaYawConstRaw = $payload["deltaYaw"] ?? 0.0;
		$pitch = is_numeric($pitchRaw) ? (float) $pitchRaw : 0.0;
		$yaw = is_numeric($yawRaw) ? (float) $yawRaw : 0.0;
		$deltaPitchConst = is_numeric($deltaPitchConstRaw) ? (float) $deltaPitchConstRaw : 0.0;
		$deltaYawConst = is_numeric($deltaYawConstRaw) ? (float) $deltaYawConstRaw : 0.0;
		$deltaPitch = cos($pitch);
		$deltaYaw = cos($yaw);
		if ($deltaPitch === $deltaPitchConst && $deltaYaw === $deltaYawConst) {
			return ["failed" => true, "debug" => "deltaPitch={$deltaPitch}, deltaYaw={$deltaYaw}"];
		}

		return ["debug" => "deltaPitch={$deltaPitch}, deltaYaw={$deltaYaw}"];
	}
}

