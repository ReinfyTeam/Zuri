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
use ReinfyTeam\Zuri\utils\AuditLogger;
use ReinfyTeam\Zuri\ZuriAC;
use function array_key_exists;
use function array_keys;
use function array_pop;
use function array_search;
use function count;
use function explode;
use function implode;
use function intval;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_numeric;
use function is_string;
use function json_decode;
use function json_encode;
use function json_last_error;
use function json_last_error_msg;
use function max;
use function min;
use function str_contains;
use function str_starts_with;
use function strlen;
use function strtolower;
use function strtoupper;
use function substr;
use function trim;

/**
 * Builds and dispatches administrative in-game forms for Zuri configuration.
 */
final class FormSender extends ConfigManager {
	use NotCloneable;
	use NotSerializable;

	/** @var array<string,string> */
	private static array $lastConfigEditorPath = [];
	/** @var array<string,int> */
	private static array $lastConfigEditorType = [];

	/**
	 * Reads a config node as boolean with permissive conversions.
	 *
	 * @param string $path Config path.
	 * @param bool $default Fallback value.
	 */
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

	/**
	 * Reads a config node as integer with numeric coercion.
	 *
	 * @param string $path Config path.
	 * @param int $default Fallback value.
	 */
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

	/**
	 * Reads a config node as string.
	 *
	 * @param string $path Config path.
	 * @param string $default Fallback value.
	 */
	private static function stringData(string $path, string $default = "") : string {
		$value = self::getData($path, $default);
		return is_string($value) ? $value : $default;
	}

	/** @return array<string,mixed> */
	/**
	 * Reads a config node as array.
	 *
	 * @param string $path Config path.
	 * @return array<string,mixed>
	 */
	private static function arrayData(string $path) : array {
		$value = self::getData($path, []);
		return is_array($value) ? $value : [];
	}

	/**
	 * If libpmform preserves the leading label row as null, we need to skip it.
	 * If it is omitted, keep the data as-is.
	 *
	 * @param array<int,mixed> $data Response payload values.
	 * @return int Offset for value indices.
	 */
	private static function leadingLabelOffset(array $data) : int {
		return array_key_exists(0, $data) && $data[0] === null ? 1 : 0;
	}

	/**
	 * Persists a config value and reports save failures to player and logs.
	 *
	 * @param Player $player Player receiving feedback.
	 * @param string $path Config path.
	 * @param mixed $value Value to save.
	 */
	private static function safeSetData(Player $player, string $path, mixed $value) : bool {
		try {
			self::setData($path, $value);
			return true;
		} catch (\Throwable $throwable) {
			$message = Lang::get(LangKeys::DEBUG_UI_CONFIG_SAVE_FAILED, [
				"path" => $path,
				"error" => $throwable->getMessage(),
			]);
			ZuriAC::getInstance()->getLogger()->warning($message);
			AuditLogger::anticheat($message);
			$player->sendMessage(Lang::get(LangKeys::UI_COMMON_ERROR, ["reason" => $throwable->getMessage()]));
			return false;
		}
	}

	/**
	 * Reloads check registry while preserving UX-safe error handling.
	 *
	 * @param Player $player Player receiving feedback.
	 */
	private static function safeReloadChecks(Player $player) : bool {
		try {
			ZuriAC::getInstance()->loadChecks();
			return true;
		} catch (\Throwable $throwable) {
			$message = Lang::get(LangKeys::DEBUG_UI_RELOAD_FAILED, ["error" => $throwable->getMessage()]);
			ZuriAC::getInstance()->getLogger()->warning($message);
			AuditLogger::anticheat($message);
			$player->sendMessage(Lang::get(LangKeys::UI_COMMON_ERROR, ["reason" => $throwable->getMessage()]));
			return false;
		}
	}

	/**
	 * Builds a stable per-player key for editor state caches.
	 *
	 * @param Player $player Player context.
	 */
	private static function configEditorPlayerKey(Player $player) : string {
		return strtolower($player->getName());
	}

	/**
	 * Converts arbitrary config values into editable text.
	 *
	 * @param mixed $value Value to stringify.
	 */
	private static function stringifyConfigEditorValue(mixed $value) : string {
		if (is_bool($value)) {
			return $value ? "true" : "false";
		}
		if ($value === null) {
			return "null";
		}
		if (is_string($value) || is_numeric($value)) {
			return (string) $value;
		}
		$encoded = json_encode($value, JSON_UNESCAPED_UNICODE);
		return is_string($encoded) ? $encoded : "";
	}

