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
use ReinfyTeam\Zuri\API;
use ReinfyTeam\Zuri\lang\Lang;
use ReinfyTeam\Zuri\lang\LangKeys;
use ReinfyTeam\Zuri\ZuriAC;
use function count;

/**
 * Displays plugin build information and quick check-count totals for admins.
 */
class AboutSubCommand extends BaseSubCommand {
	/**
	 * Registers the `/zuri about` subcommand.
	 *
	 * @param PluginBase $plugin Plugin that exposes this subcommand.
	 * @return void
	 */
	public function __construct(PluginBase $plugin) {
		parent::__construct($plugin, "about", "Show information the plugin.", ["info"]);
	}

	/**
	 * Declares arguments for this subcommand.
	 */
	protected function prepare() : void {
	}

	/**
	 * Sends version, author, and enabled/disabled check totals to the caller.
	 *
	 * @param CommandSender $sender Sender requesting plugin status details.
	 * @param string $aliasUsed Alias used to run the subcommand.
	 * @param array<string,mixed> $args Parsed arguments from Commando.
	 */
	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void {
		$version = ZuriAC::getInstance()->getDescription()->getVersion();
		$author = ZuriAC::getInstance()->getDescription()->getAuthors()[0];
		$enabledChecks = API::getAllEnabledChecks(false);
		$enabledChecksWithSub = API::getAllEnabledChecks();
		$disabledChecks = API::getAllDisabledChecks(false);
		$disabledChecksWithSub = API::getAllDisabledChecks();
		$allChecks = API::getAllChecks(false);
		$allChecksWithSub = API::getAllChecks();

		$sender->sendMessage(Lang::get(LangKeys::CMD_ABOUT_BUILD_AUTHOR, ["version" => $version, "author" => $author]));
		$sender->sendMessage(Lang::get(LangKeys::CMD_ABOUT_ENABLED, ["count" => count($enabledChecks), "subcount" => count($enabledChecksWithSub)]));
		$sender->sendMessage(Lang::get(LangKeys::CMD_ABOUT_DISABLED, ["count" => count($disabledChecks), "subcount" => count($disabledChecksWithSub)]));
		$sender->sendMessage(Lang::get(LangKeys::CMD_ABOUT_ALL, ["count" => count($allChecks), "subcount" => count($allChecksWithSub)]));
	}
}
