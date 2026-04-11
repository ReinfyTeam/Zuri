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

namespace ReinfyTeam\Zuri\command\subcommand;

use CortexPE\Commando\BaseSubCommand;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use ReinfyTeam\Zuri\lang\Lang;
use ReinfyTeam\Zuri\lang\LangKeys;
use ReinfyTeam\Zuri\utils\AuditLogger;
use ReinfyTeam\Zuri\utils\forms\FormSender;

/**
 * Opens the Zuri admin management UI for in-game staff members.
 */
class UiSubCommand extends BaseSubCommand {
	/**
	 * Registers the `/zuri ui` subcommand and its aliases.
	 *
	 * @param PluginBase $plugin Plugin that owns this command tree.
	 * @return void
	 */
	public function __construct(PluginBase $plugin) {
		parent::__construct($plugin, "ui", "Sends the Admin Management UI", ["forms", "form", "gui"]);
	}

	/**
	 * Declares arguments for this subcommand.
	 */
	protected function prepare() : void {
	}

	/**
	 * Sends the management form to the invoking player when executed in-game.
	 *
	 * @param CommandSender $sender Sender attempting to open the UI.
	 * @param string $aliasUsed Alias used to invoke this subcommand.
	 * @param array<string,mixed> $args Parsed arguments from Commando.
	 */
	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void {
		if (!$sender instanceof Player) {
			$sender->sendMessage(Lang::get(LangKeys::CMD_UI_IN_GAME_ONLY));
			return;
		}
		AuditLogger::command($sender, "zuri ui");
		FormSender::MainUI($sender);
	}
}
