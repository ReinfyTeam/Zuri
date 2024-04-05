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

use pocketmine\player\Player;
use pocketmine\utils\NotCloneable;
use pocketmine\utils\NotSerializable;
use pocketmine\utils\TextFormat;
use ReinfyTeam\Zuri\APIProvider;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\config\ConfigManager;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\forms\CustomForm;
use ReinfyTeam\Zuri\utils\forms\SimpleForm;
use function explode;
use function intval;
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
					self::ModifyCaptcha($player);
					break;
				case 2:
					self::ChangePunishment($player);
					break;
				case 3:
					self::AdvanceTools($player);
					break;
			}
		});

		$form->setTitle("Zuri AdminUI Manager");
		$form->setContent("Choose what do you want to set..");
		$form->addButton("Manage Modules");
		$form->addButton("Modify Captcha");
		$form->addButton("Change Punishment");
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
					APIProvider::getInstance()->loadChecks();
					break;
			}
		});

		$form->setTitle("Manage Modules");
		$form->setContent(($reloaded ? TextFormat::GREEN . "Successfully reloaded all of the modules!" : "Choose what do you want to manage.."));
		$form->addButton("Enable/Disable Modules");
		$form->addButton("Module Information");
		$form->addButton("Reload all modules");
		$player->sendForm($form);
	}

	public static function ModifyCaptcha(Player $player) : void {
		$form = new SimpleForm(function(Player $player, $data) {
			if ($data === null) {
				self::MainUI($player);
				return;
			}

			switch($data) {
				case 0:
					self::setData(self::CAPTCHA_ENABLE, !self::getData(self::CAPTCHA_ENABLE));
					self::ModifyCaptcha($player);
					break;
				case 1:
					self::AdjustCodeLength($player);
					break;
				case 2:
					self::ModifySendType($player);
					break;
			}
		});

		$form->setTitle("Modify Captcha");
		$form->setContent("Choose what do you want to modify..");
		$form->addButton("Status: " . (self::getData(self::CAPTCHA_ENABLE) ? TextFormat::GREEN . "Enabled" : TextFormat::RED . "Disabled"));
		$form->addButton("Adjust Code Length");
		$form->addButton("Modify send type");
		$player->sendForm($form);
	}

	public static function ChangePunishment(Player $player) : void {
		$form = new SimpleForm(function(Player $player, $data) {
			if ($data === null) {
				self::MainUI($player);
				return;
			}

			switch($data) {
				case 0:
					self::setData(self::BAN_ENABLE, !self::getData(self::BAN_ENABLE));
					break;
				case 1:
					self::setData(self::KICK_ENABLE, !self::getData(self::KICK_ENABLE));
					break;
				case 2:
					self::setData(self::PERMISSION_BYPASS_ENABLE, !self::getData(self::PERMISSION_BYPASS_ENABLE));
					break;
				case 3:
					self::setData(self::ALERTS_ENABLE, !self::getData(self::ALERTS_ENABLE));
					break;
				case 4:
					self::setData(self::DETECTION_ENABLE, !self::getData(self::DETECTION_ENABLE));
					break;
			}

			self::ChangePunishment($player);
		});

		$form->setTitle("Change Punishment");
		$form->setContent("Choose what do you want to change..");
		$form->addButton("Ban Mode: " . (self::getData(self::BAN_ENABLE) ? TextFormat::GREEN . "Yes" : TextFormat::RED . "No"));
		$form->addButton("Kick Mode: " . (self::getData(self::KICK_ENABLE) ? TextFormat::GREEN . "Yes" : TextFormat::RED . "No"));
		$form->addButton("Bypass Mode: " . (self::getData(self::PERMISSION_BYPASS_ENABLE) ? TextFormat::GREEN . "Yes" : TextFormat::RED . "No"));
		$form->addButton("Alert Failed Message: " . (self::getData(self::ALERTS_ENABLE) ? TextFormat::GREEN . "Yes" : TextFormat::RED . "No"));
		$form->addButton("Detection Message: " . (self::getData(self::DETECTION_ENABLE) ? TextFormat::GREEN . "Yes" : TextFormat::RED . "No"));
		$player->sendForm($form);
	}

	public static function AdvanceTools(Player $player) : void {
		$form = new SimpleForm(function(Player $player, $data) {
			if ($data === null) {
				self::MainUI($player);
				return;
			}

			switch($data) {
				case 0:
					PlayerAPI::getAPIPlayer($player)->setDebug(!PlayerAPI::getAPIPlayer($player)->isDebug());
					break;
				case 1:
					self::setData(self::PROXY_ENABLE, !self::getData(self::PROXY_ENABLE));
					break;
				case 2:
					self::setData(self::DISCORD_ENABLE, !self::getData(self::DISCORD_ENABLE));
					break;
			}

			self::AdvanceTools($player);
		});

		$form->setTitle("Advance Tools");
		$form->setContent("Choose what do you want to select..");
		$form->addButton("Debug Mode: " . (PlayerAPI::getAPIPlayer($player)->isDebug() ? TextFormat::GREEN . "Enabled" : TextFormat::RED . "Disabled"));
		$form->addButton("Proxy: " . (self::getData(self::PROXY_ENABLE) ? TextFormat::GREEN . "Enabled" : TextFormat::RED . "Disabled"));
		$form->addButton("Discord Webhook: " . (self::getData(self::DISCORD_ENABLE) ? TextFormat::GREEN . "Enabled" : TextFormat::RED . "Disabled"));
		$player->sendForm($form);
	}

	public static function ToggleModules(Player $player, bool $toggled = false) : void {
		$form = new SimpleForm(function(Player $player, $data) {
			if ($data === null) {
				self::ManageModules($player);
				return;
			}
			$info = explode(":", $data);
			foreach (APIProvider::Checks() as $check) {
				if ($check->getName() === $info[0] && $check->getSubType() === $info[1]) {
					self::setData(self::CHECK . "." . strtolower($check->getName()) . ".enable", !self::getData(self::CHECK . "." . strtolower($check->getName()) . ".enable"));
					self::ToggleModules($player, true);
				} else {
					continue;
				}
			}
		});

		$form->setTitle("Toggle Modules");
		$form->setContent(($toggled ? TextFormat::GREEN . "Toggled successfully!" : "Choose what do you want to toggle.."));
		foreach (APIProvider::Checks() as $check) {
			$form->addButton(TextFormat::AQUA . $check->getName() . TextFormat::DARK_GRAY . " (" . TextFormat::YELLOW . $check->getSubType() . TextFormat::DARK_GRAY . ")" . "\n" . TextFormat::DARK_GRAY . "Status: " . ($check->enable() ? TextFormat::GREEN . "Enabled" : TextFormat::RED . "Disabled"), 0, "", $check->getName() . ":" . $check->getSubType());
		}
		$player->sendForm($form);
	}

	public static function PickAModule(Player $player) : void {
		$form = new SimpleForm(function(Player $player, $data) {
			if ($data === null) {
				self::ManageModules($player);
				return;
			}

			$info = explode(":", $data);
			foreach (APIProvider::Checks() as $check) {
				if ($check->getName() === $info[0] && $check->getSubType() === $info[1]) {
					self::ModuleInformation($player, $check);
				} else {
					continue;
				}
			}
		});

		$form->setTitle("Pick a Module");
		$form->setContent("Choose what do you want to pick..");
		foreach (APIProvider::Checks() as $check) {
			$form->addButton(TextFormat::AQUA . $check->getName() . TextFormat::DARK_GRAY . " (" . TextFormat::YELLOW . $check->getSubType() . TextFormat::DARK_GRAY . ")" . "\nClick to view information.", 0, "", $check->getName() . ":" . $check->getSubType());
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

		$form->setTitle($check->getName() . " (" . $check->getSubType() . ") Information");
		$form->setContent(TextFormat::RESET . "Name: " . TextFormat::YELLOW . $check->getName() . "\n" . TextFormat::RESET . "Sub Type: " . TextFormat::YELLOW . $check->getSubType() . "\n" . TextFormat::RESET . "Status: " . ($check->enable() ? TextFormat::GREEN . "Enabled" : TextFormat::RED . "Disabled") . "\n" . TextFormat::RESET . "Ban: " . ($check->ban() ? TextFormat::GREEN . "Yes" : TextFormat::RED . "No") . "\n" . TextFormat::RESET . "Kick: " . ($check->kick() ? TextFormat::GREEN . "Yes" : TextFormat::RED . "No") . "\n" . TextFormat::RESET . "Captcha: " . ($check->captcha() ? TextFormat::GREEN . "Yes" : TextFormat::RED . "No") . "\n" . TextFormat::RESET . "Flag: " . ($check->flag() ? TextFormat::GREEN . "Yes" : TextFormat::RED . "No") . "\n" . TextFormat::RESET . "Max Internal Violation: " . TextFormat::YELLOW . $check->maxViolations() . "\n" . TextFormat::RESET . "Max Violation: " . TextFormat::YELLOW . self::getData(self::CHECK . "." . strtolower($check->getName()) . ".maxvl"));
		if ($check->maxViolations() !== 0) {
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

		$form->setTitle($check->getName() . " (" . $check->getSubType() . ") MaxVL");
		$form->addLabel(($saved ? TextFormat::GREEN . "Modified successfully!" : "Modify the slider do you want to set.."));
		$form->addSlider("MaxVL", 1, 100, -1, intval(self::getData(self::CHECK . "." . strtolower($check->getName()) . ".maxvl")));
		$player->sendForm($form);
	}

	public static function AdjustCodeLength(Player $player, bool $saved = false) : void {
		$form = new CustomForm(function(Player $player, $data) {
			if ($data === null) {
				return;
			}

			if ($data[1] !== null) {
				self::setData(self::CAPTCHA_CODE_LENGTH, intval($data[1]));
				self::ModifyCaptcha($player, true);
			}
		});

		$form->setTitle("Captcha Code Generator");
		$form->addLabel(($saved ? TextFormat::GREEN . "Changed successfully!" : "Modify the slider do you want to set.."));
		$form->addSlider("Length of Code", 1, 15, -1, intval(self::getData(self::CAPTCHA_CODE_LENGTH)));
		$player->sendForm($form);
	}

	public static function ModifySendType(Player $player, bool $saved = false) : void {
		$form = new CustomForm(function(Player $player, $data) {
			if ($data === null) {
				return;
			}
			if (self::getData(self::CAPTCHA_RANDOMIZE)) {
				if ($data[2] !== null) {
					self::setData(self::CAPTCHA_RANDOMIZE, $data[2]);
				}
			} else {
				if ($data[1] !== null) {
					self::setData(self::CAPTCHA_TIP, $data[1]);
				}

				if ($data[2] !== null) {
					self::setData(self::CAPTCHA_MESSAGE, $data[2]);
				}

				if ($data[3] !== null) {
					self::setData(self::CAPTCHA_TITLE, $data[3]);
				}

				if ($data[4] !== null) {
					self::setData(self::CAPTCHA_RANDOMIZE, $data[4]);
				}
			}

			self::ModifyCaptcha($player, true);
		});

		$form->setTitle("Modify Send Type");
		$form->addLabel(($saved ? TextFormat::GREEN . "Modified successfully!" : "Modify the what do you want to set.."));
		if (!self::getData(self::CAPTCHA_RANDOMIZE)) {
			$form->addToggle("Send Tip", self::getData(self::CAPTCHA_TIP));
		}
		if (!self::getData(self::CAPTCHA_RANDOMIZE)) {
			$form->addToggle("Send Message", self::getData(self::CAPTCHA_MESSAGE));
		}
		if (!self::getData(self::CAPTCHA_RANDOMIZE)) {
			$form->addToggle("Send Title", self::getData(self::CAPTCHA_TITLE));
		}
		if (self::getData(self::CAPTCHA_RANDOMIZE)) {
			$form->addLabel(TextFormat::RED . "When Random Send Type is on, to choose send type, please turn off first the random send type!");
		}
		$form->addToggle("Randomize Send Type", self::getData(self::CAPTCHA_RANDOMIZE));
		$player->sendForm($form);
	}
}