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
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\player\Player;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\config\CacheData;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function is_numeric;
use function is_string;
use function max;
use function microtime;
use function strtolower;

class ItemLerp extends Check {
	private const TYPE = "ItemLerpA";
	private const BUFFER_KEY = CacheData::ITEMLERP_A_BUFFER;
	private const LAST_SWITCH_KEY = CacheData::ITEMLERP_A_LAST_HELD_SWITCH;

	public function getName() : string {
		return "ItemLerp";
	}

	public function getSubType() : string {
		return "A";
	}

	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if (!$event instanceof PlayerItemHeldEvent) {
			return;
		}

		$playerAPI->setExternalData(self::LAST_SWITCH_KEY, microtime(true));
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function checkJustEvent(Event $event) : void {
		if (!$event instanceof EntityDamageByEntityEvent || $event->isCancelled()) {
			return;
		}

		$damager = $event->getDamager();
		$victim = $event->getEntity();
		if (!$damager instanceof Player || !$victim instanceof Player) {
			return;
		}

		$damagerAPI = PlayerAPI::getAPIPlayer($damager);
		if ($this->shouldSkip($damager, $victim, $damagerAPI)) {
			$this->setBuffer($damagerAPI, max(0, $this->getBuffer($damagerAPI) - 1));
			return;
		}

		$attackTicks = $damagerAPI->getAttackTicks();
		$cps = $damagerAPI->getCPS();
		if ($attackTicks > $this->profileFloatConstant("combat-window-ticks", 0.0) || $cps < $this->profileIntConstant("min-cps", 0)) {
			$this->setBuffer($damagerAPI, max(0, $this->getBuffer($damagerAPI) - 1));
			return;
		}

		$lastSwitchRaw = $damagerAPI->getExternalData(self::LAST_SWITCH_KEY, 0.0);
		$lastSwitch = is_numeric($lastSwitchRaw) ? (float) $lastSwitchRaw : 0.0;
		if ($lastSwitch <= 0.0) {
			return;
		}

		$this->dispatchAsyncCheck($damager->getName(), [
			"type" => self::TYPE,
			"lastSwitch" => $lastSwitch,
			"buffer" => $this->getBuffer($damagerAPI),
			"attackTicks" => $attackTicks,
			"cps" => $cps,
			"maxSwitchTicks" => $this->profileFloatConstant("max-switch-ticks", 0.0),
			"bufferLimit" => $this->profileIntConstant("buffer-limit", 3),
		]);
	}

	public static function evaluateAsync(array $payload) : array {
		if (($payload["type"] ?? null) !== self::TYPE) {
			return [];
		}

		$lastSwitchRaw = $payload["lastSwitch"] ?? 0.0;
		$lastSwitch = is_numeric($lastSwitchRaw) ? (float) $lastSwitchRaw : 0.0;
		if ($lastSwitch <= 0.0) {
			return [];
		}

		$switchTicks = (microtime(true) - $lastSwitch) * 20.0;
		$attackTicksRaw = $payload["attackTicks"] ?? 0.0;
		$cpsRaw = $payload["cps"] ?? 0;
		$bufferRaw = $payload["buffer"] ?? 0;
		$attackTicks = is_numeric($attackTicksRaw) ? (float) $attackTicksRaw : 0.0;
		$cps = is_numeric($cpsRaw) ? (int) $cpsRaw : 0;
		$buffer = is_numeric($bufferRaw) ? (int) $bufferRaw : 0;

		$maxSwitchTicksRaw = $payload["maxSwitchTicks"] ?? 0.0;
		$maxSwitchTicks = is_numeric($maxSwitchTicksRaw) ? (float) $maxSwitchTicksRaw : 0.0;
		if ($switchTicks <= $maxSwitchTicks) {
			$buffer++;
		} else {
			$buffer = max(0, $buffer - 1);
		}

		$result = [
			"set" => [self::BUFFER_KEY => $buffer],
			"debug" => "switchTicks={$switchTicks}, attackTicks={$attackTicks}, cps={$cps}, buffer={$buffer}",
		];

		$bufferLimitRaw = $payload["bufferLimit"] ?? 0;
		$bufferLimit = is_numeric($bufferLimitRaw) ? (int) $bufferLimitRaw : 0;
		if ($buffer >= $bufferLimit) {
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
			$damagerAPI->getTeleportTicks() < $this->profileFloatConstant("min-teleport-ticks", 0.0) ||
			$damagerAPI->getHurtTicks() < 8 ||
			(int) $damagerAPI->getPing() > $this->profileIntConstant("max-ping", 0);
	}

	private function getBuffer(PlayerAPI $playerAPI) : int {
		$raw = $playerAPI->getExternalData(self::BUFFER_KEY, 0);
		return is_numeric($raw) ? (int) $raw : 0;
	}

	private function setBuffer(PlayerAPI $playerAPI, int $buffer) : void {
		$playerAPI->setExternalData(self::BUFFER_KEY, $buffer);
	}

	private function profileConstant(string $name) : mixed {
		$default = $this->getConstant($name);
		$profileRaw = self::getData("zuri.check.itemlerp.tuning-presets.active", "default");
		$profile = strtolower(is_string($profileRaw) ? $profileRaw : "default");
		if ($profile === "custom") {
			$profile = "default";
		}
		if ($profile !== "low-latency" && $profile !== "high-latency") {
			return $default;
		}

		return self::getData("zuri.check.itemlerp.tuning-presets." . $profile . "." . $name, $default);
	}

	private function profileFloatConstant(string $name, float $default) : float {
		$raw = $this->profileConstant($name);
		return is_numeric($raw) ? (float) $raw : $default;
	}

	private function profileIntConstant(string $name, int $default) : int {
		$raw = $this->profileConstant($name);
		return is_numeric($raw) ? (int) $raw : $default;
	}
}