	/**
	 * Parses typed config editor input into normalized values.
	 *
	 * @param int $typeIndex Selected type option index.
	 * @param string $raw Raw input text.
	 * @param string $error Populated parse error text.
	 */
	private static function parseConfigEditorValue(int $typeIndex, string $raw, string &$error) : mixed {
		$trimmed = trim($raw);
		$lower = strtolower($trimmed);
		$error = "";

		switch ($typeIndex) {
			case 0: // Auto
				if ($lower === "true") {
					return true;
				}
				if ($lower === "false") {
					return false;
				}
				if ($lower === "null") {
					return null;
				}
				if (is_numeric($trimmed)) {
					return str_contains($trimmed, ".") || str_contains($lower, "e") ? (float) $trimmed : (int) $trimmed;
				}
				if (str_starts_with($trimmed, "{") || str_starts_with($trimmed, "[")) {
					$decoded = json_decode($trimmed, true);
					if (json_last_error() !== JSON_ERROR_NONE) {
						$error = json_last_error_msg();
						return null;
					}
					return $decoded;
				}
				return $raw;
			case 1: // String
				return $raw;
			case 2: // Integer
				if (!is_numeric($trimmed)) {
					$error = Lang::get(LangKeys::UI_CONFIG_EDITOR_REASON_EXPECTED_INTEGER);
					return null;
				}
				return (int) $trimmed;
			case 3: // Float
				if (!is_numeric($trimmed)) {
					$error = Lang::get(LangKeys::UI_CONFIG_EDITOR_REASON_EXPECTED_FLOAT);
					return null;
				}
				return (float) $trimmed;
			case 4: // Boolean
				if ($lower === "true" || $lower === "1" || $lower === "yes" || $lower === "on") {
					return true;
				}
				if ($lower === "false" || $lower === "0" || $lower === "no" || $lower === "off") {
					return false;
				}
				$error = Lang::get(LangKeys::UI_CONFIG_EDITOR_REASON_EXPECTED_BOOLEAN);
				return null;
			case 5: // JSON
				$decoded = json_decode($trimmed, true);
				if (json_last_error() !== JSON_ERROR_NONE) {
					$error = json_last_error_msg();
					return null;
				}
				return $decoded;
			case 6: // Null
				return null;
			default:
				return $raw;
		}
	}

	/** @return list<string> */
	/**
	 * Returns localized type dropdown options for config editor.
	 *
	 * @return list<string>
	 */
	private static function configEditorTypeOptions() : array {
		return [
			Lang::get(LangKeys::UI_CONFIG_EDITOR_TYPE_AUTO),
			Lang::get(LangKeys::UI_CONFIG_EDITOR_TYPE_STRING),
			Lang::get(LangKeys::UI_CONFIG_EDITOR_TYPE_INTEGER),
			Lang::get(LangKeys::UI_CONFIG_EDITOR_TYPE_FLOAT),
			Lang::get(LangKeys::UI_CONFIG_EDITOR_TYPE_BOOLEAN),
			Lang::get(LangKeys::UI_CONFIG_EDITOR_TYPE_JSON),
			Lang::get(LangKeys::UI_CONFIG_EDITOR_TYPE_NULL),
		];
	}

	/**
	 * Chooses default editor type index from a runtime value.
	 *
	 * @param mixed $value Runtime value.
	 */
	private static function guessConfigEditorTypeIndex(mixed $value) : int {
		if ($value === null) {
			return 6;
		}
		if (is_bool($value)) {
			return 4;
		}
		if (is_int($value)) {
			return 2;
		}
		if (is_float($value)) {
			return 3;
		}
		if (is_array($value)) {
			return 5;
		}
		if (is_string($value)) {
			return 1;
		}
		return 0;
	}

	/**
	 * Resolves parent node path of a dot-notation config key.
	 *
	 * @param string $path Child config path.
	 */
	private static function parentConfigPath(string $path) : string {
		$parts = explode(".", $path);
		if (count($parts) <= 1) {
			return "zuri";
		}
		array_pop($parts);
		$parent = implode(".", $parts);
		return $parent !== "" ? $parent : "zuri";
	}

	/**
	 * Truncates long config previews for compact button display.
	 *
	 * @param string $value Input preview.
	 * @param int $max Max display length.
	 */
	private static function shortenValue(string $value, int $max = 48) : string {
		if (strlen($value) <= $max) {
			return $value;
		}
		return substr($value, 0, $max - 1) . "...";
	}

