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
use ReinfyTeam\Zuri\lang\Lang;
use ReinfyTeam\Zuri\lang\LangKeys;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\ZuriAC;
use function array_search;
use function intval;
use function is_array;
use function is_bool;
use function is_int;
use function is_numeric;
use function is_string;
use function strtolower;
use function strtoupper;

final class FormSender extends ConfigManager {
	use NotCloneable;
	use NotSerializable;

	private static function boolData(string $path, bool $default = false) : bool {
		$value = self::getData($path, $default);
		if (is_bool($value)) {
			return $value;
		}
		if (is_int($value)) {
			return $value !== 0;
		}
		if (is_string($value)) {
			return $value === "1" || strtolower($value) === "true";
		}
		return $default;
	}

	private static function intData(string $path, int $default = 0) : int {
		$value = self::getData($path, $default);
		if (is_int($value)) {
			return $value;
		}
		if (is_numeric($value)) {
			return (int) $value;
		}
		return $default;
	}

	private static function stringData(string $path, string $default = "") : string {
		$value = self::getData($path, $default);
		return is_string($value) ? $value : $default;
	}

	/** @return array<string,mixed> */
	private static function arrayData(string $path) : array {
		$value = self::getData($path, []);
		return is_array($value) ? $value : [];
	}

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
		$form = new CustomForm(function(Player $player, mixed $data) {
			if ($data === null) {
				self::MainUI($player);
				return;
			}
			if (!is_array($data)) {
				self::CaptchaSettings($player);
				return;
			}

			self::setData(self::CAPTCHA_ENABLE, (bool) ($data[1] ?? false));

			if ((bool) ($data[1] ?? false)) {
				self::setData(self::CAPTCHA_CODE_LENGTH, (int) ($data[2] ?? 6));

				if (!self::getData(self::CAPTCHA_RANDOMIZE)) {
					self::setData(self::CAPTCHA_TIP, (bool) ($data[3] ?? false));
					self::setData(self::CAPTCHA_MESSAGE, (bool) ($data[4] ?? false));
					self::setData(self::CAPTCHA_TITLE, (bool) ($data[5] ?? false));
					self::setData(self::CAPTCHA_RANDOMIZE, (bool) ($data[6] ?? false));
				} else {
					self::setData(self::CAPTCHA_RANDOMIZE, (bool) ($data[4] ?? false));
				}
			}

			self::CaptchaSettings($player, true);
		});

