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

use ReinfyTeam\Zuri\config\CheckConstants;
use ReinfyTeam\Zuri\config\CacheData;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\BlockUtil;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function abs;
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
		if (!$packet instanceof PlayerAuthInputPacket) {
			return;
		}

		$player = $playerAPI->getPlayer();
		$this->dispatchAsyncCheck($player->getName(), [
			"type" => "FlyA",
			"attackTicks" => $playerAPI->getAttackTicks(),
			"onlineTime" => $playerAPI->getOnlineTime(),
			"jumpTicks" => $playerAPI->getJumpTicks(),
			"teleportTicks" => $playerAPI->getTeleportTicks(),
			"teleportCommandTicks" => $playerAPI->getTeleportCommandTicks(),
			"hurtTicks" => $playerAPI->getHurtTicks(),
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
			"motionX" => abs($playerAPI->getMotion()->getX()),
			"motionZ" => abs($playerAPI->getMotion()->getZ()),
			"currentY" => (float) $player->getLocation()->getY(),
			"lastYNoGround" => $playerAPI->getExternalData(CacheData::FLY_A_LAST_Y_NO_GROUND),
			"lastTime" => $playerAPI->getExternalData(CacheData::FLY_A_LAST_TIME),
			"now" => microtime(true),
			"maxGroundDiff" => (float) $this->getConstant(CheckConstants::FLYA_MAX_GROUND_DIFF),
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
			(int) ($payload["teleportTicks"] ?? 0) < 60 ||
			(int) ($payload["teleportCommandTicks"] ?? 0) < 60 ||
			(int) ($payload["hurtTicks"] ?? 0) < 20 ||
			(bool) ($payload["inWeb"] ?? false) ||
			(bool) ($payload["onGround"] ?? false) ||
			(bool) ($payload["onAdhesion"] ?? false) ||
			(bool) ($payload["allowFlight"] ?? false) ||
			(bool) ($payload["noClientPredictions"] ?? false) ||
			!(bool) ($payload["survival"] ?? false) ||
			!(bool) ($payload["chunkLoaded"] ?? false) ||
			(bool) ($payload["groundSolid"] ?? false) ||
			(bool) ($payload["gliding"] ?? false) ||
			(float) ($payload["motionX"] ?? 0.0) > 0.11 ||
			(float) ($payload["motionZ"] ?? 0.0) > 0.11 ||
			(bool) ($payload["recentlyCancelled"] ?? false)
		) {
			return ["unset" => [CacheData::FLY_A_LAST_Y_NO_GROUND, CacheData::FLY_A_LAST_TIME]];
		}

		$lastYNoGround = $payload["lastYNoGround"] ?? null;
		$lastTime = $payload["lastTime"] ?? null;
		if ($lastYNoGround !== null && $lastTime !== null) {
			$diff = (float) ($payload["now"] ?? microtime(true)) - (float) $lastTime;
			$result = ["debug" => "diff={$diff}, lastTime={$lastTime}, lastYNoGround={$lastYNoGround}"];
			$currentY = (float) ($payload["currentY"] ?? 0.0);
			if ($diff > (float) ($payload["maxGroundDiff"] ?? 0.0) && abs($currentY - (float) $lastYNoGround) <= 0.001) {
				$result["failed"] = true;
			}
			$result["unset"] = [CacheData::FLY_A_LAST_Y_NO_GROUND, CacheData::FLY_A_LAST_TIME];
			return $result;
		}

		return ["set" => [CacheData::FLY_A_LAST_Y_NO_GROUND => (float) ($payload["currentY"] ?? 0.0), CacheData::FLY_A_LAST_TIME => (float) ($payload["now"] ?? microtime(true))]];
	}
}