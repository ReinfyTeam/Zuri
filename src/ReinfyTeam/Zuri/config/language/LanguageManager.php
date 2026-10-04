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

namespace ReinfyTeam\Zuri\config\language;

use ReinfyTeam\Zuri\ZuriAC;
use function array_key_first;
use function str_ends_with;
use function str_replace;
use function str_starts_with;

class LanguageManager {
	private array $registeredLocale = [];
	private Language $currentLanguage;

	public function registerLanguage(Language $language) : void {
		if ($this->isRegisteredLocale($language->getCode())) {
			return;
		}

		$this->registeredLocale[$language->getCode()] = $language;
	}

	public function getCurrentLanguage() : Language {
		return $this->currentLanguage;
	}

	public function setCurrentLanguage(Language $currentLanguage) : void {
		if (!$this->isRegisteredLocale($currentLanguage->getCode())) {
			throw new LanguageError("This language is not registered yet: " . $currentLanguage->getCode());
		}
		$this->currentLanguage = $currentLanguage;
	}

	public function isRegisteredLocale(string $code) : bool {
		return isset($this->registeredLocale[$code]);
	}

	public function getRegisteredLocale() : array {
		return $this->registeredLocale;
	}

	public static function loadLanguage() : self {
		$instance = new LanguageManager();
		$plugin = ZuriAC::getInstance();
		foreach ($plugin->getResources() as $path => $resource) {
			$path = str_replace("\\", "/", $path);
			if (str_starts_with($path, "lang/") && str_ends_with($path, ".yml")) {
				$plugin->saveResource($path);
				$language = new Language($plugin->getDataFolder() . $path);
				$instance->registerLanguage($language);
			}
		}

		if (isset($instance->registeredLocale["en_US"])) {
			$instance->setCurrentLanguage($instance->registeredLocale["en_US"]);
		} elseif ($instance->registeredLocale !== []) {
			$instance->setCurrentLanguage($instance->registeredLocale[array_key_first($instance->registeredLocale)]);
		} else {
			throw new LanguageError("No language resources were found");
		}
		return $instance;
	}
}