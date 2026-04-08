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
use ReinfyTeam\Zuri\command\subcommand\AboutSubCommand;
use ReinfyTeam\Zuri\command\subcommand\AsyncStatusSubCommand;
use ReinfyTeam\Zuri\command\subcommand\BanModeSubCommand;
use ReinfyTeam\Zuri\command\subcommand\BypassSubCommand;
use ReinfyTeam\Zuri\command\subcommand\CaptchaSubCommand;
use ReinfyTeam\Zuri\command\subcommand\DebugSubCommand;
use ReinfyTeam\Zuri\command\subcommand\HelpSubCommand;
use ReinfyTeam\Zuri\command\subcommand\LanguageSubCommand;
use ReinfyTeam\Zuri\command\subcommand\ListSubCommand;
use ReinfyTeam\Zuri\command\subcommand\NotifySubCommand;
use ReinfyTeam\Zuri\command\subcommand\UiSubCommand;
use ReinfyTeam\Zuri\lang\Lang;
use ReinfyTeam\Zuri\lang\LangKeys;
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
		$this->registerSubCommand(new AsyncStatusSubCommand($this->plugin));
		$this->registerSubCommand(new ListSubCommand($this->plugin));
		$this->registerSubCommand(new UiSubCommand($this->plugin));
		$this->registerSubCommand(new LanguageSubCommand($this->plugin));
		$this->registerSubCommand(new HelpSubCommand($this->plugin));
	}

	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void {
		$sender->sendMessage(self::buildHelpMessage($this->getName()));
	}

	public static function buildHelpMessage(string $namecmd = "zuri") : string {
		$version = ZuriAC::getInstance()->getDescription()->getVersion();
		$author = ZuriAC::getInstance()->getDescription()->getAuthors()[0] ?? 'Unknown';
		return implode("\n", [
			Lang::get(LangKeys::CMD_HELP_HEADER),
			Lang::get(LangKeys::CMD_HELP_BUILD_AUTHOR, ["version" => $version, "author" => $author]),
			"",
			Lang::get(LangKeys::CMD_HELP_ABOUT, ["command" => $namecmd]),
			Lang::get(LangKeys::CMD_HELP_NOTIFY, ["command" => $namecmd]),
			Lang::get(LangKeys::CMD_HELP_BANMODE, ["command" => $namecmd]),
			Lang::get(LangKeys::CMD_HELP_CAPTCHA, ["command" => $namecmd]),
			Lang::get(LangKeys::CMD_HELP_BYPASS, ["command" => $namecmd]),
			Lang::get(LangKeys::CMD_HELP_DEBUG, ["command" => $namecmd]),
			Lang::get(LangKeys::CMD_HELP_LIST, ["command" => $namecmd]),
			Lang::get(LangKeys::CMD_HELP_UI, ["command" => $namecmd]),
			Lang::get(LangKeys::CMD_HELP_LANGUAGE, ["command" => $namecmd]),
			Lang::get(LangKeys::CMD_HELP_FOOTER)
		]);
	}
}
