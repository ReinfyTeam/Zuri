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

use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\event\Event;
use pocketmine\Server;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\config\CacheData;
use ReinfyTeam\Zuri\config\CheckConstants;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function array_key_exists;

class FastBow extends Check {
	private const string TYPE = "FastBowA";

	public function getName() : string {
		return "FastBow";
	}

	public function getSubType() : string {
		return "A";
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if ($event instanceof EntityShootBowEvent) {
			$tick = Server::getInstance()->getTick();
			$tps = (float) Server::getInstance()->getTicksPerSecond();
			$shootFirstTick = (int) $playerAPI->getExternalData(CacheData::FASTBOW_SHOOT_FIRST_TICK, -1);
			if ($shootFirstTick === -1) {
				$shootFirstTick = $tick - 30;
			}

			$currentHsIndex = (int) $playerAPI->getExternalData(CacheData::FASTBOW_CURRENT_HS_INDEX, 0);
			$hsTimeSum = (float) $playerAPI->getExternalData(CacheData::FASTBOW_HS_TIME_SUM, 0.0);
			$hsTimeList = (array) $playerAPI->getExternalData(CacheData::FASTBOW_HS_TIME_LIST, []);
			$previousIndexValue = array_key_exists($currentHsIndex, $hsTimeList) ? (float) $hsTimeList[$currentHsIndex] : 0.0;

			$this->dispatchAsyncCheck($playerAPI->getPlayer()->getName(), [
				"type" => self::TYPE,
				"tick" => $tick,
				"tps" => $tps,
				"shootFirstTick" => $shootFirstTick,
				"hsTimeSum" => $hsTimeSum,
				"currentHsIndex" => $currentHsIndex,
				"hsTimeList" => $hsTimeList,
				"previousIndexValue" => $previousIndexValue,
				"maxHitTime" => (float) $this->getConstant(CheckConstants::FASTBOW_MAX_HIT_TIME),
			]);
		}
	}

	public static function evaluateAsync(array $payload) : array {
		if (($payload["type"] ?? null) !== self::TYPE) {
			return [];
		}

		$tick = (int) ($payload["tick"] ?? 0);
		$tps = (float) ($payload["tps"] ?? 20.0);
		$shootFirstTick = (int) ($payload["shootFirstTick"] ?? -1);
		$hsTimeSum = (float) ($payload["hsTimeSum"] ?? 0.0);
		$currentHsIndex = (int) ($payload["currentHsIndex"] ?? 0);
		$hsTimeList = (array) ($payload["hsTimeList"] ?? []);
		$previousIndexValue = (float) ($payload["previousIndexValue"] ?? 0.0);

		if ($tps <= 0.0) {
			return [];
		}

		$tickDiff = $tick - $shootFirstTick;
		$delta = $tickDiff / $tps;
		$hsTimeList[$currentHsIndex] = $delta;
		$hsTimeSum = $hsTimeSum - $previousIndexValue + $delta;
		$nextHsIndex = $currentHsIndex >= 5 ? 0 : $currentHsIndex + 1;
		$hsHitTime = $hsTimeSum / 5.0;

		$result = [
			"set" => [
				CacheData::FASTBOW_SHOOT_FIRST_TICK => $shootFirstTick,
				CacheData::FASTBOW_HS_TIME_LIST => $hsTimeList,
				CacheData::FASTBOW_HS_TIME_SUM => $hsTimeSum,
				CacheData::FASTBOW_CURRENT_HS_INDEX => $nextHsIndex,
				CacheData::FASTBOW_HS_HIT_TIME => $hsHitTime,
			],
			"debug" => "tick={$tick}, tickDiff={$tickDiff}, tps={$tps}, shootFirstTick={$shootFirstTick}, hsTimeSum={$hsTimeSum}, currentHsIndex={$currentHsIndex}, delta={$delta}, hsHitTime={$hsHitTime}",
		];

		if ($hsHitTime < (float) ($payload["maxHitTime"] ?? 0.0)) {
			$result["failed"] = true;
		}

		return $result;
	}
}
