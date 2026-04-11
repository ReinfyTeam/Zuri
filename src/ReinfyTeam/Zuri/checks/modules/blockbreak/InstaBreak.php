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

namespace ReinfyTeam\Zuri\checks\modules\blockbreak;

use pocketmine\block\BlockTypeIds;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\Event;
use pocketmine\event\player\PlayerInteractEvent;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\config\CacheData;
use ReinfyTeam\Zuri\player\PlayerAPI;
use function ceil;
use function floor;
use function max;
use function microtime;

/**
 * Detects players attempting to break blocks faster than expected.
 */
class InstaBreak extends Check {
	/**
	 * Gets the check name.
	 */
	public function getName() : string {
		return "InstaBreak";
	}

	/**
	 * Gets the check subtype identifier.
	 */
	public function getSubType() : string {
		return "A";
	}

	/**
	 * Processes interact and break events for instant-break detection.
	 *
	 * @param Event $event Triggered event instance.
	 * @param PlayerAPI $playerAPI Player state wrapper.
	 */
	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		$breakTimes = $playerAPI->getExternalData(CacheData::INSTABREAK_BREAK_TIMES);
		if ($event instanceof PlayerInteractEvent) {
			if ($event->getAction() === PlayerInteractEvent::LEFT_CLICK_BLOCK) {
				$playerAPI->setExternalData(CacheData::INSTABREAK_BREAK_TIMES, floor(microtime(true) * 20));
			}
		}
		if ($event instanceof BlockBreakEvent) {
			if (!$event->getInstaBreak()) {
				if ($breakTimes === null) {
					$event->cancel();
					return;
				}
				if (!$playerAPI->getPlayer()->isConnected() || !$playerAPI->getPlayer()->isOnline()) {
					return;
				}
				$target = $event->getBlock();
				$isBamboo = $target->getTypeId() === BlockTypeIds::BAMBOO;

				$item = $event->getItem();
				$expectedTime = ceil($target->getBreakInfo()->getBreakTime($item) * 20);
				if (($haste = $playerAPI->getPlayer()->getEffects()->get(VanillaEffects::HASTE())) !== null) {
					$expectedTime *= 1 - (0.2 * $haste->getEffectLevel());
				}
				if (($miningFatigue = $playerAPI->getPlayer()->getEffects()->get(VanillaEffects::MINING_FATIGUE())) !== null) {
					$expectedTime *= 1 + (0.3 * $miningFatigue->getEffectLevel());
				}
				$expectedTime -= 1;
				if ($isBamboo) {
					$expectedTime = max(2, $expectedTime - 2);
				}
				$actualTime = ceil(microtime(true) * 20) - $breakTimes;
				if ($actualTime < $expectedTime) {
					$this->dispatchAsyncDecision($playerAPI, true);
					$event->cancel();
					return;
				}
				$this->debug($playerAPI, "expectedTime=$expectedTime, hasMiningFatugue=" . $playerAPI->getPlayer()->getEffects()->has(VanillaEffects::MINING_FATIGUE()) . ", expectedTime=$expectedTime, actualTime=$actualTime");
				$playerAPI->unsetExternalData(CacheData::INSTABREAK_BREAK_TIMES);
			}
		}
	}
}
