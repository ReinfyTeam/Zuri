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

namespace ReinfyTeam\Zuri\checks\modules\combat\velocity;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Event;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\player\Player;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\config\CacheData;
use ReinfyTeam\Zuri\config\CheckConstants;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use ReinfyTeam\Zuri\utils\MathUtil;
use function is_float;
use function is_int;
use function is_numeric;
use function max;
use function microtime;

class VelocityA extends Check {
	private const string TYPE = "VelocityA";
	private const string HIT_AT_KEY = CacheData::VELOCITY_A_HIT_AT;
	private const string BUFFER_KEY = CacheData::VELOCITY_A_BUFFER;

	public function getName() : string {
		return "Velocity";
	}

	public function getSubType() : string {
		return "A";
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function checkJustEvent(Event $event) : void {
		if (!$event instanceof EntityDamageByEntityEvent || $event->isCancelled()) {
			return;
		}

		$victim = $event->getEntity();
		if (!$victim instanceof Player) {
			return;
		}

		$victimAPI = PlayerAPI::getAPIPlayer($victim);
		$victimAPI->setExternalData(self::HIT_AT_KEY, microtime(true));
		$victimAPI->setExternalData(self::BUFFER_KEY, 0);
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if (!$event instanceof PlayerMoveEvent) {
			return;
		}

		$hitAt = $playerAPI->getExternalData(self::HIT_AT_KEY);
		if (!is_float($hitAt) && !is_int($hitAt)) {
			return;
		}

		$elapsedTicks = MathUtil::ticksSince((float) $hitAt);
		$maxObserveTicksRaw = $this->getConstant(CheckConstants::VELOCITYA_MAX_OBSERVE_TICKS);
		$startObserveTicksRaw = $this->getConstant(CheckConstants::VELOCITYA_START_OBSERVE_TICKS);
		$maxPingRaw = $this->getConstant(CheckConstants::VELOCITYA_MAX_PING);
		$minResponseDistanceSquaredRaw = $this->getConstant(CheckConstants::VELOCITYA_MIN_RESPONSE_DISTANCE_SQUARED);
		$bufferLimitRaw = $this->getConstant(CheckConstants::VELOCITYA_BUFFER_LIMIT);
		$maxObserveTicks = is_numeric($maxObserveTicksRaw) ? (float) $maxObserveTicksRaw : 0.0;
		$startObserveTicks = is_numeric($startObserveTicksRaw) ? (float) $startObserveTicksRaw : 0.0;
		$maxPing = is_numeric($maxPingRaw) ? (int) $maxPingRaw : 0;
		$minResponseDistanceSquared = is_numeric($minResponseDistanceSquaredRaw) ? (float) $minResponseDistanceSquaredRaw : 0.0;
		$bufferLimit = is_numeric($bufferLimitRaw) ? (int) $bufferLimitRaw : 0;

		if ($elapsedTicks > $maxObserveTicks) {
			$this->resetState($playerAPI);
			return;
		}

		if ($elapsedTicks < $startObserveTicks) {
			return;
		}

		$player = $playerAPI->getPlayer();
		if (
			!$player->isSurvival() ||
			!$playerAPI->isCurrentChunkIsLoaded() ||
			$player->getAllowFlight() ||
			$player->isFlying() ||
			$player->hasNoClientPredictions() ||
			$playerAPI->isInLiquid() ||
			$playerAPI->isOnIce() ||
			$playerAPI->isOnStairs() ||
			$playerAPI->isOnAdhesion() ||
			$playerAPI->isInWeb() ||
			$playerAPI->isInBoundingBox() ||
			$playerAPI->getTeleportTicks() < 20 ||
			$playerAPI->isRecentlyCancelledEvent() ||
			(int) $playerAPI->getPing() > $maxPing
		) {
			$this->resetState($playerAPI);
			return;
		}

		$moveXZ = MathUtil::XZDistanceSquared($event->getFrom(), $event->getTo());
		$bufferRaw = $playerAPI->getExternalData(self::BUFFER_KEY, 0);
		$buffer = is_numeric($bufferRaw) ? (int) $bufferRaw : 0;
		$this->dispatchAsyncCheck($playerAPI->getPlayer()->getName(), [
			"type" => self::TYPE,
			"elapsedTicks" => $elapsedTicks,
			"moveXZ" => $moveXZ,
			"buffer" => $buffer,
			"minResponseDistanceSquared" => $minResponseDistanceSquared,
			"bufferLimit" => $bufferLimit,
		]);
	}

	public static function evaluateAsync(array $payload) : array {
		if (($payload["type"] ?? null) !== self::TYPE) {
			return [];
		}

		$moveXZRaw = $payload["moveXZ"] ?? 0.0;
		$bufferRaw = $payload["buffer"] ?? 0;
		$minResponseDistanceSquaredRaw = $payload["minResponseDistanceSquared"] ?? 0.0;
		$elapsedTicksRaw = $payload["elapsedTicks"] ?? 0.0;
		$bufferLimitRaw = $payload["bufferLimit"] ?? 0;
		$moveXZ = is_numeric($moveXZRaw) ? (float) $moveXZRaw : 0.0;
		$buffer = is_numeric($bufferRaw) ? (int) $bufferRaw : 0;
		$minResponseDistanceSquared = is_numeric($minResponseDistanceSquaredRaw) ? (float) $minResponseDistanceSquaredRaw : 0.0;
		$elapsedTicks = is_numeric($elapsedTicksRaw) ? (float) $elapsedTicksRaw : 0.0;
		$bufferLimit = is_numeric($bufferLimitRaw) ? (int) $bufferLimitRaw : 0;
		if ($moveXZ < $minResponseDistanceSquared) {
			$buffer++;
		} else {
			$buffer = max(0, $buffer - 1);
		}

		$result = [
			"set" => [self::BUFFER_KEY => $buffer],
			"debug" => "elapsedTicks={$elapsedTicks}, moveXZ={$moveXZ}, buffer={$buffer}",
		];

		if ($buffer >= $bufferLimit) {
			$result["set"][self::BUFFER_KEY] = 0;
			$result["unset"] = [self::HIT_AT_KEY];
			$result["failed"] = true;
		}

		return $result;
	}

	private function resetState(PlayerAPI $playerAPI) : void {
		$playerAPI->unsetExternalData(self::HIT_AT_KEY);
		$playerAPI->setExternalData(self::BUFFER_KEY, 0);
	}
}
