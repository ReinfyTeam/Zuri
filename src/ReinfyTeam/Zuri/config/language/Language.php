<?php

namespace ReinfyTeam\Zuri\config\language;

use pocketmine\utils\Config;
use ReinfyTeam\Zuri\utils\TextUtil;

class Language implements LanguagePath {
    
    private Config $languageData;
    
    public function __construct(string $path) {
        $this->languageData = new Config($path, Config::YAML);
    }

    public function translate(string $key, array $replacements = []) : string {
        return TextUtil::parseColors(TextUtil::replaceText($this->languageData->get($key, "$key"), $replacements));
    }

    public function save() : void {
        $this->languageData->save();
    }

    public function reload() : void {
        $this->languageData->reload();
    }
}