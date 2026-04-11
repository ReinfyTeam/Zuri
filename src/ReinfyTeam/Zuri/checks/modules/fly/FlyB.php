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

namespace ReinfyTeam\Zuri\checks\modules\fly;

use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\UpdateAdventureSettingsPacket;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\checks\snapshots\MovementSnapshot;
use ReinfyTeam\Zuri\config\CacheData;
use ReinfyTeam\Zuri\config\CheckConstants;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\BlockUtil;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function is_numeric;
use function max;

/**
 * Detects suspicious flight flag combinations from adventure settings.
 */
class FlyB extends Check {
	private const BUFFER_KEY = CacheData::FLY_B_BUFFER;

	/**
	 * Gets the check name.
	 */
	public function getName() : string {
		return "Fly";
	}

	/**
	 * Gets the check subtype identifier.
	 */
	public function getSubType() : string {
		return "B";
	}

	/**
	 * Processes adventure settings packets for FlyB detection.
	 *
	 * @param DataPacket $packet Incoming network packet.
	 * @param PlayerAPI $playerAPI Player state wrapper.
	 *
	 * @throws DiscordWebhookException
	 */
	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		if ($packet instanceof UpdateAdventureSettingsPacket) {
			$player = $playerAPI->getPlayer();
			$snapshot = new MovementSnapshot("FlyB", $player, $playerAPI);
			$snapshot->setEnvironmentState(
				BlockUtil::isGroundSolid($player),
				$playerAPI->isCurrentChunkIsLoaded(),
				$playerAPI->isRecentlyCancelledEvent()
			);

			// Add FlyB-specific cached data
			$snapshot->addCachedData("creative", $player->isCreative());
			$snapshot->addCachedData("spectator", $player->isSpectator());
			$snapshot->addCachedData("allowFlight", $player->getAllowFlight());
			$snapshot->addCachedData("flags", 0);
			$bufferRaw = $playerAPI->getExternalData(self::BUFFER_KEY, 0);
			$snapshot->addCachedData("buffer", is_numeric($bufferRaw) ? (int) $bufferRaw : 0);
			$bufferLimitRaw = $this->getConstant(CheckConstants::FLYB_PACKET_BUFFER_LIMIT);
			$snapshot->addCachedData("bufferLimit", is_numeric($bufferLimitRaw) ? (int) $bufferLimitRaw : 2);

			$snapshot->validate();

			// Dispatch async check with snapshot payload
			$payload = $snapshot->build();
			$this->dispatchAsyncCheck($player->getName(), $payload);
		}
	}

	/**
	 * Evaluates the async payload for FlyB violations.
	 *
	 * @param array<string,mixed> $payload Serialized check context.
	 *
	 * @return array<string,mixed>
	 */
	public static function evaluateAsync(array $payload) : array {
		if (!MovementSnapshot::validatePayload(
			$payload,
			"FlyB",
			MovementSnapshot::SCHEMA_VERSION,
			["type", "schemaVersion", "teleportTicks", "teleportCommandTicks", "hurtTicks", "cachedData"],
			[
				"teleportTicks" => [0.0, 120000.0],
				"teleportCommandTicks" => [0.0, 120000.0],
				"hurtTicks" => [0.0, 120000.0],
			]
		)) {
			return [];
		}

		$cachedData = (array) ($payload["cachedData"] ?? []);
		$teleportTicksRaw = $payload["teleportTicks"] ?? 0;
		$teleportTicks = is_numeric($teleportTicksRaw) ? (int) $teleportTicksRaw : 0;
		$teleportCommandTicksRaw = $payload["teleportCommandTicks"] ?? 0;
		$teleportCommandTicks = is_numeric($teleportCommandTicksRaw) ? (int) $teleportCommandTicksRaw : 0;
		$hurtTicksRaw = $payload["hurtTicks"] ?? 0;
		$hurtTicks = is_numeric($hurtTicksRaw) ? (int) $hurtTicksRaw : 0;
		if (
			(bool) ($cachedData["creative"] ?? false) ||
			(bool) ($cachedData["spectator"] ?? false) ||
			(bool) ($cachedData["allowFlight"] ?? false) ||
			$teleportTicks < 40 ||
			$teleportCommandTicks < 40 ||
			$hurtTicks < 20 ||
			(bool) ($payload["recentlyCancelled"] ?? false)
		) {
			return ["set" => [self::BUFFER_KEY => 0]];
		}

		$flagsRaw = $cachedData["flags"] ?? 0;
		$flags = is_numeric($flagsRaw) ? (int) $flagsRaw : 0;
		$allowFlightFlag = (($flags >> 9) & 0x01) === 1;
		$flyingFlag = (($flags >> 7) & 0x01) === 1;
		$noclipFlag = (($flags >> 6) & 0x01) === 1;
		$suspicious = $allowFlightFlag || $flyingFlag || $noclipFlag;
		$bufferRaw = $cachedData["buffer"] ?? 0;
		$buffer = is_numeric($bufferRaw) ? (int) $bufferRaw : 0;
		$buffer = $suspicious ? $buffer + 1 : max(0, $buffer - 1);
		$result = [
			"set" => [self::BUFFER_KEY => $buffer],
			"debug" => "packetFlags={$flags}, allowFlightFlag=" . ($allowFlightFlag ? "1" : "0") . ", flyingFlag=" . ($flyingFlag ? "1" : "0") . ", noclipFlag=" . ($noclipFlag ? "1" : "0") . ", suspicious=" . ($suspicious ? "true" : "false") . ", buffer={$buffer}",
		];
		$bufferLimitRaw = $cachedData["bufferLimit"] ?? 2;
		$bufferLimit = is_numeric($bufferLimitRaw) ? (int) $bufferLimitRaw : 2;
		if ($buffer >= $bufferLimit) {
			$result["failed"] = true;
			$result["set"][self::BUFFER_KEY] = 0;
		}

		return $result;
	}
}
