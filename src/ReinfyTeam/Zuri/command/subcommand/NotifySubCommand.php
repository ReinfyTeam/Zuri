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
use function strtolower;

class NotifySubCommand extends BaseSubCommand {
	public function __construct(PluginBase $plugin) {
		parent::__construct($plugin, "notify", "Use to on/off notify.", ["notification"]);
	}

	protected function prepare() : void {
		$this->registerArgument(0, new RawStringArgument("mode"));
	}

	/**
	 * @throws JsonException
	 */
	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void {
		$key = strtolower((string) ($args["mode"] ?? ""));
		if ($key !== "toggle" && $key !== "admin") {
			$sender->sendMessage(Lang::get(LangKeys::CMD_NOTIFY_USAGE));
			return;
		}

		$configPath = ($key === "toggle") ? ConfigPaths::ALERTS_ENABLE : ConfigPaths::ALERTS_ADMIN;
		$current = ConfigManager::getData($configPath) === true;
		ConfigManager::setData($configPath, !$current);
		$status = !$current ? TextFormat::GREEN . "enable" : TextFormat::RED . "disable";
		$msgKey = $key === "toggle" ? "Notify toggle" : "Notify admin mode";
		$sender->sendMessage(Lang::get(LangKeys::CMD_NOTIFY_STATUS, ["target" => $msgKey, "status" => $status]));
	}
}
