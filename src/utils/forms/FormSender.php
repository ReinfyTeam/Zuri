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

namespace ReinfyTeam\Zuri\utils\forms;

use pocketmine\player\Player;
use pocketmine\utils\NotCloneable;
use pocketmine\utils\NotSerializable;
use pocketmine\utils\TextFormat;
use ReinfyTeam\Zuri\API;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\config\ConfigManager;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\ZuriAC;
use function intval;
use function is_bool;
use function strtolower;

final class FormSender extends ConfigManager {
	use NotCloneable;
	use NotSerializable;

	public static function MainUI(Player $player) : void {
		$form = new SimpleForm(function(Player $player, $data) {
			if ($data === null) {
				return;
			}

			switch($data) {
				case 0:
					self::ManageModules($player);
					break;
				case 1:
					self::CaptchaSettings($player);
					break;
				case 2:
					self::AdminSettings($player);
					break;
				case 3:
					self::AdvanceTools($player);
					break;
			}
		});

		$form->setTitle("Anticheat Manager");
		$form->setContent("Choose what do you want to set..");
		$form->addButton("Manage Modules");
		$form->addButton("Captcha Settings");
		$form->addButton("Admin Settings");
		$form->addButton("Advance Tools");
		$player->sendForm($form);
	}

	public static function ManageModules(Player $player, bool $reloaded = false) : void {
		$form = new SimpleForm(function(Player $player, $data) {
			if ($data === null) {
				self::MainUI($player);
				return;
			}

			switch($data) {
				case 0:
					self::ToggleModules($player);
					break;
				case 1:
					self::PickAModule($player);
					break;
				case 2:
					self::ManageModules($player, true);
					ZuriAC::getInstance()->loadChecks();
					break;
			}
		});

		$form->setTitle("Manage Modules");
		$form->setContent(($reloaded ? "Successfully reloaded all of the modules!" : "Choose what do you want to manage.."));
		$form->addButton("Enable/Disable Modules");
		$form->addButton("Module Information");
		$form->addButton("Reload all modules");
		$player->sendForm($form);
	}

	public static function CaptchaSettings(Player $player, bool $updated = false) : void {
		$form = new CustomForm(function(Player $player, $data) {
			if ($data === null) {
				self::MainUI($player);
				return;
			}

			self::setData(self::CAPTCHA_ENABLE, $data[1]);

			if ($data[1]) {
				self::setData(self::CAPTCHA_CODE_LENGTH, $data[2]);

				if (!self::getData(self::CAPTCHA_RANDOMIZE)) {
					self::setData(self::CAPTCHA_TIP, $data[3]);
					self::setData(self::CAPTCHA_MESSAGE, $data[4]);
					self::setData(self::CAPTCHA_TITLE, $data[5]);
					self::setData(self::CAPTCHA_RANDOMIZE, $data[6]);
				} else {
					self::setData(self::CAPTCHA_RANDOMIZE, $data[4]);
				}
			}

			self::CaptchaSettings($player, true);
		});

		$form->setTitle("Captcha Settings");
		$form->addLabel(($updated ? "Updated Successfully!" : "Choose what do you want to modify.."));
		$form->addToggle("Enable Captcha", self::getData(self::CAPTCHA_ENABLE));
		if (self::getData(self::CAPTCHA_ENABLE)) {
			$form->addSlider("Length of Code", 1, 15, -1, intval(self::getData(self::CAPTCHA_CODE_LENGTH)));
			if (!self::getData(self::CAPTCHA_RANDOMIZE)) {
				$form->addToggle("Send Tip", self::getData(self::CAPTCHA_TIP));
				$form->addToggle("Send Message", self::getData(self::CAPTCHA_MESSAGE));
				$form->addToggle("Send Title", self::getData(self::CAPTCHA_TITLE));
			}
			if (self::getData(self::CAPTCHA_RANDOMIZE)) {
				$form->addLabel(TextFormat::RED . "When Random Send Type is on, to choose send type, please turn off first the random send type!");
			}
			$form->addToggle("Randomize Send Type", self::getData(self::CAPTCHA_RANDOMIZE));
		}
		$player->sendForm($form);
	}

