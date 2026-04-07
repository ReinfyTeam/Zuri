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

namespace ReinfyTeam\Zuri\events\player;

use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\event\Event;
use pocketmine\event\player\PlayerEvent;
use pocketmine\player\Player;
use ReinfyTeam\Zuri\player\PlayerAPI;
use function microtime;

/**
 * Bruh??! Why this event? #BlamePocketMine
 *
 * Well, PocketMine-MP doesn't support checks for teleport by command.
 * Unlike bukkit, there's something called "TeleportCause" which specifies what
 * is the cause of the teleportation. Which is not implemented in PocketMine-MP.
 * This will fix some probably issues when Speed (A/B) detects as malicious behaivor.
 *
 * Also, i created this event so it can easily cancel by plugins.
 */
class PlayerTeleportByCommandEvent extends PlayerEvent implements Cancellable {
	use CancellableTrait;

	public function __construct(Player $player) {
		$this->player = $player;
	}

	public function call() : void {
		$playerAPI = PlayerAPI::getAPIPlayer($this->player);

		$playerAPI->setTeleportCommandTicks(microtime(true)); // set ticks
	}
}