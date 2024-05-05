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
use pocketmine\event\server\CommandEvent;
use pocketmine\network\mcpe\protocol\DataPacket;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use function in_array;
use function microtime;

class SpamC extends Check {
	public function getName() : string {
		return "Spam";
	}

	public function getSubType() : string {
		return "C";
	}

	public function maxViolations() : int {
		return 3;
	}

	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
	}

	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if ($event instanceof CommandEvent) {
			if (!$event->isCancelled()) {
				$command = $event->getCommand();
				$lastTicks = $playerAPI->getExternalData("lastTickSC");
				if (!$playerAPI->getPlayer()->spawned && !$playerAPI->getPlayer()->isConnected()) {
					return;
				}
				if ($lastTicks !== null) {
					$diff = microtime(true) - $lastTicks;
					if ($diff < self::getData(self::CHAT_COMMAND_SPAM_DELAY)) {
						if (in_array($command, self::getData(self::CHAT_COMMAND_SPAM_COMMANDS), true)) {
							$playerAPI->getPlayer()->sendMessage(self::getData(self::CHAT_COMMAND_SPAM_TEXT));
							$event->setCommand("");
							$event->cancel();
						}
					} else {
						$playerAPI->unsetExternalData("lastTickSC");
					}
					$this->debug($playerAPI, "diff=$diff, lastTicks=$lastTicks");
				} else {
					$playerAPI->setExternalData("lastTickSC", microtime(true));
				}
			}
		}
	}
}
