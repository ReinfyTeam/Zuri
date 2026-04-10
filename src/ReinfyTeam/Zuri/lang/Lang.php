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

namespace ReinfyTeam\Zuri\lang;

use pocketmine\utils\Config;
use ReinfyTeam\Zuri\config\ConfigManager;
use ReinfyTeam\Zuri\config\ConfigPaths;
use ReinfyTeam\Zuri\utils\Utils;
use ReinfyTeam\Zuri\ZuriAC;
use function array_diff;
use function array_key_exists;
use function array_values;
use function count;
use function explode;
use function file_exists;
use function glob;
use function is_array;
use function is_numeric;
use function is_string;
use function preg_match;
use function sort;
use function str_replace;
use function strpos;
use function substr;

final class Lang {
	private const DEFAULT_LOCALE = 'en_US';

	/** @var array<int|string,mixed> */
	private static array $messages = [];
	/** @var array<int|string,mixed> */
	private static array $fallbackMessages = [];
	/** @var array<string,array<int|string,mixed>> */
	private static array $allLocales = [];
	/** @var string[] */
	private static array $availableLocales = [];
	private static string $activeLocale = self::DEFAULT_LOCALE;

	public static function boot() : void {
		$localeRaw = ConfigManager::getData(ConfigPaths::LANGUAGE_LOCALE, self::DEFAULT_LOCALE);
		$fallbackLocaleRaw = ConfigManager::getData(ConfigPaths::LANGUAGE_FALLBACK_LOCALE, self::DEFAULT_LOCALE);
		$locale = is_string($localeRaw) ? $localeRaw : self::DEFAULT_LOCALE;
		$fallbackLocale = is_string($fallbackLocaleRaw) ? $fallbackLocaleRaw : self::DEFAULT_LOCALE;

		self::ensureBaseLanguageExists();
		self::loadAllLocales();

		if (!isset(self::$allLocales[$fallbackLocale])) {
			$fallbackLocale = self::DEFAULT_LOCALE;
		}
		if (!isset(self::$allLocales[$locale])) {
			$locale = $fallbackLocale;
		}

		self::$activeLocale = $locale;
		self::$fallbackMessages = self::$allLocales[$fallbackLocale] ?? [];
		self::$messages = self::mergeMissingWithFallback(self::$allLocales[$locale] ?? [], self::$fallbackMessages);

		self::validateAllLocales();
	}

	/** @return string[] */
	public static function getAvailableLocales() : array {
		return self::$availableLocales;
	}

	public static function getActiveLocale() : string {
		return self::$activeLocale;
	}

	public static function setLocale(string $locale, bool $persist = true) : bool {
		if (!isset(self::$allLocales[$locale])) {
			return false;
		}

		self::$activeLocale = $locale;
		self::$messages = self::mergeMissingWithFallback(self::$allLocales[$locale], self::$fallbackMessages);

		if ($persist) {
			ConfigManager::setData(ConfigPaths::LANGUAGE_LOCALE, $locale);
		}

		return true;
	}

	private static function ensureBaseLanguageExists() : void {
		$path = ZuriAC::getInstance()->getDataFolder() . 'lang/' . self::DEFAULT_LOCALE . '.yml';
		if (!file_exists($path)) {
			ZuriAC::getInstance()->saveResource('lang/' . self::DEFAULT_LOCALE . '.yml');
		}
	}

	private static function loadAllLocales() : void {
		self::$allLocales = [];
		self::$availableLocales = [];

		$paths = [];
		$legacy = glob(ZuriAC::getInstance()->getDataFolder() . 'lang/*.yml');
		if (is_array($legacy)) {
			foreach ($legacy as $path) {
				$paths[] = $path;
			}
		}
		$alternative = glob(ZuriAC::getInstance()->getDataFolder() . 'langs/*.yml');
		if (is_array($alternative)) {
			foreach ($alternative as $path) {
				$paths[] = $path;
			}
		}

		foreach ($paths as $path) {
			$locale = self::localeFromPath($path);
			if ($locale === null) {
				continue;
			}
			if (!isset(self::$allLocales[$locale])) {
				self::$allLocales[$locale] = self::loadLocale($locale);
				self::$availableLocales[] = $locale;
			}
		}

		sort(self::$availableLocales);

		if (!isset(self::$allLocales[self::DEFAULT_LOCALE])) {
			self::$allLocales[self::DEFAULT_LOCALE] = [];
			self::$availableLocales[] = self::DEFAULT_LOCALE;
			sort(self::$availableLocales);
		}
	}

