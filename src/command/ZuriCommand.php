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
use function intval;
use function is_float;
use function strtolower;

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
		if ($sender instanceof Player) {
			$playerAPI = PlayerAPI::getAPIPlayer($sender);
		}
		if (isset($args[0])) {
			switch(strtolower($args[0])) {
				case "about":
				case "info":
					$sender->sendMessage(TextFormat::AQUA . "Build: " . TextFormat::GRAY . ZuriAC::getInstance()->getDescription()->getVersion() . TextFormat::AQUA . " Author: " . TextFormat::GRAY . ZuriAC::getInstance()->getDescription()->getAuthors()[0]);
					$sender->sendMessage(TextFormat::AQUA . "Total Enabled Checks: " . TextFormat::GRAY . count(API::getAllEnabledChecks(false)) . " (With SubTypes: " . count(API::getAllEnabledChecks()) . ")");
					$sender->sendMessage(TextFormat::AQUA . "Total Disabled Checks: " . TextFormat::GRAY . count(API::getAllDisabledChecks(false)) . " (With SubTypes: " . count(API::getAllDisabledChecks()) . ")");
					$sender->sendMessage(TextFormat::AQUA . "Total All Checks: " . TextFormat::GRAY . count(API::getAllChecks(false)) . " (With SubTypes: " . count(API::getAllChecks()) . ")");
					break;
				case "notify":
				case "notification":
					if (isset($args[1])) {
						switch(strtolower($args[1])) {
							case "toggle":
								$data = ConfigManager::getData(ConfigPaths::ALERTS_ENABLE) === true ? ConfigManager::setData(ConfigPaths::ALERTS_ENABLE, false) : ConfigManager::setData(ConfigPaths::ALERTS_ENABLE, true);
								$sender->sendMessage($prefix . TextFormat::GRAY . " Notify toggle is " . (ConfigManager::getData(ConfigPaths::ALERTS_ENABLE) ? TextFormat::GREEN . "enable" : TextFormat::RED . "disable"));
								break;
							case "admin":
								$data = ConfigManager::getData(ConfigPaths::ALERTS_ADMIN) === true ? ConfigManager::setData(ConfigPaths::ALERTS_ADMIN, false) : ConfigManager::setData(ConfigPaths::ALERTS_ADMIN, true);
								$sender->sendMessage($prefix . TextFormat::GRAY . " Notify admin mode is " . (ConfigManager::getData(ConfigPaths::ALERTS_ADMIN) ? TextFormat::GREEN . "enable" : TextFormat::RED . "disable"));
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
								$data = ConfigManager::getData(ConfigPaths::BAN_ENABLE) === true ? ConfigManager::setData(ConfigPaths::BAN_ENABLE, false) : ConfigManager::setData(ConfigPaths::BAN_ENABLE, true);
								$sender->sendMessage($prefix . TextFormat::GRAY . " Ban Mode is " . (ConfigManager::getData(ConfigPaths::BAN_ENABLE) ? TextFormat::GREEN . "enable" : TextFormat::RED . "disable"));
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
								$data = ConfigManager::getData(ConfigPaths::CAPTCHA_ENABLE) === true ? ConfigManager::setData(ConfigPaths::CAPTCHA_ENABLE, false) : ConfigManager::setData(ConfigPaths::CAPTCHA_ENABLE, true);
								$sender->sendMessage($prefix . TextFormat::GRAY . " Captcha is " . (ConfigManager::getData(ConfigPaths::CAPTCHA_ENABLE) ? TextFormat::GREEN . "enable" : TextFormat::RED . "disable"));
								break;
							case "message":
								if (!ConfigManager::getData(ConfigPaths::CAPTCHA_RANDOMIZE)) {
									$data = ConfigManager::getData(ConfigPaths::CAPTCHA_MESSAGE) === true ? ConfigManager::setData(ConfigPaths::CAPTCHA_MESSAGE, false) : ConfigManager::setData(ConfigPaths::CAPTCHA_MESSAGE, true);
									$sender->sendMessage($prefix . TextFormat::GRAY . " Message Captcha is " . (ConfigManager::getData(ConfigPaths::CAPTCHA_MESSAGE) ? TextFormat::GREEN . "enable" : TextFormat::RED . "disable"));
								} else {
									$sender->sendMessage($prefix . TextFormat::RED . " Randomize is on! Turn off randomize to toggle this!");
								}
								break;
							case "tip":
								if (!ConfigManager::getData(ConfigPaths::CAPTCHA_RANDOMIZE)) {
									$data = ConfigManager::getData(ConfigPaths::CAPTCHA_TIP) === true ? ConfigManager::setData(ConfigPaths::CAPTCHA_TIP, false) : ConfigManager::setData(ConfigPaths::CAPTCHA_TIP, true);
									$sender->sendMessage($prefix . TextFormat::GRAY . " Tip Captcha is " . (ConfigManager::getData(ConfigPaths::CAPTCHA_TIP) ? TextFormat::GREEN . "enable" : TextFormat::RED . "disable"));
								} else {
									$sender->sendMessage($prefix . TextFormat::RED . " Randomize is on! Turn off randomize to toggle this!");
								}
								break;
							case "title":
								if (!ConfigManager::getData(ConfigPaths::CAPTCHA_RANDOMIZE)) {
									$data = ConfigManager::getData(ConfigPaths::CAPTCHA_TITLE) === true ? ConfigManager::setData(ConfigPaths::CAPTCHA_TITLE, false) : ConfigManager::setData(ConfigPaths::CAPTCHA_TITLE, true);
									$sender->sendMessage($prefix . TextFormat::GRAY . " Title Captcha is " . (ConfigManager::getData(ConfigPaths::CAPTCHA_TITLE) ? TextFormat::GREEN . "enable" : TextFormat::RED . "disable"));
								} else {
									$sender->sendMessage($prefix . TextFormat::RED . " Randomize is on! Turn off randomize to toggle this!");
								}
								break;
							case "randomize":
								ConfigManager::getData(ConfigPaths::CAPTCHA_RANDOMIZE) === true ? ConfigManager::setData(ConfigPaths::CAPTCHA_RANDOMIZE, false) : ConfigManager::setData(ConfigPaths::CAPTCHA_RANDOMIZE, true);
								$sender->sendMessage($prefix . TextFormat::GRAY . " Randomize Mode is " . (ConfigManager::getData(ConfigPaths::CAPTCHA_RANDOMIZE) ? TextFormat::GREEN . "enable" : TextFormat::RED . "disable"));
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
								ConfigManager::setData(ConfigPaths::CAPTCHA_CODE_LENGTH, intval($args[2])); // incase of using ""
								break;
							default: $sender->sendMessage(TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " captcha " . TextFormat::RED . "(toggle/message/tip/title/randomize/length) - Use to on/off and set length code for captcha.");
						}
					} else {
						$sender->sendMessage(TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " captcha" . TextFormat::RED . " (toggle/message/tip/title/randomize/length) - Use to on/off and set length code for captcha.");
					}
					break;
				case "bypass":
					$data = ConfigManager::getData(ConfigPaths::PERMISSION_BYPASS_ENABLE) === true ? ConfigManager::setData(ConfigPaths::PERMISSION_BYPASS_ENABLE, false) : ConfigManager::setData(ConfigPaths::PERMISSION_BYPASS_ENABLE, true);
					$sender->sendMessage($prefix . TextFormat::GRAY . " Bypass mode is " . (ConfigManager::getData(ConfigPaths::PERMISSION_BYPASS_ENABLE) ? TextFormat::GREEN . "enable" : TextFormat::RED . "disable"));
					break;
				case "debug":
				case "analyze":
					if ($sender instanceof Player) {
						$data = $playerAPI->isDebug() === true ? $playerAPI->setDebug(false) : $playerAPI->setDebug();
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
					$added = [];
					foreach (ZuriAC::Checks() as $check) {
						if (!isset($added[$check->getName()])) {
							$sender->sendMessage($prefix . TextFormat::RESET . " " . TextFormat::AQUA . $check->getName() . TextFormat::DARK_GRAY . " (" . TextFormat::YELLOW . $check->getAllSubTypes() . TextFormat::DARK_GRAY . ") " . TextFormat::GRAY . "| " . TextFormat::AQUA . "Status: " . ($check->enable() ? TextFormat::GREEN . "Enabled" : TextFormat::RED . "Disabled") . TextFormat::GRAY . " | " . TextFormat::AQUA . "Max Violation: " . TextFormat::YELLOW . ConfigManager::getData(ConfigPaths::CHECK . "." . strtolower($check->getName()) . ".maxvl"));
							$added[$check->getName()] = true;
						}
					}
					$sender->sendMessage($prefix . TextFormat::GRAY . " -------------------------------");
					break;
				case "ui":
				case "forms":
				case "form":
				case "gui":
					if ($sender instanceof Player) {
						FormSender::MainUI($sender);
					} else {
						$sender->sendMessage($prefix . TextFormat::RED . " Please use this command at the game!");
					}
					break;
				default:
				case "help":
				case "noarguments":
				case "cmd":
					goto help; // Re-Direct.
            }
		} else {
			help:
			$sender->sendMessage(TextFormat::RED . "----- Zuri Anticheat -----");
			$sender->sendMessage(TextFormat::AQUA . "Build: " . TextFormat::GRAY . ZuriAC::getInstance()->getDescription()->getVersion() . TextFormat::AQUA . " Author: " . TextFormat::GRAY . ZuriAC::getInstance()->getDescription()->getAuthors()[0]);
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