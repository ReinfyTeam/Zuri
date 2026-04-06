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

namespace ReinfyTeam\Zuri\checks\fly;

use ReinfyTeam\Zuri\config\CacheData;
use pocketmine\network\mcpe\protocol\DataPacket;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\BlockUtil;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function microtime;

class FlyA extends Check {
	public function getName() : string {
		return "Fly";
	}

	public function getSubType() : string {
		return "A";
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		$player = $playerAPI->getPlayer();
		$this->dispatchAsyncCheck($player->getName(), [
			"type" => "FlyA",
			"attackTicks" => $playerAPI->getAttackTicks(),
			"onlineTime" => $playerAPI->getOnlineTime(),
			"jumpTicks" => $playerAPI->getJumpTicks(),
			"inWeb" => $playerAPI->isInWeb(),
			"onGround" => $playerAPI->isOnGround(),
			"onAdhesion" => $playerAPI->isOnAdhesion(),
			"allowFlight" => $player->getAllowFlight(),
			"noClientPredictions" => $player->hasNoClientPredictions(),
			"survival" => $player->isSurvival(),
			"chunkLoaded" => $playerAPI->isCurrentChunkIsLoaded(),
			"groundSolid" => BlockUtil::isGroundSolid($player),
			"gliding" => $playerAPI->isGliding(),
			"recentlyCancelled" => $playerAPI->isRecentlyCancelledEvent(),
			"currentY" => (int) $player->getLocation()->getY(),
			"lastYNoGround" => $playerAPI->getExternalData(CacheData::FLY_A_LAST_Y_NO_GROUND),
			"lastTime" => $playerAPI->getExternalData(CacheData::FLY_A_LAST_TIME),
			"now" => microtime(true),
			"maxGroundDiff" => (float) $this->getConstant("max-ground-diff"),
		]);
	}

	public static function evaluateAsync(array $payload) : array {
		if (($payload["type"] ?? null) !== "FlyA") {
			return [];
		}

		if (
			(int) ($payload["attackTicks"] ?? 0) < 40 ||
			(float) ($payload["onlineTime"] ?? 0) <= 30 ||
			(int) ($payload["jumpTicks"] ?? 0) < 40 ||
			(bool) ($payload["inWeb"] ?? false) ||
			(bool) ($payload["onGround"] ?? false) ||
			(bool) ($payload["onAdhesion"] ?? false) ||
			(bool) ($payload["allowFlight"] ?? false) ||
			(bool) ($payload["noClientPredictions"] ?? false) ||
			!(bool) ($payload["survival"] ?? false) ||
			!(bool) ($payload["chunkLoaded"] ?? false) ||
			(bool) ($payload["groundSolid"] ?? false) ||
			(bool) ($payload["gliding"] ?? false) ||
			(bool) ($payload["recentlyCancelled"] ?? false)
		) {
			return ["unset" => [CacheData::FLY_A_LAST_Y_NO_GROUND, CacheData::FLY_A_LAST_TIME]];
		}

		$lastYNoGround = $payload["lastYNoGround"] ?? null;
		$lastTime = $payload["lastTime"] ?? null;
		if ($lastYNoGround !== null && $lastTime !== null) {
			$diff = (float) ($payload["now"] ?? microtime(true)) - (float) $lastTime;
			$result = ["debug" => "diff={$diff}, lastTime={$lastTime}, lastYNoGround={$lastYNoGround}"];
			if ($diff > (float) ($payload["maxGroundDiff"] ?? 0.0) && (int) ($payload["currentY"] ?? 0) === (int) $lastYNoGround) {
				$result["failed"] = true;
			}
			$result["unset"] = [CacheData::FLY_A_LAST_Y_NO_GROUND, CacheData::FLY_A_LAST_TIME];
			return $result;
		}

		return ["set" => [CacheData::FLY_A_LAST_Y_NO_GROUND => (int) ($payload["currentY"] ?? 0), CacheData::FLY_A_LAST_TIME => (float) ($payload["now"] ?? microtime(true))]];
	}
}