	/**
	 * Opens category browser for nested config groups and leaf keys.
	 *
	 * @param Player $player Player opening the editor.
	 * @param string $basePath Root config path to browse.
	 */
	public static function ConfigCategoryEditor(Player $player, string $basePath = "zuri") : void {
		$node = self::getData($basePath, null);
		if (!is_array($node)) {
			self::ConfigEditor($player, $basePath);
			return;
		}

		/** @var list<array{path:string,isGroup:bool}> $entries */
		$entries = [];
		$form = new SimpleForm(
			Lang::get(LangKeys::UI_CONFIG_EDITOR_CATEGORY_TITLE, ["path" => $basePath]),
			Lang::get(LangKeys::UI_CONFIG_EDITOR_CATEGORY_CHOOSE, ["path" => $basePath])
		);
		$form->setCallback(function(Player $player, FormResponse $response) use ($basePath, &$entries) : void {
			$data = $response->getData();
			if (!is_int($data)) {
				self::MainUI($player);
				return;
			}
			$entry = $entries[$data] ?? null;
			if (!is_array($entry)) {
				self::ConfigCategoryEditor($player, $basePath);
				return;
			}
			$path = $entry["path"];
			if ($path === "__back") {
				$parent = self::parentConfigPath($basePath);
				if ($basePath === "zuri") {
					self::MainUI($player);
					return;
				}
				self::ConfigCategoryEditor($player, $parent);
				return;
			}
			if ($entry["isGroup"]) {
				self::ConfigCategoryEditor($player, $path);
				return;
			}
			self::ConfigEditor($player, $path);
		});

		if ($basePath !== "zuri") {
			$form->addButton(Lang::get(LangKeys::UI_CONFIG_EDITOR_CATEGORY_BACK));
			$entries[] = ["path" => "__back", "isGroup" => true];
		}

		foreach (array_keys($node) as $key) {
			if (!is_string($key)) {
				continue;
			}
			$path = $basePath . "." . $key;
			$value = self::getData($path, null);
			if (is_array($value)) {
				$form->addButton(Lang::get(LangKeys::UI_CONFIG_EDITOR_CATEGORY_GROUP, ["name" => $key]));
				$entries[] = ["path" => $path, "isGroup" => true];
				continue;
			}
			$preview = self::shortenValue(self::stringifyConfigEditorValue($value));
			$form->addButton(Lang::get(LangKeys::UI_CONFIG_EDITOR_CATEGORY_VALUE, [
				"name" => $key,
				"value" => $preview,
			]));
			$entries[] = ["path" => $path, "isGroup" => false];
		}

		$player->sendForm($form);
	}