	/** @return array<int|string,mixed> */
	private static function loadLocale(string $locale) : array {
		$path = ZuriAC::getInstance()->getDataFolder() . "lang/{$locale}.yml";
		if (!file_exists($path)) {
			$altPath = ZuriAC::getInstance()->getDataFolder() . "langs/{$locale}.yml";
			if (!file_exists($altPath)) {
				return [];
			}
			$path = $altPath;
		}

		try {
			$data = (new Config($path, Config::YAML))->getAll();
			return $data;
		} catch (\Throwable $e) {
			ZuriAC::getInstance()->getLogger()->warning("Failed to load language file '{$path}': " . $e->getMessage());
			return [];
		}
	}

	private static function localeFromPath(string $path) : ?string {
		$path = str_replace("\\", "/", $path);
		$pos = strpos($path, '/lang/');
		$offset = 6;
		if ($pos === false) {
			$pos = strpos($path, '/langs/');
			$offset = 7;
		}
		if ($pos === false) {
			return null;
		}
		$file = substr($path, $pos + $offset);
		if (!preg_match('/^([A-Za-z0-9_\-]+)\.yml$/', $file, $m)) {
			return null;
		}
		return $m[1];
	}

	/** @param array<int|string,mixed> $source
	 *  @return string[] */
	private static function flattenKeys(array $source, string $prefix = '') : array {
		$result = [];
		foreach ($source as $k => $v) {
			$key = $prefix === '' ? (string) $k : $prefix . '.' . (string) $k;
			if (is_array($v)) {
				foreach (self::flattenKeys($v, $key) as $nested) {
					$result[] = $nested;
				}
			} else {
				$result[] = $key;
			}
		}
		sort($result);
		return $result;
	}

	/** @param array<int|string,mixed> $locale
	 *  @param array<int|string,mixed> $fallback
	 *  @return array<int|string,mixed>
	 */
	private static function mergeMissingWithFallback(array $locale, array $fallback) : array {
		$merged = $fallback;
		foreach ($locale as $k => $v) {
			if (is_array($v) && isset($merged[$k]) && is_array($merged[$k])) {
				$merged[$k] = self::mergeMissingWithFallback($v, $merged[$k]);
			} else {
				$merged[$k] = $v;
			}
		}
		return $merged;
	}

	private static function validateAllLocales() : void {
		$logger = ZuriAC::getInstance()->getLogger();
		$fallbackKeys = self::flattenKeys(self::$fallbackMessages);

		foreach (self::$allLocales as $locale => $data) {
			$keys = self::flattenKeys($data);
			$missing = array_values(array_diff($fallbackKeys, $keys));
			if ($missing !== []) {
				$logger->warning(self::get(LangKeys::LANG_VALIDATE_MISSING, [
					'locale' => $locale,
					'missingCount' => (string) count($missing),
				]));
			}
		}
	}

	public static function raw(string $key, string $default = '') : string {
		$value = self::resolve(self::$messages, $key);
		if ($value === null) {
			$value = self::resolve(self::$fallbackMessages, $key);
		}
		if ($value === null) {
			$value = $default !== '' ? $default : $key;
		}
		if (is_string($value)) {
			return $value;
		}
		if (is_numeric($value)) {
			return (string) $value;
		}
		if ($default !== '') {
			return $default;
		}
		return $key;
	}

	/** @param array<string,string|int|float> $replacements */
	public static function get(string $key, array $replacements = [], string $default = '') : string {
		$text = self::raw($key, $default);
		if (!array_key_exists('prefix', $replacements)) {
			$prefixRaw = ConfigManager::getData(ConfigPaths::PREFIX, '');
			$replacements['prefix'] = is_string($prefixRaw) ? $prefixRaw : '';
		}
		foreach ($replacements as $token => $value) {
			$text = str_replace('{' . $token . '}', (string) $value, $text);
		}
		return Utils::ParseColors($text);
	}

	/** @param array<int|string,mixed> $source */
	private static function resolve(array $source, string $path) : mixed {
		$segments = explode('.', $path);
		$cursor = $source;
		foreach ($segments as $segment) {
			if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
				return null;
			}
			$cursor = $cursor[$segment];
		}
		return $cursor;
	}
}
