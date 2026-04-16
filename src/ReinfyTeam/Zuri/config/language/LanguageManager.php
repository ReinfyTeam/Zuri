<?php

namespace ReinfyTeam\Zuri\config\language;

use ReinfyTeam\Zuri\ZuriAC;

class LanguageManager {
	
	private static array $registeredLocale = [];
	private static Language $currentLanguage;
	
	public static function registerLanguage(Language $language) : void {
		if (isset(self::getRegisteredLocale($language->getCode()))) return;
		
		self::$registeredLocale[$language->getCode()] = $language;
	}
	
	public function getLanguage() : Language {
		return self::$currentLanguage;
	}
	
	public static function setLanguage(Language $language) : void {
		if (!isset(self::getRegisteredLocale($language->getCode()))) {
			throw new LanguageError("This language is not registered yet: " . $language->getCode());
		}
		self::$currentLanguage = $language;
	}

	public static function getRegisteredLocale() : array {
		return self::$registeredLocale;
	}

	public static function loadLanguage() : void {
		foreach (ZuriAC::getInstance()->getResources() as $resource) {
			if (str_contains($resource->getPath(), "language/") && str_ends_with($resource->getPath(), ".yml")) {
				$language = new Language($resource->getPath());
				self::registerLanguage($language);
			}
		}
	}
}