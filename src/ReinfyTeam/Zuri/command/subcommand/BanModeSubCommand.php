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

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use JsonException;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use ReinfyTeam\Zuri\config\ConfigManager;
use ReinfyTeam\Zuri\config\ConfigPaths;
use ReinfyTeam\Zuri\lang\Lang;
use ReinfyTeam\Zuri\lang\LangKeys;
use function is_string;
use function strtolower;

class BanModeSubCommand extends BaseSubCommand {
	public function __construct(PluginBase $plugin) {
		parent::__construct($plugin, "banmode", "Use to on/off ban mode.", ["ban"]);
	}

	protected function prepare() : void {
		$this->registerArgument(0, new RawStringArgument("mode"));
	}

	/**
	 * @param array<string,mixed> $args
	 * @throws JsonException
	 */
	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void {
		$mode = $args["mode"] ?? "";
		if (!is_string($mode) || strtolower($mode) !== "toggle") {
			$sender->sendMessage(Lang::get(LangKeys::CMD_BANMODE_USAGE));
			return;
		}

		$current = ConfigManager::getData(ConfigPaths::BAN_ENABLE) === true;
		ConfigManager::setData(ConfigPaths::BAN_ENABLE, !$current);
		$status = !$current ? TextFormat::GREEN . "enable" : TextFormat::RED . "disable";
		$sender->sendMessage(Lang::get(LangKeys::CMD_BANMODE_STATUS, ["status" => $status]));
	}
}
