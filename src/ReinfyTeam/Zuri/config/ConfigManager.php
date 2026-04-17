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
use ReinfyTeam\Zuri\ZuriAC;
use function basename;
use function copy;
use function pathinfo;
use function str_replace;
use function unlink;
use function version_compare;

/**
 * Helper for managing plugin configuration files.
 */
class ConfigManager implements ConfigPath {
	private Config $config;
	private string $path;

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
	}

	/**
	 * Retrieves nested configuration data by key.
	 *
	 * @param string $key
	 * @param mixed $default
	 * @return mixed
	 */
	public function getData(string $key, mixed $default = null) : mixed {
		return $this->config->getNested($key, $default ?? $key);
	}

	/**
	 * Sets nested configuration data and persists the file.
	 *
	 * @param string $key
	 * @param mixed $value
	 * @return void
	 */
	public function setData(string $key, mixed $value) : void {
		$this->config->setNested($key, $value);
		$this->config->save();
	}

	/**
	 * Ensures configuration version compatibility and replaces resource if outdated.
	 *
	 * @param string $version
	 * @return void
	 */
	public function checkVersion(string $version) : void {
		if ($this->getData($version) !== null) {
			if (version_compare($version, $this->getData(self::CONFIG_VERSION), '>=')) {
				@copy(
					$this->path,
					str_replace(pathinfo($this->path, PATHINFO_FILENAME), pathinfo($this->path, PATHINFO_FILENAME) . "-old", $this->path) 
				);
				@unlink($this->path);
				ZuriAC::getInstance()->saveResource(basename($this->path));
			}
		}
	}

	/**
	 * Exports the raw configuration data as an array.
	 *
	 * @return array
	 */
	public function export() : array {
		return $this->config->getAll();
	}
}