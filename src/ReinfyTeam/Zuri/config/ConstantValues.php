<?php

namespace ReinfyTeam\Zuri\config;

class ConstantValues extends ConfigManager implements ConstantPath {

    public function __construct(string $path) {
        parent::__construct($path);

        $this->checkVersion(self::CONSTANT_VERSION);
    }

    public function getConstant(string $key, mixed $default = null) : mixed {
        return parent::getData("zuri.constants." . $key, $default);
    }
}