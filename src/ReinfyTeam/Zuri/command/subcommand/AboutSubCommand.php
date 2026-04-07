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
use ReinfyTeam\Zuri\API;
use ReinfyTeam\Zuri\ZuriAC;
use function count;

class AboutSubCommand extends BaseSubCommand {
	public function __construct(PluginBase $plugin) {
		parent::__construct($plugin, "about", "Show information the plugin.", ["info"]);
	}

	protected function prepare() : void {
	}

	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void {
		$version = ZuriAC::getInstance()->getDescription()->getVersion();
		$author = ZuriAC::getInstance()->getDescription()->getAuthors()[0];
		$enabledChecks = API::getAllEnabledChecks(false);
		$enabledChecksWithSub = API::getAllEnabledChecks();
		$disabledChecks = API::getAllDisabledChecks(false);
		$disabledChecksWithSub = API::getAllDisabledChecks();
		$allChecks = API::getAllChecks(false);
		$allChecksWithSub = API::getAllChecks();

		$sender->sendMessage(TextFormat::AQUA . "Build: " . TextFormat::GRAY . $version . TextFormat::AQUA . " Author: " . TextFormat::GRAY . $author);
		$sender->sendMessage(TextFormat::AQUA . "Total Enabled Checks: " . TextFormat::GRAY . count($enabledChecks) . " (With SubTypes: " . count($enabledChecksWithSub) . ")");
		$sender->sendMessage(TextFormat::AQUA . "Total Disabled Checks: " . TextFormat::GRAY . count($disabledChecks) . " (With SubTypes: " . count($disabledChecksWithSub) . ")");
		$sender->sendMessage(TextFormat::AQUA . "Total All Checks: " . TextFormat::GRAY . count($allChecks) . " (With SubTypes: " . count($allChecksWithSub) . ")");
	}
}
