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

namespace ReinfyTeam\Zuri\config;

use pocketmine\utils\Config;
use ReinfyTeam\Zuri\utils\TextUtil;
use ReinfyTeam\Zuri\utils\Utils;
use ReinfyTeam\Zuri\ZuriAC;
use function basename;
use function copy;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function pathinfo;
use function str_replace;
use function unlink;
use function version_compare;
use function yaml_parse;

/**
 * Helper for managing plugin configuration files.
 */
class ConfigManager implements ConfigPath {
	private Config $config;
	private string $path;
	/** @var array<string, mixed> */
	private array $defaultValues = [];

	/**
	 * @param string $path Path to the YAML config resource.
	 */
	public function __construct(string $path) {
		$this->path = $path;

		ZuriAC::getInstance()->saveResource(basename($path));

		$this->config = new Config(
			$path,
			Config::YAML
		);

		$this->checkVersion(self::CONFIG_VERSION);

		// Set default values natively (without relying on the Config class) to ensure they are available even if the config file is missing or incomplete.
		// what a stupid idea i think btw, just to make sure the server won't crash for config things wtf
		$defaultPath = ZuriAC::getInstance()->getResourcePath(basename($path));
		$stream = @fopen($defaultPath, "r");

		if ($stream !== false) {
			$content = @stream_get_contents($stream);
			@fclose($stream);
			// Parse the YAML content
			$parsed = @yaml_parse($content ?: '');
			
			// Verify the output is actually an array
			if (is_array($parsed)) {
				/** @var array<string, mixed> $parsed */
				$this->defaultValues = $parsed;
			} else {
				$this->defaultValues = []; // Fallback if YAML is empty, invalid, or not an array mapping
				// but i don't thing this not be happen because the config file is always valid and not empty.
			}
		}
	}

	/**
	 * Retrieves nested configuration data by key.
	 * @param array<string,string> $replacements
	 */
	public function getData(string $key, mixed $default = null, array $replacements = []) : mixed {
		$value = $this->config->getNested($key, ($this->getDefaultValue($key) ?? $default) ?? $key);
		return is_string($value) ? TextUtil::replaceText($value, $replacements) : $value;
	}

	/** @param array<string,string> $replacements */
	public function getString(string $key, array $replacements = []) : string {
		$value = $this->getData($key, $this->getDefaultValue($key), $replacements);
		return is_string($value) ? $value : Utils::readString($this->getDefaultValue($key));
	}

	public function getInt(string $key) : int {
		$value = $this->getData($key, $this->getDefaultValue($key));
		return is_int($value) ? $value : Utils::readInt($this->getDefaultValue($key));
	}

	public function getFloat(string $key) : float {
		$value = $this->getData($key, $this->getDefaultValue($key));
		return is_int($value) || is_float($value) ? (float) $value : Utils::readFloat($this->getDefaultValue($key));
	}

	public function getBool(string $key) : bool {
		$value = $this->getData($key, $this->getDefaultValue($key));
		return is_bool($value) ? $value : Utils::readBool($this->getDefaultValue($key));
	}

	/**
	 * Sets nested configuration data and persists the file.
	 */
	public function setData(string $key, mixed $value) : void {
		$this->config->setNested($key, $value);
		$this->config->save();
	}

	/**
	 * Ensures configuration version compatibility and replaces resource if outdated.
	 */
	public function checkVersion(string $version, string $versionKey = self::CURRENT_CONFIG_VERSION) : void {
		$currentVersion = $this->getString($versionKey);
		if ($currentVersion !== "") {
			if (version_compare($version, $currentVersion, '>')) {
				@copy(
					$this->path,
					str_replace(pathinfo($this->path, PATHINFO_FILENAME), pathinfo($this->path, PATHINFO_FILENAME) . "-old", $this->path)
				);
				@unlink($this->path);
				ZuriAC::getInstance()->saveResource(basename($this->path));
				$this->config->reload();
			}
		}
	}

	/**
	 * Exports the raw configuration data as an array.
	 * @return array<array-key,mixed>
	 */
	public function export() : array {
		return $this->config->getAll();
	}

	public function getDefaultValue(string $key) : mixed {
		return $this->defaultValues[$key] ?? null;
	}
}
