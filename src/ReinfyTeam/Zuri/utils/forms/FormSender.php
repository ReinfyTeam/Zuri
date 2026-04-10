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

use czechpmdevs\libpmform\response\FormResponse;
use czechpmdevs\libpmform\type\CustomForm;
use czechpmdevs\libpmform\type\SimpleForm;
use pocketmine\player\Player;
use pocketmine\utils\NotCloneable;
use pocketmine\utils\NotSerializable;
use ReinfyTeam\Zuri\API;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\config\ConfigManager;
use ReinfyTeam\Zuri\lang\Lang;
use ReinfyTeam\Zuri\lang\LangKeys;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\ZuriAC;
use function array_key_exists;
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

	/**
	 * If libpmform preserves the leading label row as null, we need to skip it.
	 * If it is omitted, keep the data as-is.
	 *
	 * @param array<int,mixed> $data
	 */
	private static function leadingLabelOffset(array $data) : int {
		return array_key_exists(0, $data) && $data[0] === null ? 1 : 0;
	}

	public static function MainUI(Player $player) : void {
		$form = new SimpleForm(Lang::get(LangKeys::UI_MAIN_TITLE), Lang::get(LangKeys::UI_MAIN_CHOOSE));
		$form->setCallback(function(Player $player, FormResponse $response) {
			$data = $response->getData();
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

		$form->addButton(Lang::get(LangKeys::UI_MAIN_MANAGE_MODULES));
		$form->addButton(Lang::get(LangKeys::UI_MAIN_CAPTCHA_SETTINGS));
		$form->addButton(Lang::get(LangKeys::UI_MAIN_ADMIN_SETTINGS));
		$form->addButton(Lang::get(LangKeys::UI_MAIN_ADVANCE_TOOLS));
		$player->sendForm($form);
	}

	public static function ManageModules(Player $player, bool $reloaded = false) : void {
		$form = new SimpleForm(
			Lang::get(LangKeys::UI_MANAGE_MODULES_TITLE),
			$reloaded ? Lang::get(LangKeys::UI_MANAGE_MODULES_RELOADED) : Lang::get(LangKeys::UI_MANAGE_MODULES_CHOOSE)
		);
		$form->setCallback(function(Player $player, FormResponse $response) {
			$data = $response->getData();
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

		$form->addButton(Lang::get(LangKeys::UI_MANAGE_MODULES_ENABLE_DISABLE));
		$form->addButton(Lang::get(LangKeys::UI_MANAGE_MODULES_MODULE_INFORMATION));
		$form->addButton(Lang::get(LangKeys::UI_MANAGE_MODULES_RELOAD_ALL));
		$player->sendForm($form);
	}

	public static function CaptchaSettings(Player $player, bool $updated = false) : void {
		$form = new CustomForm(Lang::get(LangKeys::UI_CAPTCHA_TITLE));
		$form->setCallback(function(Player $player, FormResponse $response) {
			$data = $response->getData();
			if ($data === null) {
				self::MainUI($player);
				return;
			}
			if (!is_array($data)) {
				self::CaptchaSettings($player);
				return;
			}

			$offset = self::leadingLabelOffset($data);
			self::setData(self::CAPTCHA_ENABLE, (bool) ($data[0 + $offset] ?? false));

			if ((bool) ($data[0 + $offset] ?? false)) {
				self::setData(self::CAPTCHA_CODE_LENGTH, (int) ($data[1 + $offset] ?? 6));

				if (!self::getData(self::CAPTCHA_RANDOMIZE)) {
					self::setData(self::CAPTCHA_TIP, (bool) ($data[2 + $offset] ?? false));
					self::setData(self::CAPTCHA_MESSAGE, (bool) ($data[3 + $offset] ?? false));
					self::setData(self::CAPTCHA_TITLE, (bool) ($data[4 + $offset] ?? false));
					self::setData(self::CAPTCHA_RANDOMIZE, (bool) ($data[5 + $offset] ?? false));
				} else {
					self::setData(self::CAPTCHA_RANDOMIZE, (bool) ($data[2 + $offset] ?? false));
				}
			}

			self::CaptchaSettings($player, true);
		});

		$form->addLabel(($updated ? Lang::get(LangKeys::UI_CAPTCHA_UPDATED) : Lang::get(LangKeys::UI_CAPTCHA_CHOOSE)));
		$form->addToggle(Lang::get(LangKeys::UI_CAPTCHA_ENABLE), self::boolData(self::CAPTCHA_ENABLE));
		if (self::boolData(self::CAPTCHA_ENABLE)) {
			$form->addInput(Lang::get(LangKeys::UI_CAPTCHA_LENGTH), (string) self::intData(self::CAPTCHA_CODE_LENGTH, 6));
			if (!self::boolData(self::CAPTCHA_RANDOMIZE)) {
				$form->addToggle(Lang::get(LangKeys::UI_CAPTCHA_SEND_TIP), self::boolData(self::CAPTCHA_TIP));
				$form->addToggle(Lang::get(LangKeys::UI_CAPTCHA_SEND_MESSAGE), self::boolData(self::CAPTCHA_MESSAGE));
				$form->addToggle(Lang::get(LangKeys::UI_CAPTCHA_SEND_TITLE), self::boolData(self::CAPTCHA_TITLE));
			}
			if (self::boolData(self::CAPTCHA_RANDOMIZE)) {
				$form->addLabel(Lang::get(LangKeys::UI_CAPTCHA_RANDOMIZE_WARNING));
			}
			$form->addToggle(Lang::get(LangKeys::UI_CAPTCHA_RANDOMIZE), self::boolData(self::CAPTCHA_RANDOMIZE));
		}
		$player->sendForm($form);
	}

	public static function AdminSettings(Player $player, bool $updated = false) : void {
		$form = new CustomForm(Lang::get(LangKeys::UI_ADMIN_TITLE));
		$form->setCallback(function(Player $player, FormResponse $response) {
			$data = $response->getData();
			if ($data === null) {
				self::MainUI($player);
				return;
			}
			if (!is_array($data)) {
				self::AdminSettings($player);
				return;
			}

			$offset = self::leadingLabelOffset($data);
			self::setData(self::BAN_ENABLE, (bool) ($data[0 + $offset] ?? false));
			self::setData(self::KICK_ENABLE, (bool) ($data[1 + $offset] ?? false));
			self::setData(self::PERMISSION_BYPASS_ENABLE, (bool) ($data[2 + $offset] ?? false));
			self::setData(self::ALERTS_ENABLE, (bool) ($data[3 + $offset] ?? false));
			self::setData(self::DETECTION_ENABLE, (bool) ($data[4 + $offset] ?? false));
			self::setData(self::NETWORK_LIMIT_ENABLE, (bool) ($data[5 + $offset] ?? false));
			if ((bool) ($data[5 + $offset] ?? false) === true && isset($data[6 + $offset])) {
				self::setData(self::NETWORK_LIMIT, (int) $data[6 + $offset]);
			}
			ZuriAC::getInstance()->loadChecks();
			self::AdminSettings($player, true);
		});

		$form->addLabel(($updated ? Lang::get(LangKeys::UI_ADMIN_UPDATED) : Lang::get(LangKeys::UI_ADMIN_CHOOSE)));
		$form->addToggle(Lang::get(LangKeys::UI_ADMIN_BAN_MODE), self::boolData(self::BAN_ENABLE));
		$form->addToggle(Lang::get(LangKeys::UI_ADMIN_KICK_MODE), self::boolData(self::KICK_ENABLE));
		$form->addToggle(Lang::get(LangKeys::UI_ADMIN_BYPASS_PERMISSION), self::boolData(self::PERMISSION_BYPASS_ENABLE));
		$form->addToggle(Lang::get(LangKeys::UI_ADMIN_ALERTS), self::boolData(self::ALERTS_ENABLE));
		$form->addToggle(Lang::get(LangKeys::UI_ADMIN_PREVL_DETECTIONS), self::boolData(self::DETECTION_ENABLE));
		$form->addToggle(Lang::get(LangKeys::UI_ADMIN_NETWORK_LIMIT_ENABLE), self::boolData(self::NETWORK_LIMIT_ENABLE));
		if (self::boolData(self::NETWORK_LIMIT_ENABLE)) {
			$form->addInput(Lang::get(LangKeys::UI_ADMIN_NETWORK_LIMIT), (string) self::intData(self::NETWORK_LIMIT, 3));
		}
		$player->sendForm($form);
	}

	public static function AdvanceTools(Player $player, bool $updated = false) : void {
		$availableLocales = Lang::getAvailableLocales();
		$activeLocaleIndex = array_search(Lang::getActiveLocale(), $availableLocales, true);
		if (!is_int($activeLocaleIndex)) {
			$activeLocaleIndex = 0;
		}

		$form = new CustomForm(Lang::get(LangKeys::UI_ADVANCE_TOOLS_TITLE));
		$form->setCallback(function(Player $player, FormResponse $response) use ($availableLocales) {
			$data = $response->getData();
			if ($data === null) {
				self::MainUI($player);
				return;
			}
			if (!is_array($data)) {
				self::AdvanceTools($player);
				return;
			}

			$offset = self::leadingLabelOffset($data);
			$localeIndex = (int) ($data[0 + $offset] ?? 0);
			$selectedLocale = $availableLocales[$localeIndex] ?? Lang::getActiveLocale();
			Lang::setLocale($selectedLocale, true);

			PlayerAPI::getAPIPlayer($player)->setDebug((bool) ($data[1 + $offset] ?? false));
			self::setData(self::PROXY_ENABLE, (bool) ($data[2 + $offset] ?? false));
			self::setData(self::DISCORD_ENABLE, (bool) ($data[3 + $offset] ?? false));
			self::AdvanceTools($player, true);
		});

		$form->addLabel(
			$updated
				? Lang::get(LangKeys::UI_ADVANCE_TOOLS_UPDATED_LANGUAGE, ["locale" => Lang::getActiveLocale()])
				: Lang::get(LangKeys::UI_ADVANCE_TOOLS_CHOOSE)
		);
		$form->addDropdown(Lang::get(LangKeys::UI_ADVANCE_TOOLS_LANGUAGE_LABEL), $availableLocales, $activeLocaleIndex);
		$form->addToggle(Lang::get(LangKeys::UI_ADVANCE_TOOLS_DEBUG_MODE), PlayerAPI::getAPIPlayer($player)->isDebug());
		$form->addToggle(Lang::get(LangKeys::UI_ADVANCE_TOOLS_PROXY_UDP), self::boolData(self::PROXY_ENABLE));
		$form->addToggle(Lang::get(LangKeys::UI_ADVANCE_TOOLS_DISCORD_ALERTS), self::boolData(self::DISCORD_ENABLE));
		$player->sendForm($form);
	}

	public static function ToggleModules(Player $player, bool $toggled = false) : void {
		$form = new CustomForm(Lang::get(LangKeys::UI_TOGGLE_MODULES_TITLE));
		$form->setCallback(function(Player $player, FormResponse $response) {
			$data = $response->getData();
			if ($data === null) {
				self::ManageModules($player);
				return;
			}
			if (!is_array($data)) {
				self::ToggleModules($player);
				return;
			}

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

		$form->addLabel(($toggled ? Lang::get(LangKeys::UI_TOGGLE_MODULES_TOGGLED) : Lang::get(LangKeys::UI_TOGGLE_MODULES_CHOOSE)));

		foreach (API::getAllChecks(false) as $check) {
			$form->addToggle($check->getName() . " (" . $check->getSubType() . ")", $check->enable());
		}

		$player->sendForm($form);
	}

	public static function PickAModule(Player $player) : void {
		/** @var list<string> $modules */
		$modules = [];
		$form = new SimpleForm(Lang::get(LangKeys::UI_PICK_MODULE_TITLE), Lang::get(LangKeys::UI_PICK_MODULE_CHOOSE));
		$form->setCallback(function(Player $player, FormResponse $response) use (&$modules) {
			$data = $response->getData();
			if ($data === null) {
				self::ManageModules($player);
				return;
			}

			$index = is_int($data) ? $data : -1;
			$name = $modules[$index] ?? null;
			if ($name === null) {
				self::PickAModule($player);
				return;
			}

			foreach (ZuriAC::Checks() as $check) {
				if ($check->getName() === $name) {
					self::ModuleInformation($player, $check);
					return;
				}
			}
		});

		foreach (ZuriAC::Checks() as $check) {
			if (!isset($modules[$check->getName()])) {
				$modules[] = $check->getName();
				if ($check->getName() === "NetworkLimit") {
					continue;
				}
				$form->addButton(
					$check->getName()
					. "\n"
					. $check->getAllSubTypes()
					. "\n"
					. Lang::get(LangKeys::UI_PICK_MODULE_VIEW_INFO)
				);
			}
		}

		$player->sendForm($form);
	}

	public static function ModuleInformation(Player $player, Check $check) : void {
		$checkPunishment = strtolower($check->getPunishment());
		$statusValue = $check->enable() ? Lang::get(LangKeys::UI_COMMON_ENABLED) : Lang::get(LangKeys::UI_COMMON_DISABLED);
		$banValue = $checkPunishment === "ban" ? Lang::get(LangKeys::UI_COMMON_YES) : Lang::get(LangKeys::UI_COMMON_NO);
		$kickValue = $checkPunishment === "kick" ? Lang::get(LangKeys::UI_COMMON_YES) : Lang::get(LangKeys::UI_COMMON_NO);
		$captchaValue = $checkPunishment === "captcha" ? Lang::get(LangKeys::UI_COMMON_YES) : Lang::get(LangKeys::UI_COMMON_NO);
		$flagValue = $checkPunishment === "flag" ? Lang::get(LangKeys::UI_COMMON_YES) : Lang::get(LangKeys::UI_COMMON_NO);
		$maxVlCurrent = self::intData(self::CHECK . "." . strtolower($check->getName()) . ".maxvl");
		$maxVlValue = $maxVlCurrent === 0 ? Lang::get(LangKeys::UI_MODULE_INFO_INSTANT_PUNISHMENT) : (string) $maxVlCurrent;
		$form = new SimpleForm(
			Lang::get(LangKeys::UI_MODULE_INFO_TITLE, ["module" => $check->getName()]),
			Lang::get(LangKeys::UI_MODULE_INFO_BODY, [
				"name" => $check->getName(),
				"subtypes" => $check->getAllSubTypes(),
				"status" => $statusValue,
				"ban" => $banValue,
				"kick" => $kickValue,
				"captcha" => $captchaValue,
				"flag" => $flagValue,
				"maxvl" => $maxVlValue,
			])
		);
		$form->setCallback(function(Player $player, FormResponse $response) use ($check) {
			$data = $response->getData();
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

		$form->addButton(Lang::get(LangKeys::UI_MODULE_INFO_BUTTON_CHANGE_PREVL) . "\n" . $check->getAllSubTypes());
		$form->addButton(Lang::get(LangKeys::UI_MODULE_INFO_BUTTON_TOGGLE_PUNISHMENT) . "\n" . $check->getAllSubTypes());
		if (self::intData(self::CHECK . "." . strtolower($check->getName()) . ".maxvl") !== 0) {
			$form->addButton(Lang::get(LangKeys::UI_MODULE_INFO_BUTTON_CHANGE_MAXVL) . "\n" . $check->getAllSubTypes());
		}
		$player->sendForm($form);
	}

	public static function ChangeMaxVL(Player $player, Check $check, bool $saved = false) : void {
		$form = new CustomForm(Lang::get(LangKeys::UI_CHANGE_MAXVL_TITLE, ["module" => $check->getName()]));
		$form->setCallback(function(Player $player, FormResponse $response) use ($check) {
			$data = $response->getData();
			if ($data === null) {
				self::ModuleInformation($player, $check);
				return;
			}
			if (!is_array($data)) {
				self::ChangeMaxVL($player, $check);
				return;
			}

			$offset = self::leadingLabelOffset($data);
			if (($data[0 + $offset] ?? null) !== null) {
				self::setData(self::CHECK . "." . strtolower($check->getName()) . ".maxvl", intval($data[0 + $offset]));
				self::ChangeMaxVL($player, $check, true);
			}
		});

		$form->addLabel(($saved ? Lang::get(LangKeys::UI_CHANGE_MAXVL_UPDATED) : Lang::get(LangKeys::UI_CHANGE_MAXVL_CHOOSE)));
		$form->addInput(Lang::get(LangKeys::UI_CHANGE_MAXVL_INPUT), (string) self::intData(self::CHECK . "." . strtolower($check->getName()) . ".maxvl"));
		$player->sendForm($form);
	}

	public static function ChangePreVL(Player $player, Check $check, bool $saved = false) : void {
		/** @var list<string> $subTypes */
		$subTypes = [];
		$form = new CustomForm(Lang::get(LangKeys::UI_CHANGE_PREVL_TITLE, ["module" => $check->getName()]));
		$form->setCallback(function(Player $player, FormResponse $response) use ($check, &$subTypes) {
			$data = $response->getData();
			if ($data === null) {
				self::ModuleInformation($player, $check);
				return;
			}
			if (!is_array($data)) {
				self::ChangePreVL($player, $check);
				return;
			}

			$offset = self::leadingLabelOffset($data);
			foreach ($subTypes as $i => $subType) {
				$value = $data[$i + $offset] ?? null;
				if (!is_string($subType) || !is_numeric($value)) {
					continue;
				}
				self::setData(self::CHECK . "." . strtolower($check->getName()) . ".pre-vl." . $subType, (int) $value);
			}

			self::ChangePreVL($player, $check, true);
		});

		$form->addLabel(($saved ? Lang::get(LangKeys::UI_CHANGE_PREVL_UPDATED) : Lang::get(LangKeys::UI_CHANGE_PREVL_CHOOSE)));
		foreach (self::arrayData(self::CHECK . "." . strtolower($check->getName()) . ".pre-vl") as $subType => $amount) {
			if (!is_string($subType)) {
				continue;
			}
			$subTypes[] = $subType;
			$form->addInput(
				Lang::get(LangKeys::UI_CHANGE_PREVL_INPUT, ["module" => $check->getName(), "subtype" => strtoupper($subType)]),
				(string) (is_numeric($amount) ? (int) $amount : 0)
			);
		}
		$player->sendForm($form);
	}

	public static function TogglePunishment(Player $player, Check $check, bool $saved = false) : void {
		$form = new SimpleForm(
			Lang::get(LangKeys::UI_TOGGLE_PUNISHMENT_TITLE, ["module" => $check->getName()]),
			($saved ? Lang::get(LangKeys::UI_TOGGLE_PUNISHMENT_TOGGLED) : Lang::get(LangKeys::UI_TOGGLE_PUNISHMENT_CHOOSE))
		);
		$form->setCallback(function(Player $player, FormResponse $response) use ($check) {
			$data = $response->getData();
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

		$form->addButton(Lang::get(LangKeys::UI_TOGGLE_PUNISHMENT_BUTTON, [
			"mode" => Lang::get(LangKeys::UI_TOGGLE_PUNISHMENT_KICK),
			"status" => (strtolower(self::stringData(self::CHECK . "." . strtolower($check->getName()) . ".punishment")) === "kick" ? Lang::get(LangKeys::UI_COMMON_ENABLED) : Lang::get(LangKeys::UI_COMMON_DISABLED)),
		]));
		$form->addButton(Lang::get(LangKeys::UI_TOGGLE_PUNISHMENT_BUTTON, [
			"mode" => Lang::get(LangKeys::UI_TOGGLE_PUNISHMENT_BAN),
			"status" => (strtolower(self::stringData(self::CHECK . "." . strtolower($check->getName()) . ".punishment")) === "ban" ? Lang::get(LangKeys::UI_COMMON_ENABLED) : Lang::get(LangKeys::UI_COMMON_DISABLED)),
		]));
		$form->addButton(Lang::get(LangKeys::UI_TOGGLE_PUNISHMENT_BUTTON, [
			"mode" => Lang::get(LangKeys::UI_TOGGLE_PUNISHMENT_FLAG),
			"status" => (strtolower(self::stringData(self::CHECK . "." . strtolower($check->getName()) . ".punishment")) === "flag" ? Lang::get(LangKeys::UI_COMMON_ENABLED) : Lang::get(LangKeys::UI_COMMON_DISABLED)),
		]));
		$player->sendForm($form);
	}
}