		$form->setTitle("Captcha Settings");
		$form->addLabel(($updated ? "Updated Successfully!" : "Choose what do you want to modify.."));
		$form->addToggle("Enable Captcha", self::boolData(self::CAPTCHA_ENABLE));
		if (self::boolData(self::CAPTCHA_ENABLE)) {
			$form->addSlider("Length of Code", 1, 15, -1, self::intData(self::CAPTCHA_CODE_LENGTH, 6));
			if (!self::boolData(self::CAPTCHA_RANDOMIZE)) {
				$form->addToggle("Send Tip", self::boolData(self::CAPTCHA_TIP));
				$form->addToggle("Send Message", self::boolData(self::CAPTCHA_MESSAGE));
				$form->addToggle("Send Title", self::boolData(self::CAPTCHA_TITLE));
			}
			if (self::boolData(self::CAPTCHA_RANDOMIZE)) {
				$form->addLabel(TextFormat::RED . "When Random Send Type is on, to choose send type, please turn off first the random send type!");
			}
			$form->addToggle("Randomize Send Type", self::boolData(self::CAPTCHA_RANDOMIZE));
		}
		$player->sendForm($form);
	}

	public static function AdminSettings(Player $player, bool $updated = false) : void {
		$form = new CustomForm(function(Player $player, mixed $data) {
			if ($data === null) {
				self::MainUI($player);
				return;
			}
			if (!is_array($data)) {
				self::AdminSettings($player);
				return;
			}

			self::setData(self::BAN_ENABLE, (bool) ($data[1] ?? false));
			self::setData(self::KICK_ENABLE, (bool) ($data[2] ?? false));
			self::setData(self::PERMISSION_BYPASS_ENABLE, (bool) ($data[3] ?? false));
			self::setData(self::ALERTS_ENABLE, (bool) ($data[4] ?? false));
			self::setData(self::DETECTION_ENABLE, (bool) ($data[5] ?? false));
			self::setData(self::NETWORK_LIMIT_ENABLE, (bool) ($data[6] ?? false));
			if ((bool) ($data[6] ?? false) === true && isset($data[7])) {
				self::setData(self::NETWORK_LIMIT, (int) $data[7]);
			}
			ZuriAC::getInstance()->loadChecks();
			self::AdminSettings($player, true);
		});

		$form->setTitle("Admin Settings");
		$form->addLabel(($updated ? "Updated Successfully!" : "Choose what do you want to change.."));
		$form->addToggle("Ban Mode", self::boolData(self::BAN_ENABLE));
		$form->addToggle("Kick Mode", self::boolData(self::KICK_ENABLE));
		$form->addToggle("Bypass Permission", self::boolData(self::PERMISSION_BYPASS_ENABLE));
		$form->addToggle("Admin Alerts", self::boolData(self::ALERTS_ENABLE));
		$form->addToggle("PreVL Detections", self::boolData(self::DETECTION_ENABLE));
		$form->addToggle("Network IP Limit", self::boolData(self::NETWORK_LIMIT_ENABLE));
		if (self::boolData(self::NETWORK_LIMIT_ENABLE)) {
			$form->addSlider("Player IP Limit", 1, 100, -1, self::intData(self::NETWORK_LIMIT, 3));
		}
		$player->sendForm($form);
	}

	public static function AdvanceTools(Player $player, bool $updated = false) : void {
		$availableLocales = Lang::getAvailableLocales();
		$activeLocaleIndex = array_search(Lang::getActiveLocale(), $availableLocales, true);
		if (!is_int($activeLocaleIndex)) {
			$activeLocaleIndex = 0;
		}

		$form = new CustomForm(function(Player $player, $data) use ($availableLocales) {
			if ($data === null) {
				self::MainUI($player);
				return;
			}

			$localeIndex = (int) ($data["locale"] ?? 0);
			$selectedLocale = $availableLocales[$localeIndex] ?? Lang::getActiveLocale();
			Lang::setLocale($selectedLocale, true);

			PlayerAPI::getAPIPlayer($player)->setDebug((bool) ($data["debug"] ?? false));
			self::setData(self::PROXY_ENABLE, (bool) ($data["proxy"] ?? false));
			self::setData(self::DISCORD_ENABLE, (bool) ($data["discord"] ?? false));
			self::AdvanceTools($player, true);
		});

		$form->setTitle(Lang::get(LangKeys::UI_ADVANCE_TOOLS_TITLE));
		$form->addLabel(
			$updated
				? Lang::get(LangKeys::UI_ADVANCE_TOOLS_UPDATED_LANGUAGE, ["locale" => Lang::getActiveLocale()])
				: Lang::get(LangKeys::UI_ADVANCE_TOOLS_CHOOSE)
		);
		$form->addDropdown(Lang::get(LangKeys::UI_ADVANCE_TOOLS_LANGUAGE_LABEL), $availableLocales, $activeLocaleIndex, "locale");
		$form->addToggle("Debug Mode", PlayerAPI::getAPIPlayer($player)->isDebug(), "debug");
		$form->addToggle("ProxyUDP (Beta)", self::boolData(self::PROXY_ENABLE), "proxy");
		$form->addToggle("Discord Webhook Alerts", self::boolData(self::DISCORD_ENABLE), "discord");
		$player->sendForm($form);
	}

	public static function ToggleModules(Player $player, bool $toggled = false) : void {
		$form = new CustomForm(function(Player $player, mixed $data) {
			if ($data === null) {
				self::ManageModules($player);
				return;
			}
			if (!is_array($data)) {
				self::ToggleModules($player);
				return;
			}

			// Just to filter the non-bool variable in the data.
			$status = [];
			foreach ($data as $toggle) {
				if (is_bool($toggle)) {
					$status[] = $toggle;
				}
			}

			$allChecks = API::getAllChecks(false);
			foreach ($status as $index => $toggle) {
				$module = $allChecks[$index] ?? null;
				if (!$module instanceof Check) {
					continue;
				}
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
				if ($check->getName() === "NetworkLimit") {
					continue;
				}
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

			switch($data) {
				case 0:
					self::ChangePreVL($player, $check);
					break;
				case 1:
					self::TogglePunishment($player, $check);
					break;
				case 2:
					self::ChangeMaxVL($player, $check);
					break;
			}
		});

		$form->setTitle($check->getName() . " Information");
		$form->setContent(TextFormat::RESET . "Name: " . TextFormat::YELLOW . $check->getName() . "\n" . TextFormat::RESET . "Sub Types: " . TextFormat::YELLOW . $check->getAllSubTypes() . "\n" . TextFormat::RESET . "Status: " . ($check->enable() ? TextFormat::GREEN . "Enabled" : TextFormat::RED . "Disabled") . "\n" . TextFormat::RESET . "Ban: " . ($check->getPunishment() === "ban" ? TextFormat::GREEN . "Yes" : TextFormat::RED . "No") . "\n" . TextFormat::RESET . "Kick: " . ($check->getPunishment() === "kick" ? TextFormat::GREEN . "Yes" : TextFormat::RED . "No") . "\n" . TextFormat::RESET . "Captcha: " . ($check->getPunishment() === "captcha" ? TextFormat::GREEN . "Yes" : TextFormat::RED . "No") . "\n" . TextFormat::RESET . "Flag: " . ($check->getPunishment() === "flag" ? TextFormat::GREEN . "Yes" : TextFormat::RED . "No") . "\n" . TextFormat::RESET . "Max Violation: " . TextFormat::YELLOW . (self::getData(self::CHECK . "." . strtolower($check->getName()) . ".maxvl") === 0 ? "Instant Punishment" : self::getData(self::CHECK . "." . strtolower($check->getName()) . ".maxvl"))); // bullshit this is so long..
		$form->addButton("Change PreVL");
		$form->addButton("Toggle Punishment");
		if (self::intData(self::CHECK . "." . strtolower($check->getName()) . ".maxvl") !== 0) {
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
		$form->addSlider("MaxVL", 0, 100, -1, self::intData(self::CHECK . "." . strtolower($check->getName()) . ".maxvl"));
		$player->sendForm($form);
	}

	public static function ChangePreVL(Player $player, Check $check, bool $saved = false) : void {
		$form = new CustomForm(function(Player $player, mixed $data) use ($check) {
			if ($data === null) {
				self::ModuleInformation($player, $check);
				return;
			}
			if (!is_array($data)) {
				self::ChangePreVL($player, $check);
				return;
			}

			unset($data[0]);

			foreach ($data as $subType => $amount) {
				if (!is_string($subType) || !is_numeric($amount)) {
					continue;
				}
				self::setData(self::CHECK . "." . strtolower($check->getName()) . ".pre-vl." . $subType, (int) $amount);
			}

			self::ChangePreVL($player, $check, true);
		});

		$form->setTitle($check->getName() . " PreVL");
		$form->addLabel(($saved ? TextFormat::GREEN . "Modified successfully!" : "Modify the slider do you want to set.."));
		foreach (self::arrayData(self::CHECK . "." . strtolower($check->getName()) . ".pre-vl") as $subType => $amount) {
			if (!is_string($subType)) {
				continue;
			}
			$form->addSlider($check->getName() . " (" . strtoupper($subType) . ")", 0, 100, -1, is_numeric($amount) ? (int) $amount : 0, $subType);
		}
		$player->sendForm($form);
	}

	public static function TogglePunishment(Player $player, Check $check, bool $saved = false) : void {
		$form = new SimpleForm(function(Player $player, $data) use ($check) {
			if ($data === null) {
				self::ModuleInformation($player, $check);
				return;
			}

			switch($data) {
				case 0:
					self::setData(self::CHECK . "." . strtolower($check->getName()) . ".punishment", strtoupper("kick"));
					break;
				case 1:
					self::setData(self::CHECK . "." . strtolower($check->getName()) . ".punishment", strtoupper("ban"));
					break;
				case 2:
					self::setData(self::CHECK . "." . strtolower($check->getName()) . ".punishment", strtoupper("flag"));
					break;
			}
			self::TogglePunishment($player, $check, true);
		});

		$form->setTitle($check->getName() . " Punishment");
		$form->setContent(($saved ? TextFormat::GREEN . "Toggled successfully!" : "Choose what do you want to toggle.."));
		$form->addButton("Kick Mode\n" . (strtolower(self::stringData(self::CHECK . "." . strtolower($check->getName()) . ".punishment")) === "kick" ? "Enabled" : "Disabled"));
		$form->addButton("Ban Mode\n" . (strtolower(self::stringData(self::CHECK . "." . strtolower($check->getName()) . ".punishment")) === "ban" ? "Enabled" : "Disabled"));
		$form->addButton("Flag Mode\n" . (strtolower(self::stringData(self::CHECK . "." . strtolower($check->getName()) . ".punishment")) === "flag" ? "Enabled" : "Disabled"));
		$player->sendForm($form);
	}
}