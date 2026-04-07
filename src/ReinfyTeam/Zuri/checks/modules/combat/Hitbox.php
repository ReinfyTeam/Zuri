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

namespace ReinfyTeam\Zuri\checks\modules\combat;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Event;
use pocketmine\player\Player;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\config\CacheData;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use ReinfyTeam\Zuri\utils\MathUtil;
use function max;
use function min;
use function strtolower;

class Hitbox extends Check {
	private const string TYPE = "HitboxA";
	private const string BUFFER_KEY = CacheData::HITBOX_A_BUFFER;

	public function getName() : string {
		return "Hitbox";
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
		$damager = $event->getDamager();
		if (!$victim instanceof Player || !$damager instanceof Player) {
			return;
		}

		$damagerAPI = PlayerAPI::getAPIPlayer($damager);
		if ($this->shouldSkip($damager, $victim, $damagerAPI)) {
			$this->setBuffer($damagerAPI, max(0, $this->getBuffer($damagerAPI) - 1));
			return;
		}

		$attackTicks = $damagerAPI->getAttackTicks();
		if ($attackTicks > (float) $this->profileConstant("combat-window-ticks")) {
			$this->setBuffer($damagerAPI, max(0, $this->getBuffer($damagerAPI) - 1));
			return;
		}

		$eye = $damager->getEyePos();
		$box = $victim->getBoundingBox();
		$distance = MathUtil::distanceFromComponents($eye->x, $eye->y, $eye->z, $victim->getEyePos()->x, $victim->getEyePos()->y, $victim->getEyePos()->z);
		if ($distance < (float) $this->profileConstant("min-distance") || $distance > (float) $this->profileConstant("max-distance")) {
			return;
		}

		$closestX = max($box->minX, min($eye->x, $box->maxX));
		$closestY = max($box->minY, min($eye->y, $box->maxY));
		$closestZ = max($box->minZ, min($eye->z, $box->maxZ));

		$toX = $closestX - $eye->x;
		$toY = $closestY - $eye->y;
		$toZ = $closestZ - $eye->z;
		$toLength = MathUtil::distanceFromComponents(0.0, 0.0, 0.0, $toX, $toY, $toZ);
		if ($toLength < 0.0001) {
			return;
		}

		$dir = $damager->getDirectionVector();
		$alignment = $dir->x * ($toX / $toLength) + $dir->y * ($toY / $toLength) + $dir->z * ($toZ / $toLength);
		$projection = ($toX * $dir->x) + ($toY * $dir->y) + ($toZ * $dir->z);
		$rayX = $eye->x + ($dir->x * $projection);
		$rayY = $eye->y + ($dir->y * $projection);
		$rayZ = $eye->z + ($dir->z * $projection);
		$missDistance = MathUtil::distanceFromComponents($rayX, $rayY, $rayZ, $closestX, $closestY, $closestZ);
		$ping = (int) $damagerAPI->getPing();
		$minDot = (float) $this->profileConstant("min-dot") - min(0.18, $ping * (float) $this->profileConstant("dot-ping-compensation"));
		$missLimit = (float) $this->profileConstant("max-miss-distance") + min(0.45, $ping * (float) $this->profileConstant("miss-ping-compensation"));

		$suspicious = $alignment < $minDot || $missDistance > $missLimit;
		$this->dispatchAsyncCheck($damager->getName(), [
			"type" => self::TYPE,
			"buffer" => $this->getBuffer($damagerAPI),
			"suspicious" => $suspicious,
			"alignment" => $alignment,
			"missDistance" => $missDistance,
			"minDot" => $minDot,
			"missLimit" => $missLimit,
			"attackTicks" => $attackTicks,
			"ping" => $ping,
			"bufferLimit" => (int) $this->profileConstant("buffer-limit"),
		]);
	}

	public static function evaluateAsync(array $payload) : array {
		if (($payload["type"] ?? null) !== self::TYPE) {
			return [];
		}

		$buffer = (int) ($payload["buffer"] ?? 0);
		if ((bool) ($payload["suspicious"] ?? false)) {
			$buffer++;
		} else {
			$buffer = max(0, $buffer - 1);
		}

		$result = [
			"set" => [self::BUFFER_KEY => $buffer],
			"debug" => "alignment=" . (float) ($payload["alignment"] ?? 0.0) . ", missDistance=" . (float) ($payload["missDistance"] ?? 0.0) . ", minDot=" . (float) ($payload["minDot"] ?? 0.0) . ", missLimit=" . (float) ($payload["missLimit"] ?? 0.0) . ", attackTicks=" . (float) ($payload["attackTicks"] ?? 0.0) . ", ping=" . (int) ($payload["ping"] ?? 0) . ", buffer={$buffer}",
		];

		if ($buffer >= (int) ($payload["bufferLimit"] ?? 0)) {
			$result["set"][self::BUFFER_KEY] = 0;
			$result["failed"] = true;
		}

		return $result;
	}

	private function shouldSkip(Player $damager, Player $victim, PlayerAPI $damagerAPI) : bool {
		return
			!$damager->isSurvival() ||
			!$victim->isSurvival() ||
			$damagerAPI->isRecentlyCancelledEvent() ||
			$damagerAPI->getTeleportTicks() < (float) $this->profileConstant("min-teleport-ticks") ||
			(int) $damagerAPI->getPing() > (int) $this->profileConstant("max-ping");
	}

	private function getBuffer(PlayerAPI $playerAPI) : int {
		return (int) $playerAPI->getExternalData(self::BUFFER_KEY, 0);
	}

	private function setBuffer(PlayerAPI $playerAPI, int $buffer) : void {
		$playerAPI->setExternalData(self::BUFFER_KEY, $buffer);
	}

	private function profileConstant(string $name) : mixed {
		$default = $this->getConstant($name);
		$profile = strtolower((string) self::getData("zuri.check.hitbox.tuning-presets.active", "default"));
		if ($profile === "custom") {
			$profile = "default";
		}
		if ($profile !== "low-latency" && $profile !== "high-latency") {
			return $default;
		}

		return self::getData("zuri.check.hitbox.tuning-presets." . $profile . "." . $name, $default);
	}
}
