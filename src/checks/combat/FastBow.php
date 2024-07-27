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

namespace ReinfyTeam\Zuri\checks\combat;

use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\event\Event;
use pocketmine\Server;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function array_merge;

class FastBow extends Check {
	public function getName() : string {
		return "FastBow";
	}

	public function getSubType() : string {
		return "A";
	}

	public function maxViolations() : int {
		return 3;
	}

    /**
     * @throws DiscordWebhookException
     */
    public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if ($event instanceof EntityShootBowEvent) {
			$tick = (double) Server::getInstance()->getTick();
            $tps = Server::getInstance()->getTicksPerSecond();
			$shootFirstTick = $playerAPI->getExternalData("shootFirstTickA");
			$hsTimeSum = $playerAPI->getExternalData("hsTimeSum") ?? 0;
			$currentHsIndex = $playerAPI->getExternalData("currentHsIndex") ?? 0;
			$hsTimeList = $playerAPI->getExternalData("hsTimeList") ?? [];

            if ($shootFirstTick == -1) {
				$playerAPI->setExternalData("shootFirstTickA", $tick - 30);
			}

			$tickDiff = $tick - $shootFirstTick; // server ticks since last hit
			$delta = $tickDiff / $tps; // seconds since last hit
			$playerAPI->setExternalData("hsTimeList", array_merge($hsTimeList, [$currentHsIndex => $delta])); // merge the new array to a new another one..
			$hsTimeList = $playerAPI->getExternalData("hsTimeList"); // update again the list..
			$playerAPI->setExternalData("hsTimeSum", $hsTimeSum - $hsTimeList[$currentHsIndex] + $delta);
			$playerAPI->setExternalData("currentHsIndex", $currentHsIndex + 1);
			if ($currentHsIndex >= 5) {
				$playerAPI->setExternalData("currentHsIndex", 0);
			}
			$playerAPI->setExternalData("hsHitTime", $hsTimeSum / 5);
			$hsHitTime = $playerAPI->getExternalData("hsHitTime");
			$this->debug($playerAPI, "tick=$tick, tickDiff=$tickDiff, tps=$tps, shootFirstTick=$shootFirstTick, hsTimeSum=$hsTimeSum, currentHsIndex=$currentHsIndex, delta=$delta, hsHitTime=$hsHitTime");
			if ($hsHitTime < $this->getConstant("max-hit-time")) { // idk how i made this..
				$this->failed($playerAPI);
			}
		}
	}
}
