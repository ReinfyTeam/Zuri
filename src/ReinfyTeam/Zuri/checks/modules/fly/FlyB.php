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
use function max;

class FlyB extends Check {
	private const string BUFFER_KEY = CacheData::FLY_B_BUFFER;

	public function getName() : string {
		return "Fly";
	}

	public function getSubType() : string {
		return "B";
	}

	/**
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
			$snapshot->addCachedData("flags", $packet->flags);
			$snapshot->addCachedData("buffer", (int) $playerAPI->getExternalData(self::BUFFER_KEY, 0));
			$snapshot->addCachedData("bufferLimit", (int) $this->getConstant(CheckConstants::FLYB_PACKET_BUFFER_LIMIT));

			// Dispatch async check with snapshot payload
			$payload = $snapshot->build();
			$this->dispatchAsyncCheck($player->getName(), $payload);
		}
	}

	public static function evaluateAsync(array $payload) : array {
		if (($payload["type"] ?? null) !== "FlyB") {
			return [];
		}

		$cachedData = (array) ($payload["cachedData"] ?? []);
		if (
			(bool) ($cachedData["creative"] ?? false) ||
			(bool) ($cachedData["spectator"] ?? false) ||
			(bool) ($cachedData["allowFlight"] ?? false) ||
			(int) ($payload["teleportTicks"] ?? 0) < 40 ||
			(int) ($payload["teleportCommandTicks"] ?? 0) < 40 ||
			(int) ($payload["hurtTicks"] ?? 0) < 20 ||
			(bool) ($payload["recentlyCancelled"] ?? false)
		) {
			return ["set" => [self::BUFFER_KEY => 0]];
		}

		$flags = (int) ($cachedData["flags"] ?? 0);
		$allowFlightFlag = (($flags >> 9) & 0x01) === 1;
		$flyingFlag = (($flags >> 7) & 0x01) === 1;
		$noclipFlag = (($flags >> 6) & 0x01) === 1;
		$suspicious = $allowFlightFlag || $flyingFlag || $noclipFlag;
		$buffer = (int) ($cachedData["buffer"] ?? 0);
		$buffer = $suspicious ? $buffer + 1 : max(0, $buffer - 1);
		$result = [
			"set" => [self::BUFFER_KEY => $buffer],
			"debug" => "packetFlags={$flags}, allowFlightFlag=" . ($allowFlightFlag ? "1" : "0") . ", flyingFlag=" . ($flyingFlag ? "1" : "0") . ", noclipFlag=" . ($noclipFlag ? "1" : "0") . ", suspicious=" . ($suspicious ? "true" : "false") . ", buffer={$buffer}",
		];
		if ($buffer >= (int) ($cachedData["bufferLimit"] ?? 2)) {
			$result["failed"] = true;
			$result["set"][self::BUFFER_KEY] = 0;
		}

		return $result;
	}
}
