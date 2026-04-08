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

use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\checks\snapshots\MovementSnapshot;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\BlockUtil;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function abs;

class WrongPitch extends Check {
	public function getName() : string {
		return "WrongPitch";
	}

	public function getSubType() : string {
		return "A";
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		if ($packet instanceof PlayerAuthInputPacket) {
			$player = $playerAPI->getPlayer();
			$snapshot = new MovementSnapshot("WrongPitch", $player, $playerAPI);
			$snapshot->setEnvironmentState(
				BlockUtil::isGroundSolid($player),
				$playerAPI->isCurrentChunkIsLoaded(),
				$playerAPI->isRecentlyCancelledEvent()
			);

			// Add WrongPitch-specific cached data
			$snapshot->addCachedData("pitch", abs($packet->getPitch()));

			$snapshot->validate();

			// Dispatch async check with snapshot payload
			$payload = $snapshot->build();
			$this->dispatchAsyncCheck($player->getName(), $payload);
		}
	}

	public static function evaluateAsync(array $payload) : array {
		if (
			($payload["type"] ?? null) !== "WrongPitch" ||
			(int) ($payload["schemaVersion"] ?? 0) !== \ReinfyTeam\Zuri\checks\snapshots\MovementSnapshot::SCHEMA_VERSION
		) {
			return [];
		}

		$cachedData = (array) ($payload["cachedData"] ?? []);
		if ((int) ($payload["teleportTicks"] ?? 0) > 100 && (float) ($cachedData["pitch"] ?? 0) > 90) {
			return ["failed" => true];
		}

		return [];
	}
}