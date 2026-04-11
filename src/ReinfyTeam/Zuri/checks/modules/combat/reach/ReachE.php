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

namespace ReinfyTeam\Zuri\checks\modules\combat\reach;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Event;
use pocketmine\player\Player;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\checks\snapshots\CombatSnapshot;
use ReinfyTeam\Zuri\config\CacheData;
use ReinfyTeam\Zuri\config\CheckConstants;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use ReinfyTeam\Zuri\utils\MathUtil;
use function is_numeric;
use function max;
use function min;

/**
 * Detects edge-hit reach abuse using victim bounding box proximity checks.
 */
class ReachE extends Check {
	private const BUFFER_KEY = CacheData::REACH_E_BUFFER;

	/**
	 * Returns the check name.
	 *
	 * @return string Check identifier.
	 */
	public function getName() : string {
		return "Reach";
	}

	/**
	 * Returns the check subtype.
	 *
	 * @return string Check subtype identifier.
	 */
	public function getSubType() : string {
		return "E";
	}

	/**
	 * Processes entity damage events for edge reach evaluation.
	 *
	 * @param Event $event Triggered event.
	 * @throws DiscordWebhookException
	 */
	public function checkJustEvent(Event $event) : void {
		if (!$event instanceof EntityDamageByEntityEvent || $event->isCancelled()) {
			return;
		}

		$victim = $event->getEntity();
		$damager = $event->getDamager();
		if (!$victim instanceof Player || !$damager instanceof Player) {
			return;
		}

		$damagerAPI = PlayerAPI::getAPIPlayer($damager);
		$victimAPI = PlayerAPI::getAPIPlayer($victim);
		if ($this->shouldSkip($damager, $victim, $damagerAPI, $victimAPI)) {
			$this->setBuffer($damagerAPI, max(0, $this->getBuffer($damagerAPI) - 1));
			return;
		}

		$box = $victim->getBoundingBox();
		$edgePingCompensationRaw = $this->getConstant(CheckConstants::REACHE_EDGE_PING_COMPENSATION);
		$edgeReachLimitRaw = $this->getConstant(CheckConstants::REACHE_EDGE_REACH_LIMIT);
		$edgeBufferLimitRaw = $this->getConstant(CheckConstants::REACHE_EDGE_BUFFER_LIMIT);
		$edgePingCompensation = is_numeric($edgePingCompensationRaw) ? (float) $edgePingCompensationRaw : 0.0;
		$edgeReachLimit = is_numeric($edgeReachLimitRaw) ? (float) $edgeReachLimitRaw : 3.15;
		$edgeBufferLimit = is_numeric($edgeBufferLimitRaw) ? (int) $edgeBufferLimitRaw : 3;

		$snapshot = new CombatSnapshot("ReachE", $damager, $damagerAPI, $victim, $victimAPI);
		$snapshot->addCachedData("buffer", $this->getBuffer($damagerAPI));
		$snapshot->addCachedData("victimMinX", $box->minX);
		$snapshot->addCachedData("victimMinY", $box->minY);
		$snapshot->addCachedData("victimMinZ", $box->minZ);
		$snapshot->addCachedData("victimMaxX", $box->maxX);
		$snapshot->addCachedData("victimMaxY", $box->maxY);
		$snapshot->addCachedData("victimMaxZ", $box->maxZ);
		$snapshot->addCachedData("edgePingCompensation", $edgePingCompensation);
		$snapshot->addCachedData("edgeReachLimit", $edgeReachLimit);
		$snapshot->addCachedData("edgeBufferLimit", $edgeBufferLimit);
		$snapshot->validate();
		$this->dispatchAsyncCheck($damager->getName(), $snapshot->build());
	}

