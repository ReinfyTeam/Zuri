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
use function count;
use function explode;
use function str_split;
use function strtolower;

class SpamB extends Check {
	public function getName() : string {
		return "Spam";
	}

	public function getSubType() : string {
		return "B";
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
		return false;
	}

	public function maxViolations() : int {
		return 3;
	}

	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
	}

	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if ($event instanceof PlayerChatEvent) {
			if (!$event->isCancelled()) {
				$message = $event->getMessage();
				$lastMessage = $playerAPI->getExternalData("lastMessage");
				if ($lastMessage !== null) {
					$violation = false;
					$explode = explode(" ", $message);
					$explode2 = explode(" ", $lastMessage);
					$countChar = count($explode);
					$countChar2 = count($explode2);
					if ($countChar === $countChar2 and $countChar === 1) {
						$explode3 = str_split(strtolower($explode[0]));
						$explode4 = str_split(strtolower($explode2[0]));
						$count = 0;
						foreach ($explode3 as $key) {
							if (isset($explode4[$key])) {
								$count++;
							}
						}
						if (count($explode4) - $count <= $count) {
							$violation = true;
						}
					}
					$count2 = 0;
					$chars = [];
					foreach ($explode as $text) {
						$chars[strtolower($text)] = strtolower($text);
					}
					foreach ($explode2 as $text) {
						if (isset($chars[strtolower($text)])) {
							$count2++;
						}
					}
					if (count($chars) - $count2 <= $count2) {
						$violation = true;
					}
					if ($violation === true) {
						$playerAPI->getPlayer()->sendMessage($this->replaceText($playerAPI, self::getData(self::CHAT_REPEAT_TEXT), $this->getName(), $this->getSubType()));
						$event->cancel();
					}
					$playerAPI->setExternalData("lastMessage", $message);
				} else {
					$playerAPI->setExternalData("lastMessage", $message);
				}
			}
		}
	}
}