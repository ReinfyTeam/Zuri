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
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use ReinfyTeam\Zuri\config\ConfigManager;
use ReinfyTeam\Zuri\config\ConfigPaths;
use ReinfyTeam\Zuri\lang\Lang;
use ReinfyTeam\Zuri\lang\LangKeys;
use ReinfyTeam\Zuri\ZuriAC;
use function is_scalar;
use function strtolower;

/**
 * Displays loaded check modules with status, subtypes, and configured max violations.
 */
class ListSubCommand extends BaseSubCommand {
	/**
	 * Registers the `/zuri list` subcommand.
	 *
	 * @param PluginBase $plugin Plugin that provides this command tree.
	 * @return void
	 */
	public function __construct(PluginBase $plugin) {
		parent::__construct($plugin, "list", "List of modules in Zuri.", ["modules", "checks"]);
	}

	/**
	 * Declares arguments for this subcommand.
	 */
	protected function prepare() : void {
	}

	/**
	 * Sends a formatted list of anti-cheat checks and their current configuration.
	 *
	 * @param CommandSender $sender Sender requesting the module listing.
	 * @param string $aliasUsed Alias used to execute this subcommand.
	 * @param array<string,mixed> $args Parsed arguments from Commando.
	 */
	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void {
		$sender->sendMessage(Lang::get(LangKeys::CMD_LIST_HEADER));
		$sender->sendMessage(Lang::get(LangKeys::CMD_LIST_TITLE));
		$added = [];
		foreach (ZuriAC::Checks() as $check) {
			$name = $check->getName();
			if (!isset($added[$name])) {
				$status = $check->enable() ? TextFormat::GREEN . "Enabled" : TextFormat::RED . "Disabled";
				$maxVl = ConfigManager::getData(ConfigPaths::CHECK . "." . strtolower($name) . ".maxvl");
				$sender->sendMessage(Lang::get(LangKeys::CMD_LIST_ENTRY, [
					"name" => $name,
					"subtypes" => $check->getAllSubTypes(),
					"status" => $status,
					"maxvl" => is_scalar($maxVl) ? (string) $maxVl : "0",
				]));
				$added[$name] = true;
			}
		}
		$sender->sendMessage(Lang::get(LangKeys::CMD_LIST_FOOTER));
	}
}