	/**
	 * Opens direct config value editor with typed parsing support.
	 *
	 * @param Player $player Player opening the editor.
	 * @param string|null $preferredPath Preferred config path.
	 * @param bool $saved Whether the previous update succeeded.
	 * @param string $statusMessage Optional status line override.
	 */
	public static function ConfigEditor(Player $player, ?string $preferredPath = null, bool $saved = false, string $statusMessage = "") : void {
		$playerKey = self::configEditorPlayerKey($player);
		$path = trim($preferredPath ?? (self::$lastConfigEditorPath[$playerKey] ?? "zuri.alerts.enable"));
		if ($path === "") {
			$path = "zuri.alerts.enable";
		}
		self::$lastConfigEditorPath[$playerKey] = $path;

		$currentValue = self::getData($path, "");
		$typeOptions = self::configEditorTypeOptions();
		$defaultTypeRaw = self::$lastConfigEditorType[$playerKey] ?? self::guessConfigEditorTypeIndex($currentValue);
		$defaultType = max(0, min(6, (int) $defaultTypeRaw));
		$defaultValue = self::stringifyConfigEditorValue($currentValue);

		$form = new CustomForm(Lang::get(LangKeys::UI_CONFIG_EDITOR_TITLE));
		$form->setCallback(function(Player $player, FormResponse $response) : void {
			$data = $response->getData();
			if ($data === null) {
				self::MainUI($player);
				return;
			}
			if (!is_array($data)) {
				self::ConfigEditor($player);
				return;
			}

			$offset = self::leadingLabelOffset($data);
			$path = trim((string) ($data[0 + $offset] ?? ""));
			$typeIndex = (int) ($data[1 + $offset] ?? 0);
			$rawValue = (string) ($data[2 + $offset] ?? "");
			$reload = (bool) ($data[3 + $offset] ?? false);

			if ($path === "") {
				self::ConfigEditor($player, null, false, Lang::get(LangKeys::UI_CONFIG_EDITOR_INVALID_PATH));
				return;
			}

			$error = "";
			$parsed = self::parseConfigEditorValue($typeIndex, $rawValue, $error);
			if ($error !== "") {
				self::ConfigEditor($player, $path, false, Lang::get(LangKeys::UI_CONFIG_EDITOR_INVALID_VALUE, ["reason" => $error]));
				return;
			}
			if (!self::safeSetData($player, $path, $parsed)) {
				return;
			}
			if ($reload && !self::safeReloadChecks($player)) {
				return;
			}

			$playerKey = self::configEditorPlayerKey($player);
			self::$lastConfigEditorPath[$playerKey] = $path;
			self::$lastConfigEditorType[$playerKey] = $typeIndex;
			self::ConfigEditor($player, $path, true, Lang::get(LangKeys::UI_CONFIG_EDITOR_UPDATED, [
				"path" => $path,
				"value" => self::stringifyConfigEditorValue($parsed),
			]));
		});

		$message = $statusMessage !== ""
			? $statusMessage
			: ($saved ? Lang::get(LangKeys::UI_CONFIG_EDITOR_UPDATED, ["path" => $path, "value" => $defaultValue]) : Lang::get(LangKeys::UI_CONFIG_EDITOR_CHOOSE));
		$form->addLabel($message);
		$form->addInput(Lang::get(LangKeys::UI_CONFIG_EDITOR_PATH), "zuri.alerts.enable", $path);
		$form->addDropdown(Lang::get(LangKeys::UI_CONFIG_EDITOR_TYPE), $typeOptions, $defaultType);
		$form->addInput(Lang::get(LangKeys::UI_CONFIG_EDITOR_VALUE), "", $defaultValue);
		$form->addToggle(Lang::get(LangKeys::UI_CONFIG_EDITOR_RELOAD), false);
		$player->sendForm($form);
	}

