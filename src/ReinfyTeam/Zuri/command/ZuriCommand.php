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

namespace ReinfyTeam\Zuri\command;

use CortexPE\Commando\BaseCommand;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;
use ReinfyTeam\Zuri\command\subcommand\AboutSubCommand;
use ReinfyTeam\Zuri\command\subcommand\BanModeSubCommand;
use ReinfyTeam\Zuri\command\subcommand\BypassSubCommand;
use ReinfyTeam\Zuri\command\subcommand\CaptchaSubCommand;
use ReinfyTeam\Zuri\command\subcommand\DebugSubCommand;
use ReinfyTeam\Zuri\command\subcommand\HelpSubCommand;
use ReinfyTeam\Zuri\command\subcommand\ListSubCommand;
use ReinfyTeam\Zuri\command\subcommand\NotifySubCommand;
use ReinfyTeam\Zuri\command\subcommand\UiSubCommand;
use ReinfyTeam\Zuri\ZuriAC;
use function implode;

class ZuriCommand extends BaseCommand {
	public function __construct(ZuriAC $plugin) {
		parent::__construct($plugin, "zuri", "Zuri Anticheat", ["anticheat", "ac"]);
	}

	protected function prepare() : void {
		$this->setPermission("zuri.command");
		$this->registerSubCommand(new AboutSubCommand($this->plugin));
		$this->registerSubCommand(new NotifySubCommand($this->plugin));
		$this->registerSubCommand(new BanModeSubCommand($this->plugin));
		$this->registerSubCommand(new CaptchaSubCommand($this->plugin));
		$this->registerSubCommand(new BypassSubCommand($this->plugin));
		$this->registerSubCommand(new DebugSubCommand($this->plugin));
		$this->registerSubCommand(new ListSubCommand($this->plugin));
		$this->registerSubCommand(new UiSubCommand($this->plugin));
		$this->registerSubCommand(new HelpSubCommand($this->plugin));
	}

	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void {
		$sender->sendMessage(self::buildHelpMessage($this->getName()));
	}

	public static function buildHelpMessage(string $namecmd = "zuri") : string {
		return implode("\n", [
			TextFormat::RED . "----- Zuri Anticheat -----",
			TextFormat::AQUA . "Build: " . TextFormat::GRAY . ZuriAC::getInstance()->getDescription()->getVersion() .
			TextFormat::AQUA . " Author: " . TextFormat::GRAY . ZuriAC::getInstance()->getDescription()->getAuthors()[0],
			"",
			TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " about" . TextFormat::GRAY . " - Show information the plugin.",
			TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " notify <toggle|admin>" . TextFormat::GRAY . " - Use to on/off notify.",
			TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " banmode <toggle>" . TextFormat::GRAY . " - Use to on/off ban mode.",
			TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " captcha <toggle|message|tip|title|randomize|length> [length]" . TextFormat::GRAY . " - Use to on/off mode for captcha.",
			TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " bypass" . TextFormat::GRAY . " - Use to on/off for bypass mode.",
			TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " debug" . TextFormat::GRAY . " - Use to on/off for debug mode.",
			TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " list" . TextFormat::GRAY . " - List of modules in Zuri.",
			TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " ui" . TextFormat::GRAY . " - Sends the Admin Management UI",
			TextFormat::RED . "----------------------"
		]);
	}
}
