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
		$playerAPI = PlayerAPI::getAPIPlayer($sender);
		if ($sender instanceof Player) {
			if (isset($args[0])) {
				switch($args[0]) {
					case "about":
						$sender->sendMessage(TextFormat::AQUA . "Build: " . TextFormat::GRAY . APIProvider::VERSION_PLUGIN . TextFormat::AQUA . " Author: " . TextFormat::GRAY . "ReinfyTeam");
						break;
					case "notify":
						if (isset($args[1])) {
							switch($args[1]) {
								case "toggle":
									$data = ConfigManager::getData(ConfigManager::ALERTS_ENABLE) === true ? ConfigManager::setData(ConfigManager::ALERTS_ENABLE, false) : ConfigManager::setData(ConfigManager::ALERTS_ENABLE, true);
									$sender->sendMessage($prefix . TextFormat::GRAY . " Notify toggle is " . (ConfigManager::getData(ConfigManager::ALERTS_ENABLE) ? TextFormat::GREEN . "enable" : TextFormat::RED . "disable"));
									break;
								case "admin":
									$data = ConfigManager::getData(ConfigManager::ALERTS_ADMIN) === true ? ConfigManager::setData(ConfigManager::ALERTS_ADMIN, false) : ConfigManager::setData(ConfigManager::ALERTS_ADMIN, true);
									$sender->sendMessage($prefix . TextFormat::GRAY . " Notify admin mode is " . (ConfigManager::getData(ConfigManager::ALERTS_ADMIN) ? TextFormat::GREEN . "enable" : TextFormat::RED . "disable"));
									break;
								default: $sender->sendMessage(TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " notify (toggle/admin) - Use to on/off notify.");
							}
						} else {
							$sender->sendMessage(TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " notify (toggle/admin) - Use to on/off notify.");
						}
						break;
					case "process":
						if (isset($args[1])) {
							switch($args[1]) {
								case "auto":
									$data = ConfigManager::getData(ConfigManager::PROCESS_AUTO) === true ? ConfigManager::setData(ConfigManager::PROCESS_AUTO, false) : ConfigManager::setData(ConfigManager::PROCESS_AUTO, true);
									$sender->sendMessage($prefix . TextFormat::GRAY . " Automatic processing is " . (ConfigManager::getData(ConfigManager::PROCESS_AUTO) ? TextFormat::GREEN . "enable" : TextFormat::RED . "disable"));
									break;
								default: $sender->sendMessage(TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " process (auto/immediately) - Use to on/off process.");
							}
						} else {
							$sender->sendMessage(TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " process (auto/immediately) - Use to on/off process.");
						}
						break;
					case "xray":
						$data = ConfigManager::getData(ConfigManager::XRAY_ENABLE) === true ? ConfigManager::setData(ConfigManager::XRAY_ENABLE, false) : ConfigManager::setData(ConfigManager::XRAY_ENABLE, true);
						$sender->sendMessage($prefix . TextFormat::GRAY . " AntiXray is " . (ConfigManager::getData(ConfigManager::XRAY_ENABLE) ? TextFormat::GREEN . "enable" : TextFormat::RED . "disable"));
						break;
					case "banmode":
						if (isset($args[1])) {
							switch($args[1]) {
								case "toggle":
									$data = ConfigManager::getData(ConfigManager::BAN_ENABLE) === true ? ConfigManager::setData(ConfigManager::BAN_ENABLE, false) : ConfigManager::setData(ConfigManager::BAN_ENABLE, true);
									$sender->sendMessage($prefix . TextFormat::GRAY . " Ban Mode is " . (ConfigManager::getData(ConfigManager::BAN_ENABLE) ? TextFormat::GREEN . "enable" : TextFormat::RED . "disable"));
									break;
								case "randomize":
									$data = ConfigManager::getData(ConfigManager::BAN_RANDOMIZE) === true ? ConfigManager::setData(ConfigManager::BAN_RANDOMIZE, false) : ConfigManager::setData(ConfigManager::BAN_RANDOMIZE, true);
									$sender->sendMessage($prefix . TextFormat::GRAY . " Ban Randomize mode is " . (ConfigManager::getData(ConfigManager::BAN_RANDOMIZE) ? TextFormat::GREEN . "enable" : TextFormat::RED . "disable"));
									break;
								default: $sender->sendMessage(TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " banmode (toggle/randomize) - Use to on/off ban mode.");
							}
						} else {
							$sender->sendMessage(TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " banmode (toggle/randomize) - Use to on/off ban mode.");
						}
						break;
					case "captcha":
						if (isset($args[1])) {
							switch($args[1]) {
								case "toggle":
									$data = ConfigManager::getData(ConfigManager::CAPTCHA_ENABLE) === true ? ConfigManager::setData(ConfigManager::CAPTCHA_ENABLE, false) : ConfigManager::setData(ConfigManager::CAPTCHA_ENABLE, true);
									$sender->sendMessage($prefix . TextFormat::GRAY . " Captcha is " . (ConfigManager::getData(ConfigManager::CAPTCHA_ENABLE) ? TextFormat::GREEN . "enable" : TextFormat::RED . "disable"));
									break;
								case "message":
									$data = ConfigManager::getData(ConfigManager::CAPTCHA_MESSAGE) === true ? ConfigManager::setData(ConfigManager::CAPTCHA_MESSAGE, false) : ConfigManager::setData(ConfigManager::CAPTCHA_MESSAGE, true);
									$sender->sendMessage($prefix . TextFormat::GRAY . " Message Captcha is " . (ConfigManager::getData(ConfigManager::CAPTCHA_MESSAGE) ? TextFormat::GREEN . "enable" : TextFormat::RED . "disable"));
									break;
								case "tip":
									$data = ConfigManager::getData(ConfigManager::CAPTCHA_TIP) === true ? ConfigManager::setData(ConfigManager::CAPTCHA_TIP, false) : ConfigManager::setData(ConfigManager::CAPTCHA_TIP, true);
									$sender->sendMessage($prefix . TextFormat::GRAY . " Tip Captcha is " . (ConfigManager::getData(ConfigManager::CAPTCHA_TIP) ? TextFormat::GREEN . "enable" : TextFormat::RED . "disable"));
									break;
								case "title":
									$data = ConfigManager::getData(ConfigManager::CAPTCHA_TITLE) === true ? ConfigManager::setData(ConfigManager::CAPTCHA_TITLE, false) : ConfigManager::setData(ConfigManager::CAPTCHA_TITLE, true);
									$sender->sendMessage($prefix . TextFormat::GRAY . " Title Captcha is " . (ConfigManager::getData(ConfigManager::CAPTCHA_TITLE) ? TextFormat::GREEN . "enable" : TextFormat::RED . "disable"));
									break;
								case "randomize":
									$data = ConfigManager::getData(ConfigManager::CAPTCHA_RANDOMIZE) === true ? ConfigManager::setData(ConfigManager::CAPTCHA_RANDOMIZE, false) : ConfigManager::setData(ConfigManager::CAPTCHA_RANDOMIZE, true);
									$sender->sendMessage($prefix . TextFormat::GRAY . " Randomize Mode is " . (ConfigManager::getData(ConfigManager::CAPTCHA_RANDOMIZE) ? TextFormat::GREEN . "enable" : TextFormat::RED . "disable"));
									break;
								default: $sender->sendMessage(TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " captcha (toggle/message/tip/title/randomize/length) - Use to on/off and set length code for captcha.");
							}
						} else {
							$sender->sendMessage(TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " captcha (toggle/message/tip/title/randomize/length) - Use to on/off and set length code for captcha.");
						}
						break;
					case "bypass":
						$data = ConfigManager::getData(ConfigManager::PERMISSION_BYPASS_ENABLE) === true ? ConfigManager::setData(ConfigManager::PERMISSION_BYPASS_ENABLE, false) : ConfigManager::setData(ConfigManager::PERMISSION_BYPASS_ENABLE, true);
						$sender->sendMessage($prefix . TextFormat::GRAY . " Bypass mode is " . (ConfigManager::getData(ConfigManager::PERMISSION_BYPASS_ENABLE) ? TextFormat::GREEN . "enable" : TextFormat::RED . "disable"));
						break;
					case "debug":
						$data = $playerAPI->isDebug() === true ? $playerAPI->setDebug(false) : $playerAPI->setDebug(true);
						$sender->sendMessage($prefix . TextFormat::GRAY . " Debug mode is " . ($playerAPI->isDebug() ? TextFormat::GREEN . "enable" : TextFormat::RED . "disable"));
						break;
					default:
						$sender->sendMessage(TextFormat::RED . "----- Zuri Anticheat -----");
						$sender->sendMessage(TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " about" . TextFormat::GRAY . " - Show infomation the plugin.");
						$sender->sendMessage(TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " notify (toggle/admin)" . TextFormat::GRAY . " - Use to on/off notify.");
						$sender->sendMessage(TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " process (auto)" . TextFormat::GRAY . " - Use to on/off process.");
						//$sender->sendMessage(TextFormat::RED."/".$namecmd.TextFormat::RESET." xray".TextFormat::GRAY." - Use to on/off check xray.");
						$sender->sendMessage(TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " banmode (toggle/randomize)" . TextFormat::GRAY . " - Use to on/off ban mode.");
						$sender->sendMessage(TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " captcha (toggle/message/tip/title/randomize)" . TextFormat::GRAY . " - Use to on/off mode for captcha.");
						$sender->sendMessage(TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " bypass" . TextFormat::GRAY . " - Use to on/off for bypass mode.");
						$sender->sendMessage(TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " debug" . TextFormat::GRAY . " - Use to on/off for debug mode.");
						$sender->sendMessage(TextFormat::RED . "----------------------");
						break;
				}
			} else {
				$sender->sendMessage(TextFormat::RED . "----- Zuri Anticheat -----");
				$sender->sendMessage(TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " about" . TextFormat::GRAY . " - Show infomation the plugin.");
				$sender->sendMessage(TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " notify (toggle/admin)" . TextFormat::GRAY . " - Use to on/off notify.");
				$sender->sendMessage(TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " process (auto)" . TextFormat::GRAY . " - Use to on/off process.");
				//$sender->sendMessage(TextFormat::RED."/".$namecmd.TextFormat::RESET." xray".TextFormat::GRAY." - Use to on/off check xray.");
				$sender->sendMessage(TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " banmode (toggle/randomize)" . TextFormat::GRAY . " - Use to on/off ban mode.");
				$sender->sendMessage(TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " captcha (toggle/message/tip/title/randomize)" . TextFormat::GRAY . " - Use to on/off mode for captcha.");
				$sender->sendMessage(TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " bypass" . TextFormat::GRAY . " - Use to on/off for bypass mode.");
				$sender->sendMessage(TextFormat::RED . "/" . $namecmd . TextFormat::RESET . " debug" . TextFormat::GRAY . " - Use to on/off for debug mode.");
				$sender->sendMessage(TextFormat::RED . "----------------------");
			}
		} else {
			$sender->sendMessage($prefix . TextFormat::RED . " Please use this command at the game!");
		}
	}
}