	/**
	 * Opens the root Zuri administration menu.
	 *
	 * @param Player $player Player opening the menu.
	 */
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
				case 4:
					self::ConfigCategoryEditor($player);
					break;
				case 5:
					self::ConfigEditor($player);
					break;
			}
		});

		$form->addButton(Lang::get(LangKeys::UI_MAIN_MANAGE_MODULES));
		$form->addButton(Lang::get(LangKeys::UI_MAIN_CAPTCHA_SETTINGS));
		$form->addButton(Lang::get(LangKeys::UI_MAIN_ADMIN_SETTINGS));
		$form->addButton(Lang::get(LangKeys::UI_MAIN_ADVANCE_TOOLS));
		$form->addButton(Lang::get(LangKeys::UI_MAIN_CONFIG_CATEGORIES));
		$form->addButton(Lang::get(LangKeys::UI_MAIN_CONFIG_EDITOR));
		$player->sendForm($form);
	}

	/**
	 * Opens module management entry menu.
	 *
	 * @param Player $player Player opening the menu.
	 * @param bool $reloaded Whether a reload action just occurred.
	 */
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
					self::safeReloadChecks($player);
					break;
			}
		});

		$form->addButton(Lang::get(LangKeys::UI_MANAGE_MODULES_ENABLE_DISABLE));
		$form->addButton(Lang::get(LangKeys::UI_MANAGE_MODULES_MODULE_INFORMATION));
		$form->addButton(Lang::get(LangKeys::UI_MANAGE_MODULES_RELOAD_ALL));
		$player->sendForm($form);
	}

	/**
	 * Opens captcha settings form and persists chosen options.
	 *
	 * @param Player $player Player opening the menu.
	 * @param bool $updated Whether settings were just applied.
	 */
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
			if (!self::safeSetData($player, self::CAPTCHA_ENABLE, (bool) ($data[0 + $offset] ?? false))) {
				return;
			}

			if ((bool) ($data[0 + $offset] ?? false)) {
				if (!self::safeSetData($player, self::CAPTCHA_CODE_LENGTH, (int) ($data[1 + $offset] ?? 6))) {
					return;
				}

				if (!self::getData(self::CAPTCHA_RANDOMIZE)) {
					if (!self::safeSetData($player, self::CAPTCHA_TIP, (bool) ($data[2 + $offset] ?? false))) {
						return;
					}
					if (!self::safeSetData($player, self::CAPTCHA_MESSAGE, (bool) ($data[3 + $offset] ?? false))) {
						return;
					}
					if (!self::safeSetData($player, self::CAPTCHA_TITLE, (bool) ($data[4 + $offset] ?? false))) {
						return;
					}
					if (!self::safeSetData($player, self::CAPTCHA_RANDOMIZE, (bool) ($data[5 + $offset] ?? false))) {
						return;
					}
				} else {
					if (!self::safeSetData($player, self::CAPTCHA_RANDOMIZE, (bool) ($data[2 + $offset] ?? false))) {
						return;
					}
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

	/**
	 * Opens admin behavior settings form.
	 *
	 * @param Player $player Player opening the menu.
	 * @param bool $updated Whether settings were just applied.
	 */
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
			if (!self::safeSetData($player, self::BAN_ENABLE, (bool) ($data[0 + $offset] ?? false))) {
				return;
			}
			if (!self::safeSetData($player, self::KICK_ENABLE, (bool) ($data[1 + $offset] ?? false))) {
				return;
			}
			if (!self::safeSetData($player, self::PERMISSION_BYPASS_ENABLE, (bool) ($data[2 + $offset] ?? false))) {
				return;
			}
			if (!self::safeSetData($player, self::ALERTS_ENABLE, (bool) ($data[3 + $offset] ?? false))) {
				return;
			}
			if (!self::safeSetData($player, self::DETECTION_ENABLE, (bool) ($data[4 + $offset] ?? false))) {
				return;
			}
			if (!self::safeSetData($player, self::NETWORK_LIMIT_ENABLE, (bool) ($data[5 + $offset] ?? false))) {
				return;
			}
			if ((bool) ($data[5 + $offset] ?? false) === true && isset($data[6 + $offset])) {
				if (!self::safeSetData($player, self::NETWORK_LIMIT, (int) $data[6 + $offset])) {
					return;
				}
			}
			if (!self::safeReloadChecks($player)) {
				return;
			}
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

	/**
	 * Opens advanced tools form for locale/debug/proxy/discord toggles.
	 *
	 * @param Player $player Player opening the menu.
	 * @param bool $updated Whether settings were just applied.
	 */
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
			if (!self::safeSetData($player, self::PROXY_ENABLE, (bool) ($data[2 + $offset] ?? false))) {
				return;
			}
			if (!self::safeSetData($player, self::DISCORD_ENABLE, (bool) ($data[3 + $offset] ?? false))) {
				return;
			}
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

	/**
	 * Opens bulk module toggle form.
	 *
	 * @param Player $player Player opening the menu.
	 * @param bool $toggled Whether toggles were just saved.
	 */
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
				self::safeSetData($player, self::CHECK . "." . strtolower($module->getName()) . ".enable", $toggle);
			}
			self::ToggleModules($player, true);
		});

		$form->addLabel(($toggled ? Lang::get(LangKeys::UI_TOGGLE_MODULES_TOGGLED) : Lang::get(LangKeys::UI_TOGGLE_MODULES_CHOOSE)));

		foreach (API::getAllChecks(false) as $check) {
			$form->addToggle($check->getName() . " (" . $check->getSubType() . ")", $check->enable());
		}

		$player->sendForm($form);
	}

	/**
	 * Opens module picker for detailed module actions.
	 *
	 * @param Player $player Player opening the menu.
	 */
	public static function PickAModule(Player $player) : void {
		/** @var list<string> $modules */
		$modules = [];
		/** @var array<string,true> $seen */
		$seen = [];
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
			$name = $check->getName();
			if (isset($seen[$name]) || $name === "NetworkLimit") {
				continue;
			}
			$seen[$name] = true;
			$modules[] = $name;
			$form->addButton(
				$name
				. "\n"
				. $check->getAllSubTypes()
				. "\n"
				. Lang::get(LangKeys::UI_PICK_MODULE_VIEW_INFO)
			);
		}

		$player->sendForm($form);
	}

	/**
	 * Shows details and actions for a selected check module.
	 *
	 * @param Player $player Player viewing module details.
	 * @param Check $check Selected module instance.
	 */
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
					self::ToggleModuleStatus($player, $check);
					break;
				case 1:
					self::ChangePreVL($player, $check);
					break;
				case 2:
					self::TogglePunishment($player, $check);
					break;
				case 3:
					self::ChangeMaxVL($player, $check);
					break;
			}
		});

		$form->addButton(
			Lang::get(LangKeys::UI_TOGGLE_PUNISHMENT_BUTTON, [
				"mode" => Lang::get(LangKeys::UI_MODULE_INFO_BUTTON_TOGGLE_STATUS),
				"status" => $check->enable() ? Lang::get(LangKeys::UI_COMMON_ENABLED) : Lang::get(LangKeys::UI_COMMON_DISABLED),
			])
		);
		$form->addButton(Lang::get(LangKeys::UI_MODULE_INFO_BUTTON_CHANGE_PREVL) . "\n" . $check->getAllSubTypes());
		$form->addButton(Lang::get(LangKeys::UI_MODULE_INFO_BUTTON_TOGGLE_PUNISHMENT) . "\n" . $check->getAllSubTypes());
		$form->addButton(Lang::get(LangKeys::UI_MODULE_INFO_BUTTON_CHANGE_MAXVL) . "\n" . $check->getAllSubTypes());
		$player->sendForm($form);
	}

	/**
	 * Toggles a module enabled state and reloads checks.
	 *
	 * @param Player $player Player performing action.
	 * @param Check $check Selected module instance.
	 */
	private static function ToggleModuleStatus(Player $player, Check $check) : void {
		$path = self::CHECK . "." . strtolower($check->getName()) . ".enable";
		$newState = !self::boolData($path, $check->enable());
		if (!self::safeSetData($player, $path, $newState)) {
			return;
		}
		if (!self::safeReloadChecks($player)) {
			return;
		}
		self::ModuleInformation($player, $check);
	}

	/**
	 * Opens max-violation editor for a module.
	 *
	 * @param Player $player Player performing action.
	 * @param Check $check Selected module instance.
	 * @param bool $saved Whether the value was just saved.
	 */
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
				if (!self::safeSetData($player, self::CHECK . "." . strtolower($check->getName()) . ".maxvl", intval($data[0 + $offset]))) {
					return;
				}
				self::ChangeMaxVL($player, $check, true);
			}
		});

		$form->addLabel(($saved ? Lang::get(LangKeys::UI_CHANGE_MAXVL_UPDATED) : Lang::get(LangKeys::UI_CHANGE_MAXVL_CHOOSE)));
		$form->addInput(Lang::get(LangKeys::UI_CHANGE_MAXVL_INPUT), (string) self::intData(self::CHECK . "." . strtolower($check->getName()) . ".maxvl"));
		$player->sendForm($form);
	}

	/**
	 * Opens pre-violation thresholds editor for module subtypes.
	 *
	 * @param Player $player Player performing action.
	 * @param Check $check Selected module instance.
	 * @param bool $saved Whether values were just saved.
	 */
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
				self::safeSetData($player, self::CHECK . "." . strtolower($check->getName()) . ".pre-vl." . $subType, (int) $value);
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

	/**
	 * Opens punishment-mode toggle menu for a module.
	 *
	 * @param Player $player Player performing action.
	 * @param Check $check Selected module instance.
	 * @param bool $saved Whether a mode was just saved.
	 */
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
					if (!self::safeSetData($player, self::CHECK . "." . strtolower($check->getName()) . ".punishment", strtoupper("kick"))) {
						return;
					}
					break;
				case 1:
					if (!self::safeSetData($player, self::CHECK . "." . strtolower($check->getName()) . ".punishment", strtoupper("ban"))) {
						return;
					}
					break;
				case 2:
					if (!self::safeSetData($player, self::CHECK . "." . strtolower($check->getName()) . ".punishment", strtoupper("captcha"))) {
						return;
					}
					break;
				case 3:
					if (!self::safeSetData($player, self::CHECK . "." . strtolower($check->getName()) . ".punishment", strtoupper("flag"))) {
						return;
					}
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
			"mode" => Lang::get(LangKeys::UI_TOGGLE_PUNISHMENT_CAPTCHA),
			"status" => (strtolower(self::stringData(self::CHECK . "." . strtolower($check->getName()) . ".punishment")) === "captcha" ? Lang::get(LangKeys::UI_COMMON_ENABLED) : Lang::get(LangKeys::UI_COMMON_DISABLED)),
		]));
		$form->addButton(Lang::get(LangKeys::UI_TOGGLE_PUNISHMENT_BUTTON, [
			"mode" => Lang::get(LangKeys::UI_TOGGLE_PUNISHMENT_FLAG),
			"status" => (strtolower(self::stringData(self::CHECK . "." . strtolower($check->getName()) . ".punishment")) === "flag" ? Lang::get(LangKeys::UI_COMMON_ENABLED) : Lang::get(LangKeys::UI_COMMON_DISABLED)),
		]));
		$player->sendForm($form);
	}
}
