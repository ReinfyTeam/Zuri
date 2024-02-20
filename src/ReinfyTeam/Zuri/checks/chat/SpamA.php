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
 * @author ReinfyTeam
 * @link https://github.com/ReinfyTeam/
 *
 *
 */

declare(strict_types=1);

namespace ReinfyTeam\Zuri\checks\chat;

use pocketmine\event\Event;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\network\mcpe\protocol\DataPacket;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use function microtime;

class SpamA extends Check {
	public function getName() : string {
		return "Spam";
	}

	public function getSubType() : string {
		return "A";
	}

	public function enable() : bool {
		return true;
	}

	public function ban() : bool {
		return false;
	}

	public function kick() : bool {
		return false;
	}

	public function flag() : bool {
		return false;
	}

	public function captcha() : bool {
		return true;
	}

	public function maxViolations() : int {
		return 5;
	}

	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
	}

	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if ($event instanceof PlayerChatEvent) {
			$chatTick = $playerAPI->getExternalData("SpamATick");
			if (!$playerAPI->getPlayer()->spawned && !$playerAPI->getPlayer()->isConnected()) {
				return;
			}
			$violationChat = $playerAPI->getExternalData("ViolationSpamA");
			if (!$event->isCancelled()) {
				if ($chatTick !== null || $violationChat !== null) {
					$diff = microtime(true) - $chatTick;
					if ($diff <= self::getData(self::CHAT_SPAM_DELAY)) {
						if ($violationChat <= 5) {
							$playerAPI->getPlayer()->sendMessage($this->replaceText($playerAPI, self::getData(self::CHAT_SPAM_TEXT), $this->getName(), $this->getSubType()));
							$playerAPI->setExternalData("SpamATick", microtime(true));
							$playerAPI->setExternalData("ViolationSpamA", $violationChat + 1);
							$this->failed($playerAPI);
							$event->cancel();
						} else {
							$playerAPI->setExternalData("SpamATick", microtime(true));
							$playerAPI->setExternalData("ViolationSpamA", 0);
							$event->cancel();
						}
					} else {
						$playerAPI->setExternalData("SpamATick", microtime(true));
						$playerAPI->setExternalData("ViolationSpamA", 0);
					}
					$this->debug($playerAPI, "diff=$diff, chatTick=$chatTick, violationChat=$violationChat");
				} else {
					$playerAPI->setExternalData("SpamATick", microtime(true));
					$playerAPI->setExternalData("ViolationSpamA", 0);
				}
			}
		}
	}
}