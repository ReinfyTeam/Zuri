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

namespace ReinfyTeam\Zuri\checks\combat\autoclick;

use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function microtime;

class AutoClickB extends Check {
	public function getName() : string {
		return "AutoClick";
	}

	public function getSubType() : string {
		return "B";
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		if ($playerAPI->getPlacingTicks() < 100) {
			return;
		}
		if ($packet instanceof LevelSoundEventPacket) {
			if ($packet->sound === LevelSoundEvent::ATTACK_NODAMAGE) {
				$this->dispatchAsyncCheck($playerAPI->getPlayer()->getName(), [
					"type" => "AutoClickB",
					"placingTicks" => $playerAPI->getPlacingTicks(),
					"ticks" => $playerAPI->getExternalData("clicksTicks2"),
					"lastClick" => $playerAPI->getExternalData("lastClick"),
					"diffTime" => (float) $this->getConstant("diff-time"),
					"diffTicks" => (int) $this->getConstant("diff-ticks"),
					"now" => microtime(true),
				]);
			}
		}
	}

	public static function evaluateAsync(array $payload) : array {
		if (($payload["type"] ?? null) !== "AutoClickB") {
			return [];
		}

		$placingTicks = (int) ($payload["placingTicks"] ?? 0);
		if ($placingTicks < 100) {
			return [];
		}

		$ticks = $payload["ticks"] ?? null;
		$lastClick = $payload["lastClick"] ?? null;
		if ($ticks === null || $lastClick === null) {
			return ["set" => ["clicksTicks2" => 0, "lastClick" => $payload["now"] ?? microtime(true)]];
		}

		$diff = (float) ($payload["now"] ?? microtime(true)) - (float) $lastClick;
		if ($diff > (float) ($payload["diffTime"] ?? 0.0)) {
			$result = ["unset" => ["clicksTicks2", "lastClick"], "debug" => "diff={$diff}, lastClick={$lastClick}, ticks={$ticks}"];
			if ((int) $ticks >= (int) ($payload["diffTicks"] ?? 0)) {
				$result["failed"] = true;
			}
			return $result;
		}

		return ["set" => ["clicksTicks2" => (int) $ticks + 1, "lastClick" => $lastClick], "debug" => "lastClick={$lastClick}, ticks={$ticks}"];
	}
}