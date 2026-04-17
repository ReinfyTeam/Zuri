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

namespace ReinfyTeam\Zuri\player;

use pocketmine\player\Player;


/**
 * Manages PlayerZuri instances for active players.
 */
class PlayerManager {
	/** @var PlayerZuri[] */
	private static array $players = [];

	/**
	 * Returns the PlayerZuri for a given Player, creating it if necessary.
	 */
	public static function get(Player $player) : PlayerZuri {
		$playerZuri = self::$players[$player->getName()] ??= PlayerZuri::create($player);
		$playerZuri->updateData($player);

		return $playerZuri;
	}

	/**
	 * Adds a new PlayerZuri for a player.
	 */
	public static function add(Player $player) : void {
		self::$players[$player->getName()] = PlayerZuri::create($player);
	}

	/**
	 * Removes a PlayerZuri for a player.
	 */
	public static function remove(Player $player) : void {
		unset(self::$players[$player->getName()]);
	}
}