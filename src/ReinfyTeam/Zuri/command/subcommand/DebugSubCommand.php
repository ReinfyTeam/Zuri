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
use pocketmine\utils\TextFormat;
use ReinfyTeam\Zuri\lang\Lang;
use ReinfyTeam\Zuri\lang\LangKeys;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\AuditLogger;

/**
 * Lets players toggle their personal anti-cheat debug stream.
 */
class DebugSubCommand extends BaseSubCommand {
	/**
	 * Registers the `/zuri debug` subcommand.
	 *
	 * @param PluginBase $plugin Plugin exposing this subcommand.
	 * @return void
	 */
	public function __construct(PluginBase $plugin) {
		parent::__construct($plugin, "debug", "Use to on/off for debug mode.", ["analyze"]);
	}

	/**
	 * Declares arguments for this subcommand.
	 */
	protected function prepare() : void {
	}

	/**
	 * Toggles debug mode for the executing in-game player.
	 *
	 * @param CommandSender $sender Sender attempting to toggle debug mode.
	 * @param string $aliasUsed Alias used to run this subcommand.
	 * @param array<string,mixed> $args Parsed arguments from Commando.
	 */
	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void {
		if (!$sender instanceof Player) {
			$sender->sendMessage(Lang::get(LangKeys::CMD_UI_IN_GAME_ONLY));
			return;
		}

		$playerAPI = PlayerAPI::getAPIPlayer($sender);
		$newDebugStatus = !$playerAPI->isDebug();
		$playerAPI->setDebug($newDebugStatus);
		$status = $newDebugStatus ? TextFormat::GREEN . "enable" : TextFormat::RED . "disable";
		AuditLogger::command($sender, "zuri debug", ["toggledTo" => $newDebugStatus ? "enabled" : "disabled"]);
		$sender->sendMessage(Lang::get(LangKeys::CMD_DEBUG_STATUS, ["status" => $status]));
	}
}
