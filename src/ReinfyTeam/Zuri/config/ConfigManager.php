<?php

namespace ReinfyTeam\Zuri\config;

use pocketmine\utils\Config;
use ReinfyTeam\Zuri\ZuriAC;
use ReinfyTeam\Zuri\utils\FileUtil;

class ConfigManager implements ConfigPath {
    private Config $config;
    private string $path;

    public function __construct(string $path) {
        $this->path = $path;

        ZuriAC::getInstance()->saveResource($path);
        
        $this->config = new Config(
            $path, 
            Config::YAML
        );

        $this->checkVersion();
    }

    public static function getData(string $key, mixed $default = null) : mixed {
        return $this->config->getNested($key, $default ?? $key);
    }

    public static function setData(string $key, mixed $value) : void {
        $this->config->setNested($key, $value);
        $this->config->save();
    }

    public function checkVersion() : void {
        if (version_compare(self::CONFIG_VERSION, $this->getData(self::CONFIG_VERSION), '>=')) {
            FileUtil::asyncRename(
                $path,
                str_replace(pathinfo($path, PATHINFO_FILENAME), pathinfo($path, PATHINFO_FILENAME) . "-old", $path) 
            );
        }
    }
}