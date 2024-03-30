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
 * @author ReinfyTeam
 * @link https://github.com/ReinfyTeam/
 *
 *
 */

declare(strict_types=1);

namespace ReinfyTeam\Zuri\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\PluginOwned;
use pocketmine\utils\TextFormat;
use ReinfyTeam\Zuri\APIProvider;
use ReinfyTeam\Zuri\config\ConfigManager;
use ReinfyTeam\Zuri\player\PlayerAPI;
use function count;
use function intval;
use function is_float;
use function strtolower;

class ZuriCommand extends Command implements PluginOwned {
	public function __construct() {
		parent::__construct("zuri", "Zuri Anticheat", "/zuri <help|sub-command>", ["zuri", "anticheat", "ac"]);
		$this->setPermission("zuri.command");
	}

	public function getOwningPlugin() : APIProvider {
		return APIProvider::getInstance();
	}

	public function execute(CommandSender $sender, string $label, array $args) : void {
		$prefix = ConfigManager::getData(ConfigManager::PREFIX);
		$namecmd = $this->getName();
		if ($sender instanceof Player) {
			$playerAPI = PlayerAPI::getAPIPlayer($sender);
		}
		if (isset($args[0])) {
			switch(strtolower($args[0])) {
				case "about":
				case "info":
					$sender->sendMessage(TextFormat::AQUA . "Build: " . TextFormat::GRAY . APIProvider::getInstance()->getDescription()->getVersion() . TextFormat::AQUA . " Author: " . TextFormat::GRAY . APIProvider::getInstance()->getDescription()->getAuthors()[0]);
					$sender->sendMessage(TextFormat::AQUA . "Total Checks: " . TextFormat::GRAY . count(APIProvider::Checks()));
					break;
				case "notify":
				case "notification":
					if (isset($args[1])) {
						switch(strtolower($args[1])) {
							case "toggle":
								$data = ConfigManager::getData(ConfigManager::ALERTS_ENABLE) === true ? ConfigManager::setData(ConfigManager::ALERTS_ENABLE, false) : ConfigManager::setData(ConfigManager::ALERTS_ENABLE, true);
								$sender->sendMessage($prefix . TextFormat::GRAY . " Notify toggle is " . (ConfigManager::getData(ConfigManager::ALERTS_ENABLE) ? TextFormat::GREEN . "enable" : TextFormat::RED . "disable"));
								break;
							case "admin":
								$data = ConfigManager::getData(ConfigManager::ALERTS_ADMIN) === true ? ConfigManager::setData(ConfigManager::ALERTS_ADMIN, false) : ConfigManager::setData(ConfigManager::ALERTS_ADMIN, true);
								$sender->sendMessage($prefix . TextFormat::GRAY . " Notify admin mode is " . (ConfigManager::getData(ConfigManager::ALERTS_ADMIN) ? TextFormat::GREEN . "enable" : TextFormat::RED . "disable"));
								break;
							default:
								$sender->sendMessage(TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " notify (toggle/admin) - Use to on/off notify.");
								break;
						}
					} else {
						$sender->sendMessage(TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " notify" . TextFormat::RED . " (toggle/admin) - Use to on/off notify.");
					}
					break;
				case "banmode":
				case "ban":
					if (isset($args[1])) {
						switch($args[1]) {
							case "toggle":
								$data = ConfigManager::getData(ConfigManager::BAN_ENABLE) === true ? ConfigManager::setData(ConfigManager::BAN_ENABLE, false) : ConfigManager::setData(ConfigManager::BAN_ENABLE, true);
								$sender->sendMessage($prefix . TextFormat::GRAY . " Ban Mode is " . (ConfigManager::getData(ConfigManager::BAN_ENABLE) ? TextFormat::GREEN . "enable" : TextFormat::RED . "disable"));
								break;
							default: $sender->sendMessage(TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " banmode (toggle/randomize) - Use to on/off ban mode.");
						}
					} else {
						$sender->sendMessage(TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " banmode " . TextFormat::RED . " (toggle) - Use to on/off ban mode.");
					}
					break;
				case "captcha":
				case "verification":
				case "verify":
					if (isset($args[1])) {
						switch($args[1]) {
							case "toggle":
								$data = ConfigManager::getData(ConfigManager::CAPTCHA_ENABLE) === true ? ConfigManager::setData(ConfigManager::CAPTCHA_ENABLE, false) : ConfigManager::setData(ConfigManager::CAPTCHA_ENABLE, true);
								$sender->sendMessage($prefix . TextFormat::GRAY . " Captcha is " . (ConfigManager::getData(ConfigManager::CAPTCHA_ENABLE) ? TextFormat::GREEN . "enable" : TextFormat::RED . "disable"));
								break;
							case "message":
								if (!ConfigManager::getData(ConfigManager::CAPTCHA_RANDOMIZE)) {
									$data = ConfigManager::getData(ConfigManager::CAPTCHA_MESSAGE) === true ? ConfigManager::setData(ConfigManager::CAPTCHA_MESSAGE, false) : ConfigManager::setData(ConfigManager::CAPTCHA_MESSAGE, true);
									$sender->sendMessage($prefix . TextFormat::GRAY . " Message Captcha is " . (ConfigManager::getData(ConfigManager::CAPTCHA_MESSAGE) ? TextFormat::GREEN . "enable" : TextFormat::RED . "disable"));
								} else {
									$sender->sendMessage($prefix . TextFormat::RED . " Randomize is on! Turn off randomize to toggle this!");
								}
								break;
							case "tip":
								if (!ConfigManager::getData(ConfigManager::CAPTCHA_RANDOMIZE)) {
									$data = ConfigManager::getData(ConfigManager::CAPTCHA_TIP) === true ? ConfigManager::setData(ConfigManager::CAPTCHA_TIP, false) : ConfigManager::setData(ConfigManager::CAPTCHA_TIP, true);
									$sender->sendMessage($prefix . TextFormat::GRAY . " Tip Captcha is " . (ConfigManager::getData(ConfigManager::CAPTCHA_TIP) ? TextFormat::GREEN . "enable" : TextFormat::RED . "disable"));
								} else {
									$sender->sendMessage($prefix . TextFormat::RED . " Randomize is on! Turn off randomize to toggle this!");
								}
								break;
							case "title":
								if (!ConfigManager::getData(ConfigManager::CAPTCHA_RANDOMIZE)) {
									$data = ConfigManager::getData(ConfigManager::CAPTCHA_TITLE) === true ? ConfigManager::setData(ConfigManager::CAPTCHA_TITLE, false) : ConfigManager::setData(ConfigManager::CAPTCHA_TITLE, true);
									$sender->sendMessage($prefix . TextFormat::GRAY . " Title Captcha is " . (ConfigManager::getData(ConfigManager::CAPTCHA_TITLE) ? TextFormat::GREEN . "enable" : TextFormat::RED . "disable"));
								} else {
									$sender->sendMessage($prefix . TextFormat::RED . " Randomize is on! Turn off randomize to toggle this!");
								}
								break;
							case "randomize":
								$data = ConfigManager::getData(ConfigManager::CAPTCHA_RANDOMIZE) === true ? ConfigManager::setData(ConfigManager::CAPTCHA_RANDOMIZE, false) : ConfigManager::setData(ConfigManager::CAPTCHA_RANDOMIZE, true);
								$sender->sendMessage($prefix . TextFormat::GRAY . " Randomize Mode is " . (ConfigManager::getData(ConfigManager::CAPTCHA_RANDOMIZE) ? TextFormat::GREEN . "enable" : TextFormat::RED . "disable"));
								break;
							case "length":
								if (!isset($args[2])) {
									$sender->sendMessage($prefix . TextFormat::RED . " Invalid usage! " . TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " captcha length <int: 1-15>");
									return;
								}
								if (is_float($args[2])) {
									$sender->sendMessage($prefix . TextFormat::RED . " Invalid usage! The value must be integer! Must range to 1-15");
									return;
								}

								if ($args[2] < 1 || $args[2] > 15) {
									$sender->sendMessage($prefix . TextFormat::RED . " Invalid usage! Too big or too low! Must range to 1-15!");
									return;
								}

								$sender->sendMessage($prefix . TextFormat::GREEN . " Changed the code length to " . $args[2] . "!");
								ConfigManager::setData(ConfigManager::CAPTCHA_CODE_LENGTH, intval($args[2])); // incase of using ""
								break;
							default: $sender->sendMessage(TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " captcha " . TextFormat::RED . "(toggle/message/tip/title/randomize/length) - Use to on/off and set length code for captcha.");
						}
					} else {
						$sender->sendMessage(TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " captcha" . TextFormat::RED . " (toggle/message/tip/title/randomize/length) - Use to on/off and set length code for captcha.");
					}
					break;
				case "bypass":
					$data = ConfigManager::getData(ConfigManager::PERMISSION_BYPASS_ENABLE) === true ? ConfigManager::setData(ConfigManager::PERMISSION_BYPASS_ENABLE, false) : ConfigManager::setData(ConfigManager::PERMISSION_BYPASS_ENABLE, true);
					$sender->sendMessage($prefix . TextFormat::GRAY . " Bypass mode is " . (ConfigManager::getData(ConfigManager::PERMISSION_BYPASS_ENABLE) ? TextFormat::GREEN . "enable" : TextFormat::RED . "disable"));
					break;
				case "debug":
				case "analyze":
					if ($sender instanceof Player) {
						$data = $playerAPI->isDebug() === true ? $playerAPI->setDebug(false) : $playerAPI->setDebug(true);
						$sender->sendMessage($prefix . TextFormat::GRAY . " Debug mode is " . ($playerAPI->isDebug() ? TextFormat::GREEN . "enable" : TextFormat::RED . "disable"));
					} else {
						$sender->sendMessage($prefix . TextFormat::RED . " Please use this command at the game!");
					}
					break;
				case "list":
				case "modules":
				case "checks":
					$sender->sendMessage($prefix . TextFormat::GRAY . " -------------------------------");
					$sender->sendMessage($prefix . TextFormat::GRAY . " Zuri Modules/Check Information List:");
					foreach (APIProvider::Checks() as $check) {
						$sender->sendMessage($prefix . TextFormat::RESET . " " . TextFormat::AQUA . $check->getName() . TextFormat::DARK_GRAY . " (" . TextFormat::YELLOW . $check->getSubType() . TextFormat::DARK_GRAY . ") " . TextFormat::GRAY . "| " . TextFormat::AQUA . "Status: " . ($check->enable() ? TextFormat::GREEN . "Enabled" : TextFormat::RED . "Disabled") . TextFormat::GRAY . " | " . TextFormat::AQUA . "Max Internal Violation: " . TextFormat::YELLOW . $check->maxViolations() . TextFormat::GRAY . " | " . TextFormat::AQUA . "Max Violation: " . TextFormat::YELLOW . ConfigManager::getData(ConfigManager::CHECK . "." . strtolower($check->getName()) . ".maxvl"));
					}
					$sender->sendMessage($prefix . TextFormat::GRAY . " -------------------------------");
					break;
				case "ui":
					if ($sender instanceof Player) {
						FormSender::MainUI($sender);
					} else {
						$sender->sendMessage($prefix . TextFormat::RED . " Please use this command at the game!");
					}
					break;
				default:
				case "help":
					goto help; // redirect ..
					break;
			}
		} else {
			help:
			$sender->sendMessage(TextFormat::RED . "----- Zuri Anticheat -----");
			$sender->sendMessage(TextFormat::AQUA . "Build: " . TextFormat::GRAY . APIProvider::getInstance()->getDescription()->getVersion() . TextFormat::AQUA . " Author: " . TextFormat::GRAY . APIProvider::getInstance()->getDescription()->getAuthors()[0]);
			$sender->sendMessage("");
			$sender->sendMessage(TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " about" . TextFormat::GRAY . " - Show infomation the plugin.");
			$sender->sendMessage(TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " notify (toggle/admin)" . TextFormat::GRAY . " - Use to on/off notify.");
			$sender->sendMessage(TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " banmode (toggle)" . TextFormat::GRAY . " - Use to on/off ban mode.");
			$sender->sendMessage(TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " captcha (toggle/message/tip/title/randomize)" . TextFormat::GRAY . " - Use to on/off mode for captcha.");
			$sender->sendMessage(TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " bypass" . TextFormat::GRAY . " - Use to on/off for bypass mode.");
			$sender->sendMessage(TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " debug" . TextFormat::GRAY . " - Use to on/off for debug mode.");
			$sender->sendMessage(TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " list" . TextFormat::GRAY . " - List of modules in Zuri.");
			$sender->sendMessage(TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " ui" . TextFormat::GRAY . " - Sends the Admin Management UI");
			$sender->sendMessage(TextFormat::RED . "----------------------");
		}
	}
}