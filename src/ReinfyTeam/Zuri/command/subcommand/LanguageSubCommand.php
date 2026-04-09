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
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginBase;
use ReinfyTeam\Zuri\lang\Lang;
use ReinfyTeam\Zuri\lang\LangKeys;
use function implode;
use function is_string;
use function strtolower;

class LanguageSubCommand extends BaseSubCommand {
	public function __construct(PluginBase $plugin) {
		parent::__construct($plugin, "language", "Switch plugin language at runtime", ["lang", "locale"]);
	}

	protected function prepare() : void {
		$this->registerArgument(0, new RawStringArgument("locale", true));
	}

	/** @param array<string,mixed> $args */
	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void {
		$requestedRaw = $args["locale"] ?? "";
		$requested = is_string($requestedRaw) ? $requestedRaw : "";
		$available = Lang::getAvailableLocales();

		if ($requested === "" || strtolower($requested) === "list") {
			$sender->sendMessage(Lang::get(LangKeys::CMD_LANGUAGE_CURRENT, ["locale" => Lang::getActiveLocale()]));
			$sender->sendMessage(Lang::get(LangKeys::CMD_LANGUAGE_AVAILABLE, ["locales" => implode(", ", $available)]));
			if ($requested === "") {
				$sender->sendMessage(Lang::get(LangKeys::CMD_LANGUAGE_USAGE));
			}
			return;
		}

		if (!Lang::setLocale($requested, true)) {
			$sender->sendMessage(Lang::get(LangKeys::CMD_LANGUAGE_UNSUPPORTED, ["locale" => $requested]));
			$sender->sendMessage(Lang::get(LangKeys::CMD_LANGUAGE_AVAILABLE, ["locales" => implode(", ", $available)]));
			return;
		}

		$sender->sendMessage(Lang::get(LangKeys::CMD_LANGUAGE_SWITCHED, ["locale" => Lang::getActiveLocale()]));
	}
}
