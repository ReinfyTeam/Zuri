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

namespace ReinfyTeam\Zuri\checks\modules\chat;

use pocketmine\event\Event;
use pocketmine\event\player\PlayerChatEvent;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\config\CacheData;
use ReinfyTeam\Zuri\config\CheckConstants;
use ReinfyTeam\Zuri\lang\Lang;
use ReinfyTeam\Zuri\lang\LangKeys;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function is_numeric;
use function microtime;

/**
 * Detects repeated chat messages sent in short intervals.
 */
class SpamA extends Check {
	/**
	 * Gets the check name.
	 */
	public function getName() : string {
		return "Spam";
	}

	/**
	 * Gets the check subtype identifier.
	 */
	public function getSubType() : string {
		return "A";
	}

	/**
	 * Handles chat events and evaluates spam behavior.
	 *
	 * @param Event $event Triggered event instance.
	 * @param PlayerAPI $playerAPI Player state wrapper.
	 *
	 * @throws DiscordWebhookException
	 */
	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if ($event instanceof PlayerChatEvent) {
			$chatTickRaw = $playerAPI->getExternalData(CacheData::SPAM_A_TICK);
			$chatTick = is_numeric($chatTickRaw) ? (float) $chatTickRaw : null;
			if (!$playerAPI->getPlayer()->isConnected() || !$playerAPI->getPlayer()->isOnline()) {
				return;
			}
			$violationChatRaw = $playerAPI->getExternalData(CacheData::SPAM_A_VIOLATION);
			$violationChat = is_numeric($violationChatRaw) ? (int) $violationChatRaw : null;
			if (!$event->isCancelled()) {
				if ($chatTick !== null || $violationChat !== null) {
					$diff = microtime(true) - ($chatTick ?? microtime(true));
					if ($diff <= self::getData(self::CHAT_SPAM_DELAY)) {
						$maxViolationRateRaw = $this->getConstant(CheckConstants::SPAMA_MAX_VIOLATION_RATE);
						$maxViolationRate = is_numeric($maxViolationRateRaw) ? (int) $maxViolationRateRaw : 0;
						if (($violationChat ?? 0) <= $maxViolationRate) {
							$playerAPI->getPlayer()->sendMessage($this->replaceText($playerAPI, Lang::raw(LangKeys::CHAT_SPAM_TEXT), $this->getName(), $this->getSubType()));
							$playerAPI->setExternalData(CacheData::SPAM_A_TICK, microtime(true));
							$playerAPI->setExternalData(CacheData::SPAM_A_VIOLATION, ($violationChat ?? 0) + 1);
							$this->dispatchAsyncDecision($playerAPI, true);
						} else {
							$playerAPI->setExternalData(CacheData::SPAM_A_TICK, microtime(true));
							$playerAPI->setExternalData(CacheData::SPAM_A_VIOLATION, 0);
						}
						$event->cancel();
					} else {
						$playerAPI->setExternalData(CacheData::SPAM_A_TICK, microtime(true));
						$playerAPI->setExternalData(CacheData::SPAM_A_VIOLATION, 0);
					}
					$this->debug($playerAPI, "diff=$diff, chatTick=$chatTick, violationChat=$violationChat");
				} else {
					$playerAPI->setExternalData(CacheData::SPAM_A_TICK, microtime(true));
					$playerAPI->setExternalData(CacheData::SPAM_A_VIOLATION, 0);
				}
			}
		}
	}
}