	public static function AdminSettings(Player $player, bool $updated = false) : void {
		$form = new CustomForm(function(Player $player, $data) {
			if ($data === null) {
				self::MainUI($player);
				return;
			}

			self::setData(self::BAN_ENABLE, $data[1]);
			self::setData(self::KICK_ENABLE, $data[2]);
			self::setData(self::PERMISSION_BYPASS_ENABLE, $data[3]);
			self::setData(self::ALERTS_ENABLE, $data[4]);
			self::setData(self::DETECTION_ENABLE, $data[5]);
			self::setData(self::NETWORK_LIMIT_ENABLE, $data[6]);
			if ($data[6] === true && isset($data[7])) { // idk why this is crashing.. i think the variable isn't updated yet.. Probably this is the fix for it LOL :(
				self::setData(self::NETWORK_LIMIT, $data[7]);
			}
			self::AdminSettings($player, true);
		});

		$form->setTitle("Admin Settings");
		$form->addLabel(($updated ? "Updated Successfully!" : "Choose what do you want to change.."));
		$form->addToggle("Ban Mode", self::getData(self::BAN_ENABLE));
		$form->addToggle("Kick Mode", self::getData(self::KICK_ENABLE));
		$form->addToggle("Bypass Permission", self::getData(self::PERMISSION_BYPASS_ENABLE));
		$form->addToggle("Admin Alerts", self::getData(self::ALERTS_ENABLE));
		$form->addToggle("PreVL Detections", self::getData(self::DETECTION_ENABLE));
		$form->addToggle("Network IP Limit", self::getData(self::NETWORK_LIMIT_ENABLE));
		if (self::getData(self::NETWORK_LIMIT_ENABLE)) {
			$form->addSlider("Player IP Limit", 1, 100, -1, intval(self::getData(self::NETWORK_LIMIT)));
		}
		$player->sendForm($form);
	}

	public static function AdvanceTools(Player $player, bool $updated = false) : void {
		$form = new CustomForm(function(Player $player, $data) {
			if ($data === null) {
				self::MainUI($player);
				return;
			}

			PlayerAPI::getAPIPlayer($player)->setDebug($data[1]);
			self::setData(self::PROXY_ENABLE, $data[2]);
			self::setData(self::DISCORD_ENABLE, $data[3]);
			self::AdvanceTools($player, true);
		});

		$form->setTitle("Advance Tools");
		$form->addLabel(($updated ? "Updated Successfully!" : "Choose what do you want to toggle.."));
		$form->addToggle("Debug Mode", PlayerAPI::getAPIPlayer($player)->isDebug());
		$form->addToggle("ProxyUDP (Beta)", self::getData(self::PROXY_ENABLE));
		$form->addToggle("Discord Webhook Alerts", self::getData(self::DISCORD_ENABLE));
		$player->sendForm($form);
	}

	public static function ToggleModules(Player $player, bool $toggled = false) : void {
		$form = new CustomForm(function(Player $player, $data) {
			if ($data === null) {
				self::ManageModules($player);
				return;
			}

			// Just to filter the non-bool variable in the data.
			$status = [];
			foreach ($data as $toggle) {
				if (is_bool($toggle)) {
					$status[] = $toggle;
				}
			}

			foreach ($status as $index => $toggle) {
				$module = API::getAllChecks(false)[$index];
				self::setData(self::CHECK . "." . strtolower($module->getName()) . ".enable", $toggle);
			}
			self::ToggleModules($player, true);
		});

		$form->setTitle("Toggle Modules");
		$form->addLabel(($toggled ? "Toggled successfully!" : "Choose what do you want to toggle.."));

		foreach (API::getAllChecks(false) as $check) {
			$form->addToggle($check->getName(), $check->enable());
		}

		$player->sendForm($form);
	}

