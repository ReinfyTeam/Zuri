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

namespace ReinfyTeam\Zuri\utils\discord;

use pocketmine\Server;
use ReinfyTeam\Zuri\ZuriAC;
use function filter_var;
use function json_encode;

class Webhook {
	protected $url;

	public function __construct(string $url) {
		$this->url = $url;
	}

	public function getURL() : string {
		return $this->url;
	}

	public function isValid() : bool {
		return filter_var($this->url, FILTER_VALIDATE_URL) !== false;
	}

	public function send(Message $message) : void {
		Server::getInstance()->getAsyncPool()->submitTask(new WebhookSendTask($this, $message));
		ZuriAC::getInstance()->getLogger()->debug("[Discord] [DEBUG]: Sending to discord with data: " . json_encode($message));
	}
}