	/**
	 * Evaluates an async payload for Reach E violations.
	 *
	 * @param array<string,mixed> $payload Serialized check payload.
	 * @return array<string,mixed> Async decision data.
	 */
	public static function evaluateAsync(array $payload) : array {
		if (!CombatSnapshot::validatePayload(
			$payload,
			"ReachE",
			CombatSnapshot::SCHEMA_VERSION,
			["type", "schemaVersion", "damagerEyeX", "damagerEyeY", "damagerEyeZ", "damagerPing", "cachedData"],
			[
				"damagerEyeX" => [-30000000.0, 30000000.0],
				"damagerEyeY" => [-2048.0, 2048.0],
				"damagerEyeZ" => [-30000000.0, 30000000.0],
				"damagerPing" => [0.0, 60000.0],
			]
		)) {
			return [];
		}

		if (
			!(bool) ($payload["damagerSurvival"] ?? false) ||
			!(bool) ($payload["victimSurvival"] ?? false) ||
			(bool) ($payload["damagerRecentlyCancelled"] ?? false) ||
			(bool) ($payload["victimRecentlyCancelled"] ?? false)
		) {
			return [];
		}

		$cachedData = (array) ($payload["cachedData"] ?? []);
		$eyeXRaw = $payload["damagerEyeX"] ?? 0.0;
		$eyeYRaw = $payload["damagerEyeY"] ?? 0.0;
		$eyeZRaw = $payload["damagerEyeZ"] ?? 0.0;
		$victimMinXRaw = $cachedData["victimMinX"] ?? 0.0;
		$victimMinYRaw = $cachedData["victimMinY"] ?? 0.0;
		$victimMinZRaw = $cachedData["victimMinZ"] ?? 0.0;
		$victimMaxXRaw = $cachedData["victimMaxX"] ?? 0.0;
		$victimMaxYRaw = $cachedData["victimMaxY"] ?? 0.0;
		$victimMaxZRaw = $cachedData["victimMaxZ"] ?? 0.0;
		$damagerPingRaw = $payload["damagerPing"] ?? 0;
		$edgePingCompensationRaw = $cachedData["edgePingCompensation"] ?? 0.0;
		$edgeReachLimitRaw = $cachedData["edgeReachLimit"] ?? 3.15;
		$bufferRaw = $cachedData["buffer"] ?? 0;
		$edgeBufferLimitRaw = $cachedData["edgeBufferLimit"] ?? 3;

		$eyeX = is_numeric($eyeXRaw) ? (float) $eyeXRaw : 0.0;
		$eyeY = is_numeric($eyeYRaw) ? (float) $eyeYRaw : 0.0;
		$eyeZ = is_numeric($eyeZRaw) ? (float) $eyeZRaw : 0.0;
		$victimMinX = is_numeric($victimMinXRaw) ? (float) $victimMinXRaw : 0.0;
		$victimMinY = is_numeric($victimMinYRaw) ? (float) $victimMinYRaw : 0.0;
		$victimMinZ = is_numeric($victimMinZRaw) ? (float) $victimMinZRaw : 0.0;
		$victimMaxX = is_numeric($victimMaxXRaw) ? (float) $victimMaxXRaw : 0.0;
		$victimMaxY = is_numeric($victimMaxYRaw) ? (float) $victimMaxYRaw : 0.0;
		$victimMaxZ = is_numeric($victimMaxZRaw) ? (float) $victimMaxZRaw : 0.0;
		$damagerPing = is_numeric($damagerPingRaw) ? (int) $damagerPingRaw : 0;
		$edgePingCompensation = is_numeric($edgePingCompensationRaw) ? (float) $edgePingCompensationRaw : 0.0;
		$limit = is_numeric($edgeReachLimitRaw) ? (float) $edgeReachLimitRaw : 3.15;
		$buffer = is_numeric($bufferRaw) ? (int) $bufferRaw : 0;
		$edgeBufferLimit = is_numeric($edgeBufferLimitRaw) ? (int) $edgeBufferLimitRaw : 3;

		$closestX = max($victimMinX, min($eyeX, $victimMaxX));
		$closestY = max($victimMinY, min($eyeY, $victimMaxY));
		$closestZ = max($victimMinZ, min($eyeZ, $victimMaxZ));

		$distance = MathUtil::distanceFromComponents(
			$eyeX,
			$eyeY,
			$eyeZ,
			$closestX,
			$closestY,
			$closestZ
		);
		$distance -= $damagerPing * $edgePingCompensation;

		$buffer = $distance > $limit ? $buffer + 1 : max(0, $buffer - 1);

		$result = [
			"set" => [self::BUFFER_KEY => $buffer],
			"debug" => "distance={$distance}, limit={$limit}, buffer={$buffer}",
		];

		if ($buffer >= $edgeBufferLimit) {
			$result["failed"] = true;
			$result["set"][self::BUFFER_KEY] = 0;
		}

		return $result;
	}

	/**
	 * Determines whether edge-reach processing should be skipped.
	 *
	 * @param Player $damager Attacking player.
	 * @param Player $victim Victim player.
	 * @param PlayerAPI $damagerAPI Damager context.
	 * @param PlayerAPI $victimAPI Victim context.
	 * @return bool True when the check should be skipped.
	 */
	private function shouldSkip(Player $damager, Player $victim, PlayerAPI $damagerAPI, PlayerAPI $victimAPI) : bool {
		$maxPingRaw = $this->getConstant(CheckConstants::REACHE_EDGE_MAX_PING);
		$minTeleportTicksRaw = $this->getConstant(CheckConstants::REACHE_EDGE_MIN_TELEPORT_TICKS);
		$minStabilityTicksRaw = $this->getConstant(CheckConstants::REACHE_EDGE_MIN_STABILITY_TICKS);
		$maxPing = is_numeric($maxPingRaw) ? (int) $maxPingRaw : 0;
		$minTeleportTicks = is_numeric($minTeleportTicksRaw) ? (float) $minTeleportTicksRaw : 0.0;
		$minStabilityTicks = is_numeric($minStabilityTicksRaw) ? (float) $minStabilityTicksRaw : 0.0;

		return
			!$damager->isSurvival() ||
			!$victim->isSurvival() ||
			(int) $damagerAPI->getPing() > $maxPing ||
			$damagerAPI->isRecentlyCancelledEvent() ||
			$victimAPI->isRecentlyCancelledEvent() ||
			$damagerAPI->getTeleportTicks() < $minTeleportTicks ||
			$victimAPI->getProjectileAttackTicks() < $minStabilityTicks ||
			$damagerAPI->getProjectileAttackTicks() < $minStabilityTicks ||
			$victimAPI->getBowShotTicks() < $minStabilityTicks ||
			$damagerAPI->getBowShotTicks() < $minStabilityTicks;
	}

	/**
	 * Returns the current edge-reach buffer value.
	 *
	 * @param PlayerAPI $playerAPI Player context.
	 * @return int Current buffer.
	 */
	private function getBuffer(PlayerAPI $playerAPI) : int {
		$bufferRaw = $playerAPI->getExternalData(self::BUFFER_KEY, 0);
		return is_numeric($bufferRaw) ? (int) $bufferRaw : 0;
	}

	/**
	 * Stores the edge-reach buffer value.
	 *
	 * @param PlayerAPI $playerAPI Player context.
	 * @param int $buffer Buffer value to store.
	 */
	private function setBuffer(PlayerAPI $playerAPI, int $buffer) : void {
		$playerAPI->setExternalData(self::BUFFER_KEY, $buffer);
	}
}
