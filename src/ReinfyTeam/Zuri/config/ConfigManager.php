<?php

namespace ReinfyTeam\Zuri\config;

use pocketmine\utils\Config;
use ReinfyTeam\Zuri\ZuriAC;

class ConfigManager implements ConfigPath {
    private Config $config;
    private string $path;

    public function __construct(string $path) {
        $this->path = $path;

        ZuriAC::getInstance()->saveResource(basename($path));
        
        $this->config = new Config(
            $path, 
            Config::YAML
        );

        $this->checkVersion(self::CONFIG_VERSION);
    }

    public function getData(string $key, mixed $default = null) : mixed {
        return $this->config->getNested($key, $default ?? $key);
    }

    public function setData(string $key, mixed $value) : void {
        $this->config->setNested($key, $value);
        $this->config->save();
    }

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

    public function export() : array {
        return $this->config->getAll();
    }
}