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

namespace ReinfyTeam\Zuri\events;

use pocketmine\event\CancellableTrait;
use pocketmine\event\Event;
use pocketmine\Server;
use ReinfyTeam\Zuri\lang\Lang;
use ReinfyTeam\Zuri\lang\LangKeys;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\Discord;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use ReinfyTeam\Zuri\utils\ReplaceText;

/**
 * Fired when lag handling detects severe server slowdown.
 */
class ServerLagEvent extends Event {
	use CancellableTrait;

	private PlayerAPI $player;

	/**
	 * Creates a server lag event payload.
	 *
	 * @return void
	 */
	public function __construct(PlayerAPI $player) {
		$this->player = $player;
	}

	/**
	 * Gets the associated player context.
	 */
	public function getPlayer() : PlayerAPI {
		return $this->player;
	}

	/**
	 * Sends lag notifications and dispatches the event.
	 *
	 * @throws DiscordWebhookException
	 */
	public function call() : void {
		Discord::Send($this->player, Discord::LAGGING);
		Server::getInstance()->getLogger()->warning(ReplaceText::replace($this->player, Lang::raw(LangKeys::SERVER_LAGGING_MESSAGE)));

		parent::call();
	}
}
