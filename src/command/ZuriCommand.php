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

use JsonException;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\PluginOwned;
use pocketmine\utils\TextFormat;
use ReinfyTeam\Zuri\API;
use ReinfyTeam\Zuri\config\ConfigManager;
use ReinfyTeam\Zuri\config\ConfigPaths;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\forms\FormSender;
use ReinfyTeam\Zuri\ZuriAC;
use function count;
use function implode;
use function intval;
use function is_numeric;
use function strtolower;
use function ucfirst;

class ZuriCommand extends Command implements PluginOwned {
	public function __construct() {
		parent::__construct("zuri", "Zuri Anticheat", "/zuri <help|sub-command>", ["zuri", "anticheat", "ac"]);
		$this->setPermission("zuri.command");
	}

	public function getOwningPlugin() : ZuriAC {
		return ZuriAC::getInstance();
	}

	/**
	 * @throws JsonException
	 */
	public function execute(CommandSender $sender, string $label, array $args) : void {
		$prefix = ConfigManager::getData(ConfigPaths::PREFIX);
		$namecmd = $this->getName();

		$playerAPI = ($sender instanceof Player) ? PlayerAPI::getAPIPlayer($sender) : null;

		$helpMessage = implode("\n", [
			TextFormat::RED . "----- Zuri Anticheat -----",
			TextFormat::AQUA . "Build: " . TextFormat::GRAY . ZuriAC::getInstance()->getDescription()->getVersion() .
				TextFormat::AQUA . " Author: " . TextFormat::GRAY . ZuriAC::getInstance()->getDescription()->getAuthors()[0],
			"",
			TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " about" . TextFormat::GRAY . " - Show information the plugin.",
			TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " notify (toggle/admin)" . TextFormat::GRAY . " - Use to on/off notify.",
			TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " banmode (toggle)" . TextFormat::GRAY . " - Use to on/off ban mode.",
			TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " captcha (toggle/message/tip/title/randomize/length)" . TextFormat::GRAY . " - Use to on/off mode for captcha.",
			TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " bypass" . TextFormat::GRAY . " - Use to on/off for bypass mode.",
			TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " debug" . TextFormat::GRAY . " - Use to on/off for debug mode.",
			TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " list" . TextFormat::GRAY . " - List of modules in Zuri.",
			TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " ui" . TextFormat::GRAY . " - Sends the Admin Management UI",
			TextFormat::RED . "----------------------"
		]);

		if (!isset($args[0])) {
			$sender->sendMessage($helpMessage);
			return;
		}

		switch (strtolower($args[0])) {
			case "about":
			case "info":
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
				break;

			case "notify":
			case "notification":
				if (!isset($args[1])) {
					$sender->sendMessage(TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " notify (toggle/admin) - Use to on/off notify.");
					break;
				}
				$key = strtolower($args[1]);
				if ($key === "toggle" || $key === "admin") {
					$configPath = ($key === "toggle") ? ConfigPaths::ALERTS_ENABLE : ConfigPaths::ALERTS_ADMIN;
					$current = ConfigManager::getData($configPath) === true;
					ConfigManager::setData($configPath, !$current);
					$status = !$current ? TextFormat::GREEN . "enable" : TextFormat::RED . "disable";
					$msgKey = $key === "toggle" ? "Notify toggle" : "Notify admin mode";
					$sender->sendMessage($prefix . TextFormat::GRAY . " {$msgKey} is " . $status);
				} else {
					$sender->sendMessage(TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " notify (toggle/admin) - Use to on/off notify.");
				}
				break;

			case "banmode":
			case "ban":
				if (!isset($args[1]) || strtolower($args[1]) !== "toggle") {
					$sender->sendMessage(TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " banmode (toggle) - Use to on/off ban mode.");
					break;
				}
				$current = ConfigManager::getData(ConfigPaths::BAN_ENABLE) === true;
				ConfigManager::setData(ConfigPaths::BAN_ENABLE, !$current);
				$status = !$current ? TextFormat::GREEN . "enable" : TextFormat::RED . "disable";
				$sender->sendMessage($prefix . TextFormat::GRAY . " Ban Mode is " . $status);
				break;

			case "captcha":
			case "verification":
			case "verify":
				if (!isset($args[1])) {
					$sender->sendMessage(TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " captcha (toggle/message/tip/title/randomize/length) - Use to on/off and set length code for captcha.");
					break;
				}
				$option = strtolower($args[1]);

				// Helper to toggle a boolean config
				$toggleConfig = function(string $path, string $msg) use ($prefix, $sender) {
					$current = ConfigManager::getData($path) === true;
					ConfigManager::setData($path, !$current);
					$status = !$current ? TextFormat::GREEN . "enable" : TextFormat::RED . "disable";
					$sender->sendMessage($prefix . TextFormat::GRAY . " {$msg} is " . $status);
				};

				switch ($option) {
					case "toggle":
						$toggleConfig(ConfigPaths::CAPTCHA_ENABLE, "Captcha");
						break;

					case "message":
					case "tip":
					case "title":
						if (ConfigManager::getData(ConfigPaths::CAPTCHA_RANDOMIZE)) {
							$sender->sendMessage($prefix . TextFormat::RED . " Randomize is on! Turn off randomize to toggle this!");
							break;
						}
						$path = match ($option) {
							"message" => ConfigPaths::CAPTCHA_MESSAGE,
							"tip" => ConfigPaths::CAPTCHA_TIP,
							"title" => ConfigPaths::CAPTCHA_TITLE,
							default => null,
						};
						if ($path !== null) {
							$toggleConfig($path, ucfirst($option) . " Captcha");
						}
						break;

					case "randomize":
						$toggleConfig(ConfigPaths::CAPTCHA_RANDOMIZE, "Randomize Mode");
						break;

					case "length":
						if (!isset($args[2]) || !is_numeric($args[2]) || (int) $args[2] != $args[2] || $args[2] < 1 || $args[2] > 15) {
							$sender->sendMessage($prefix . TextFormat::RED . " Invalid usage! " . TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " captcha length <int: 1-15>");
							break;
						}
						$len = intval($args[2]);
						ConfigManager::setData(ConfigPaths::CAPTCHA_CODE_LENGTH, $len);
						$sender->sendMessage($prefix . TextFormat::GREEN . " Changed the code length to " . $len . "!");
						break;

					default:
						$sender->sendMessage(TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " captcha (toggle/message/tip/title/randomize/length) - Use to on/off and set length code for captcha.");
						break;
				}
				break;

			case "bypass":
				$current = ConfigManager::getData(ConfigPaths::PERMISSION_BYPASS_ENABLE) === true;
				ConfigManager::setData(ConfigPaths::PERMISSION_BYPASS_ENABLE, !$current);
				$status = !$current ? TextFormat::GREEN . "enable" : TextFormat::RED . "disable";
				$sender->sendMessage($prefix . TextFormat::GRAY . " Bypass mode is " . $status);
				break;

			case "debug":
			case "analyze":
				if ($playerAPI === null) {
					$sender->sendMessage($prefix . TextFormat::RED . " Please use this command at the game!");
					break;
				}
				$newDebugStatus = !$playerAPI->isDebug();
				$playerAPI->setDebug($newDebugStatus);
				$status = $newDebugStatus ? TextFormat::GREEN . "enable" : TextFormat::RED . "disable";
				$sender->sendMessage($prefix . TextFormat::GRAY . " Debug mode is " . $status);
				break;

			case "list":
			case "modules":
			case "checks":
				$sender->sendMessage($prefix . TextFormat::GRAY . " -------------------------------");
				$sender->sendMessage($prefix . TextFormat::GRAY . " Zuri Modules/Check Information List:");
				$added = [];
				foreach (ZuriAC::Checks() as $check) {
					$name = $check->getName();
					if (!isset($added[$name])) {
						$status = $check->enable() ? TextFormat::GREEN . "Enabled" : TextFormat::RED . "Disabled";
						$maxVl = ConfigManager::getData(ConfigPaths::CHECK . "." . strtolower($name) . ".maxvl");
						$sender->sendMessage($prefix . TextFormat::RESET . " " . TextFormat::AQUA . $name . TextFormat::DARK_GRAY . " (" . TextFormat::YELLOW . $check->getAllSubTypes() . TextFormat::DARK_GRAY . ") " .
							TextFormat::GRAY . "| " . TextFormat::AQUA . "Status: " . $status . TextFormat::GRAY . " | " . TextFormat::AQUA . "Max Violation: " . TextFormat::YELLOW . $maxVl);
						$added[$name] = true;
					}
				}
				$sender->sendMessage($prefix . TextFormat::GRAY . " -------------------------------");
				break;

			case "ui":
			case "forms":
			case "form":
			case "gui":
				if ($playerAPI === null) {
					$sender->sendMessage($prefix . TextFormat::RED . " Please use this command at the game!");
					break;
				}
				FormSender::MainUI($sender);
				break;

			case "help":
			case "noarguments":
			case "cmd":
			default:
				$sender->sendMessage($helpMessage);
				break;
		}
	}
}