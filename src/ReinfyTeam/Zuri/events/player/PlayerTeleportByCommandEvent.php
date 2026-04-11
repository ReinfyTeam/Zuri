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
 * Fired when a player teleport is triggered by a command.
 *
 * This event provides a cancellable hook for plugins and updates Zuri timing state
 * so movement checks can distinguish command teleports from suspicious movement.
 */
class PlayerTeleportByCommandEvent extends PlayerEvent implements Cancellable {
	use CancellableTrait;

	/**
	 * Creates a teleport-by-command event payload.
	 *
	 * @return void
	 */
	public function __construct(Player $player) {
		$this->player = $player;
	}

	/**
	 * Updates teleport-command timing state for the player.
	 */
	public function call() : void {
		$playerAPI = PlayerAPI::getAPIPlayer($this->player);

		$playerAPI->setTeleportCommandTicks(microtime(true)); // set ticks
	}
}
