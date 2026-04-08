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
use function max;
use function min;

class ReachE extends Check {
	private const string BUFFER_KEY = CacheData::REACH_E_BUFFER;

	public function getName() : string {
		return "Reach";
	}

	public function getSubType() : string {
		return "E";
	}

	/**
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
		$snapshot = new CombatSnapshot("ReachE", $damager, $damagerAPI, $victim, $victimAPI);
		$snapshot->addCachedData("buffer", $this->getBuffer($damagerAPI));
		$snapshot->addCachedData("victimMinX", $box->minX);
		$snapshot->addCachedData("victimMinY", $box->minY);
		$snapshot->addCachedData("victimMinZ", $box->minZ);
		$snapshot->addCachedData("victimMaxX", $box->maxX);
		$snapshot->addCachedData("victimMaxY", $box->maxY);
		$snapshot->addCachedData("victimMaxZ", $box->maxZ);
		$snapshot->addCachedData("edgePingCompensation", (float) $this->getConstant(CheckConstants::REACHE_EDGE_PING_COMPENSATION));
		$snapshot->addCachedData("edgeReachLimit", (float) $this->getConstant(CheckConstants::REACHE_EDGE_REACH_LIMIT));
		$snapshot->addCachedData("edgeBufferLimit", (int) $this->getConstant(CheckConstants::REACHE_EDGE_BUFFER_LIMIT));
		$snapshot->validate();
		$this->dispatchAsyncCheck($damager->getName(), $snapshot->build());
	}

	public static function evaluateAsync(array $payload) : array {
		if (
			($payload["type"] ?? null) !== "ReachE" ||
			(int) ($payload["schemaVersion"] ?? 0) !== \ReinfyTeam\Zuri\checks\snapshots\CombatSnapshot::SCHEMA_VERSION
		) {
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
		$eyeX = (float) ($payload["damagerEyeX"] ?? 0.0);
		$eyeY = (float) ($payload["damagerEyeY"] ?? 0.0);
		$eyeZ = (float) ($payload["damagerEyeZ"] ?? 0.0);

		$closestX = max((float) ($cachedData["victimMinX"] ?? 0.0), min($eyeX, (float) ($cachedData["victimMaxX"] ?? 0.0)));
		$closestY = max((float) ($cachedData["victimMinY"] ?? 0.0), min($eyeY, (float) ($cachedData["victimMaxY"] ?? 0.0)));
		$closestZ = max((float) ($cachedData["victimMinZ"] ?? 0.0), min($eyeZ, (float) ($cachedData["victimMaxZ"] ?? 0.0)));

		$distance = MathUtil::distanceFromComponents(
			$eyeX,
			$eyeY,
			$eyeZ,
			$closestX,
			$closestY,
			$closestZ
		);
		$distance -= (int) ($payload["damagerPing"] ?? 0) * (float) ($cachedData["edgePingCompensation"] ?? 0.0);
		$limit = (float) ($cachedData["edgeReachLimit"] ?? 3.15);

		$buffer = (int) ($cachedData["buffer"] ?? 0);
		$buffer = $distance > $limit ? $buffer + 1 : max(0, $buffer - 1);

		$result = [
			"set" => [self::BUFFER_KEY => $buffer],
			"debug" => "distance={$distance}, limit={$limit}, buffer={$buffer}",
		];

		if ($buffer >= (int) ($cachedData["edgeBufferLimit"] ?? 3)) {
			$result["failed"] = true;
			$result["set"][self::BUFFER_KEY] = 0;
		}

		return $result;
	}

	private function shouldSkip(Player $damager, Player $victim, PlayerAPI $damagerAPI, PlayerAPI $victimAPI) : bool {
		return
			!$damager->isSurvival() ||
			!$victim->isSurvival() ||
			(int) $damagerAPI->getPing() > (int) $this->getConstant(CheckConstants::REACHE_EDGE_MAX_PING) ||
			$damagerAPI->isRecentlyCancelledEvent() ||
			$victimAPI->isRecentlyCancelledEvent() ||
			$damagerAPI->getTeleportTicks() < (float) $this->getConstant(CheckConstants::REACHE_EDGE_MIN_TELEPORT_TICKS) ||
			$victimAPI->getProjectileAttackTicks() < (float) $this->getConstant(CheckConstants::REACHE_EDGE_MIN_STABILITY_TICKS) ||
			$damagerAPI->getProjectileAttackTicks() < (float) $this->getConstant(CheckConstants::REACHE_EDGE_MIN_STABILITY_TICKS) ||
			$victimAPI->getBowShotTicks() < (float) $this->getConstant(CheckConstants::REACHE_EDGE_MIN_STABILITY_TICKS) ||
			$damagerAPI->getBowShotTicks() < (float) $this->getConstant(CheckConstants::REACHE_EDGE_MIN_STABILITY_TICKS);
	}

	private function getBuffer(PlayerAPI $playerAPI) : int {
		return (int) $playerAPI->getExternalData(self::BUFFER_KEY, 0);
	}

	private function setBuffer(PlayerAPI $playerAPI, int $buffer) : void {
		$playerAPI->setExternalData(self::BUFFER_KEY, $buffer);
	}
}