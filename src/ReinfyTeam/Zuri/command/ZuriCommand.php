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
use ReinfyTeam\Zuri\config\ConfigPath;
use ReinfyTeam\Zuri\config\language\LanguagePath;
use ReinfyTeam\Zuri\utils\Utils;
use ReinfyTeam\Zuri\ZuriAC;
use ReinfyTeam\Zuri\command\subcommand\DebugSubCommand;

class ZuriCommand extends BaseCommand {
	public function prepare() : void {
		$this->setPermission(Utils::readString(ZuriAC::getConfigManager()->getData(ConfigPath::ZURI_COMMAND_PERMISSION)));
		$this->setDescription(ZuriAC::getLanguageManager()->getCurrentLanguage()->translate(LanguagePath::ZURI_COMMAND_DESCRIPTION));

		$this->registerSubCommand(new DebugSubCommand());
	}

	public function __construct() {
		parent::__construct(ZuriAC::getInstance(), "zuri", ZuriAC::getLanguageManager()->getCurrentLanguage()->translate(LanguagePath::ZURI_COMMAND_DESCRIPTION), ["anticheat", "ac", "zuriac"]);
	}

	/**
	 * @param array<string> $args
	 */
	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void {
	}
}