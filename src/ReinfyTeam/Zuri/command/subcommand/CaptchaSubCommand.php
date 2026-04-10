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

use CortexPE\Commando\args\IntegerArgument;
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
use ReinfyTeam\Zuri\utils\AuditLogger;
use function is_int;
use function is_string;
use function strtolower;
use function ucfirst;

class CaptchaSubCommand extends BaseSubCommand {
	public function __construct(PluginBase $plugin) {
		parent::__construct($plugin, "captcha", "Use to on/off mode for captcha.", ["verification", "verify"]);
	}

	protected function prepare() : void {
		$this->registerArgument(0, new RawStringArgument("option"));
		$this->registerArgument(1, new IntegerArgument("length", true));
	}

	/**
	 * @param array<string,mixed> $args
	 * @throws JsonException
	 */
	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void {
		$optionRaw = $args["option"] ?? "";
		$option = is_string($optionRaw) ? strtolower($optionRaw) : "";

		$toggleConfig = static function(string $path, string $msg) use ($sender, $option) : void {
			$current = ConfigManager::getData($path) === true;
			ConfigManager::setData($path, !$current);
			$status = !$current ? TextFormat::GREEN . "enable" : TextFormat::RED . "disable";
			AuditLogger::command($sender, "zuri captcha", ["option" => $option, "target" => $msg, "toggledTo" => !$current ? "enabled" : "disabled"]);
			$sender->sendMessage(Lang::get(LangKeys::CMD_GENERIC_TOGGLE_STATUS, ["target" => $msg, "status" => $status]));
		};

		switch ($option) {
			case "toggle":
				$toggleConfig(ConfigPaths::CAPTCHA_ENABLE, "Captcha");
				return;

			case "message":
			case "tip":
			case "title":
				if (ConfigManager::getData(ConfigPaths::CAPTCHA_RANDOMIZE)) {
					$sender->sendMessage(Lang::get(LangKeys::CMD_CAPTCHA_RANDOMIZE_ON));
					return;
				}
				$path = match ($option) {
					"message" => ConfigPaths::CAPTCHA_MESSAGE,
					"tip" => ConfigPaths::CAPTCHA_TIP,
					"title" => ConfigPaths::CAPTCHA_TITLE,
				};
				$toggleConfig($path, ucfirst($option) . " Captcha");
				return;

			case "randomize":
				$toggleConfig(ConfigPaths::CAPTCHA_RANDOMIZE, "Randomize Mode");
				return;

			case "length":
				$length = $args["length"] ?? null;
				if (!is_int($length) || $length < 1 || $length > 15) {
					$sender->sendMessage(Lang::get(LangKeys::CMD_CAPTCHA_INVALID_LENGTH));
					return;
				}
				ConfigManager::setData(ConfigPaths::CAPTCHA_CODE_LENGTH, $length);
				AuditLogger::command($sender, "zuri captcha", ["option" => "length", "length" => $length]);
				$sender->sendMessage(Lang::get(LangKeys::CMD_CAPTCHA_LENGTH_UPDATED, ["length" => $length]));
				return;
		}

		$sender->sendMessage(Lang::get(LangKeys::CMD_CAPTCHA_USAGE));
	}
}
