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
use function is_numeric;
use function is_string;
use function max;
use function min;
use function strtolower;

/**
 * Detects attacks that target outside a victim's effective hitbox.
 */
class Hitbox extends Check {
	private const TYPE = "HitboxA";
	private const BUFFER_KEY = CacheData::HITBOX_A_BUFFER;

	/**
	 * Gets the check name.
	 */
	public function getName() : string {
		return "Hitbox";
	}

	/**
	 * Gets the check subtype identifier.
	 */
	public function getSubType() : string {
		return "A";
	}

	/**
	 * Handles combat events and dispatches Hitbox checks.
	 *
	 * @param Event $event Triggered event instance.
	 *
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
		if ($attackTicks > $this->profileFloatConstant("combat-window-ticks", 0.0)) {
			$this->setBuffer($damagerAPI, max(0, $this->getBuffer($damagerAPI) - 1));
			return;
		}

		$eye = $damager->getEyePos();
		$box = $victim->getBoundingBox();
		$distance = MathUtil::distanceFromComponents($eye->x, $eye->y, $eye->z, $victim->getEyePos()->x, $victim->getEyePos()->y, $victim->getEyePos()->z);
		$minDistance = $this->profileFloatConstant("min-distance", 0.0);
		$maxDistance = $this->profileFloatConstant("max-distance", 0.0);
		if ($distance < $minDistance || $distance > $maxDistance) {
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
		$minDot = $this->profileFloatConstant("min-dot", 0.0) - min(0.18, $ping * $this->profileFloatConstant("dot-ping-compensation", 0.0));
		$missLimit = $this->profileFloatConstant("max-miss-distance", 0.0) + min(0.45, $ping * $this->profileFloatConstant("miss-ping-compensation", 0.0));

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
			"bufferLimit" => $this->profileIntConstant("buffer-limit", 3),
		]);
	}

	/**
	 * Evaluates async payload for Hitbox violations.
	 *
	 * @param array<string,mixed> $payload Serialized check context.
	 *
	 * @return array<string,mixed>
	 */
	public static function evaluateAsync(array $payload) : array {
    // Thread-safe: execute in async worker thread only; use only $payload (no Player objects)
    if (\pocketmine\thread\Thread::getCurrentThreadId() === 0) {
        throw new \RuntimeException("evaluateAsync must not be called on the main thread");
    }
		if (($payload["type"] ?? null) !== self::TYPE) {
			return [];
		}

		$bufferRaw = $payload["buffer"] ?? 0;
		$buffer = is_numeric($bufferRaw) ? (int) $bufferRaw : 0;
		if ((bool) ($payload["suspicious"] ?? false)) {
			$buffer++;
		} else {
			$buffer = max(0, $buffer - 1);
		}

		$alignmentRaw = $payload["alignment"] ?? 0.0;
		$missDistanceRaw = $payload["missDistance"] ?? 0.0;
		$minDotRaw = $payload["minDot"] ?? 0.0;
		$missLimitRaw = $payload["missLimit"] ?? 0.0;
		$attackTicksRaw = $payload["attackTicks"] ?? 0.0;
		$pingRaw = $payload["ping"] ?? 0;
		$alignment = is_numeric($alignmentRaw) ? (float) $alignmentRaw : 0.0;
		$missDistance = is_numeric($missDistanceRaw) ? (float) $missDistanceRaw : 0.0;
		$minDot = is_numeric($minDotRaw) ? (float) $minDotRaw : 0.0;
		$missLimit = is_numeric($missLimitRaw) ? (float) $missLimitRaw : 0.0;
		$attackTicks = is_numeric($attackTicksRaw) ? (float) $attackTicksRaw : 0.0;
		$ping = is_numeric($pingRaw) ? (int) $pingRaw : 0;

		$result = [
			"set" => [self::BUFFER_KEY => $buffer],
			"debug" => "alignment={$alignment}, missDistance={$missDistance}, minDot={$minDot}, missLimit={$missLimit}, attackTicks={$attackTicks}, ping={$ping}, buffer={$buffer}",
		];

		$bufferLimitRaw = $payload["bufferLimit"] ?? 0;
		$bufferLimit = is_numeric($bufferLimitRaw) ? (int) $bufferLimitRaw : 0;
		if ($buffer >= $bufferLimit) {
			$result["set"][self::BUFFER_KEY] = 0;
			$result["failed"] = true;
		}

		return $result;
	}

	/**
	 * Determines whether current context should skip Hitbox checks.
	 *
	 * @param Player $damager Attacking player.
	 * @param Player $victim Damaged player.
	 * @param PlayerAPI $damagerAPI Attacker API wrapper.
	 */
	private function shouldSkip(Player $damager, Player $victim, PlayerAPI $damagerAPI) : bool {
		return
			!$damager->isSurvival() ||
			!$victim->isSurvival() ||
			$damagerAPI->isRecentlyCancelledEvent() ||
			$damagerAPI->getTeleportTicks() < $this->profileFloatConstant("min-teleport-ticks", 0.0) ||
			(int) $damagerAPI->getPing() > $this->profileIntConstant("max-ping", 0);
	}

	/**
	 * Gets the current hitbox buffer value.
	 *
	 * @param PlayerAPI $playerAPI Player state wrapper.
	 */
	private function getBuffer(PlayerAPI $playerAPI) : int {
		$raw = $playerAPI->getExternalData(self::BUFFER_KEY, 0);
		return is_numeric($raw) ? (int) $raw : 0;
	}

	/**
	 * Stores the hitbox buffer value.
	 *
	 * @param PlayerAPI $playerAPI Player state wrapper.
	 * @param int $buffer Buffer value to persist.
	 */
	private function setBuffer(PlayerAPI $playerAPI, int $buffer) : void {
		$playerAPI->setExternalData(self::BUFFER_KEY, $buffer);
	}

	/**
	 * Reads a profiling constant with prefixed key support.
	 *
	 * @param string $name Constant key suffix.
	 */
	private function profileConstant(string $name) : mixed {
		$default = $this->getConstant($name);
		$profileRaw = self::getData("zuri.check.hitbox.tuning-presets.active", "default");
		$profile = strtolower(is_string($profileRaw) ? $profileRaw : "default");
		if ($profile === "custom") {
			$profile = "default";
		}
		if ($profile !== "low-latency" && $profile !== "high-latency") {
			return $default;
		}

		return self::getData("zuri.check.hitbox.tuning-presets." . $profile . "." . $name, $default);
	}

	/**
	 * Reads a profiling constant as float with fallback.
	 *
	 * @param string $name Constant key suffix.
	 * @param float $default Default value.
	 */
	private function profileFloatConstant(string $name, float $default) : float {
		$raw = $this->profileConstant($name);
		return is_numeric($raw) ? (float) $raw : $default;
	}

	/**
	 * Reads a profiling constant as integer with fallback.
	 *
	 * @param string $name Constant key suffix.
	 * @param int $default Default value.
	 */
	private function profileIntConstant(string $name, int $default) : int {
		$raw = $this->profileConstant($name);
		return is_numeric($raw) ? (int) $raw : $default;
	}
}