	public static function PickAModule(Player $player) : void {
		$form = new SimpleForm(function(Player $player, $data) {
			if ($data === null) {
				self::ManageModules($player);
				return;
			}

			foreach (ZuriAC::Checks() as $check) {
				if ($check->getName() === $data) {
					self::ModuleInformation($player, $check);
					return;
				}
			}
		});

		$form->setTitle("Pick a Module");
		$form->setContent("Choose what do you want to pick..");

		$list = [];
		foreach (ZuriAC::Checks() as $check) {
			if (!isset($list[$check->getName()])) {
				$list[$check->getName()] = $check->enable();
				$form->addButton($check->getName() . "\nClick to view information.", 0, "", $check->getName());
			}
		}

		$player->sendForm($form);
	}

	public static function ModuleInformation(Player $player, Check $check) : void {
		$form = new SimpleForm(function(Player $player, $data) use ($check) {
			if ($data === null) {
				self::PickAModule($player);
				return;
			}

			self::ChangeMaxVL($player, $check);
		});

		$form->setTitle($check->getName() . " Information");
		$form->setContent(TextFormat::RESET . "Name: " . TextFormat::YELLOW . $check->getName() . "\n" . TextFormat::RESET . "Sub Types: " . TextFormat::YELLOW . $check->getAllSubTypes() . "\n" . TextFormat::RESET . "Status: " . ($check->enable() ? TextFormat::GREEN . "Enabled" : TextFormat::RED . "Disabled") . "\n" . TextFormat::RESET . "Ban: " . ($check->getPunishment() === "ban" ? TextFormat::GREEN . "Yes" : TextFormat::RED . "No") . "\n" . TextFormat::RESET . "Kick: " . ($check->getPunishment() === "kick" ? TextFormat::GREEN . "Yes" : TextFormat::RED . "No") . "\n" . TextFormat::RESET . "Captcha: " . ($check->getPunishment() === "captcha" ? TextFormat::GREEN . "Yes" : TextFormat::RED . "No") . "\n" . TextFormat::RESET . "Flag: " . ($check->getPunishment() === "flag" ? TextFormat::GREEN . "Yes" : TextFormat::RED . "No") . "\n" . TextFormat::RESET . "PreVL Max Violation: " . TextFormat::YELLOW . ($check->maxViolations() === 0 ? "Instant Fail" : $check->maxViolations()) . "\n" . TextFormat::RESET . "Max Violation: " . TextFormat::YELLOW . (self::getData(self::CHECK . "." . strtolower($check->getName()) . ".maxvl") === 0 ? "Instant Punishment" : self::getData(self::CHECK . "." . strtolower($check->getName()) . ".maxvl"))); // bullshit this is so long..
		if (self::getData(self::CHECK . "." . strtolower($check->getName()) . ".maxvl") !== 0) {
			$form->addButton("Change MaxVL");
		}
		$player->sendForm($form);
	}

	public static function ChangeMaxVL(Player $player, Check $check, bool $saved = false) : void {
		$form = new CustomForm(function(Player $player, $data) use ($check) {
			if ($data === null) {
				self::ModuleInformation($player, $check);
				return;
			}

			if ($data[1] !== null) {
				self::setData(self::CHECK . "." . strtolower($check->getName()) . ".maxvl", intval($data[1]));
				self::ChangeMaxVL($player, $check, true);
			}
		});

		$form->setTitle($check->getName() . " MaxVL");
		$form->addLabel(($saved ? TextFormat::GREEN . "Modified successfully!" : "Modify the slider do you want to set.."));
		$form->addSlider("MaxVL", 0, 100, -1, intval(self::getData(self::CHECK . "." . strtolower($check->getName()) . ".maxvl")));
		$player->sendForm($form);
	}
}