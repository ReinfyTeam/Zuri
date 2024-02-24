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

namespace ReinfyTeam\Zuri\utils\discord;

use ReinfyTeam\Zuri\config\ConfigManager;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\ReplaceText;

class Discord extends ConfigManager {
	public static function sendMessage(string $text) {
		$discord = new Webhook(self::getData(self::DISCORD_WEBHOOK));
		$msg = new Message();
		$msg->setUsername("Zuri Anticheat");
		$msg->setAvatarURL("https://raw.githubusercontent.com/ReinfyTeam/Zuri/main/icon.png");
		$msg->setContent($text);
		$discord->send($msg);
	}

	public static function onJoin(PlayerAPI $player) {
		if (self::getData(self::DISCORD_PLAYER_JOIN_ENABLE) === true) {
			self::sendMessage(ReplaceText::replace($player, self::getData(self::DISCORD_PLAYER_JOIN_TEXT)));
		}
	}

	public static function onLeft(PlayerAPI $player) {
		if (self::getData(self::DISCORD_PLAYER_LEFT_ENABLE) === true) {
			self::sendMessage(ReplaceText::replace($player, self::getData(self::DISCORD_PLAYER_LEFT_TEXT)));
		}
	}

	public static function onKick(PlayerAPI $player, string $reason) {
		if (self::getData(self::DISCORD_PLAYER_KICK_ENABLE) === true) {
			self::sendMessage(ReplaceText::replace($player, self::getData(self::DISCORD_PLAYER_KICK_TEXT), $reason));
		}
	}

	public static function onBan(PlayerAPI $player, string $reason) {
		if (self::getData(self::DISCORD_PLAYER_BAN_ENABLE) === true) {
			self::sendMessage(ReplaceText::replace($player, self::getData(self::DISCORD_PLAYER_BAN_TEXT), $reason));
		}
	}

	public static function onLagging(PlayerAPI $player) {
		if (self::getData(self::DISCORD_SERVER_LAGGING_ENABLE) === true) {
			self::sendMessage(ReplaceText::replace($player, self::getData(self::DISCORD_SERVER_LAGGING_TEXT)));
		}
	}
}
