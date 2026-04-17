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
use function str_contains;
use function str_ends_with;

class LanguageManager {
	private array $registeredLocale = [];
	private Language $currentLanguage;

	public function registerLanguage(Language $language) : void {
		if ($this->isRegisteredLocale($language->getCode())) {
			return;
		}

		$this->registeredLocale[$language->getCode()] = $language;
	}

	public function getLanguage() : Language {
		return $this->currentLanguage;
	}

	public function setLanguage(Language $language) : void {
		if ($this->isRegisteredLocale($language->getCode())) {
			throw new LanguageError("This language is not registered yet: " . $language->getCode());
		}
		$this->currentLanguage = $language;
	}

	public function isRegisteredLocale(string $code) : bool {
		return isset($this->registeredLocale[$code]);
	}

	public function getRegisteredLocale() : array {
		return $this->registeredLocale;
	}

	public static function loadLanguage() : self {
		$instance = new LanguageManager();
		foreach (ZuriAC::getInstance()->getResources() as $resource) {
			if (str_contains($resource->getPath(), "language/") && str_ends_with($resource->getPath(), ".yml")) {
				$language = new Language($resource->getPath());
				$instance->registerLanguage($language);
			}
		}
		return $instance;
	}
}