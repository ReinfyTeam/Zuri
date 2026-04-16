<?php

namespace ReinfyTeam\Zuri\config;

class ConstantValue extends ConfigManager {

    public function __construct(string $path) {
        parent::__construct($path);
    }

    public function getConstant(string $parameter, mixed $value) : mixed {
        return $this->getData($parameter, $value);
    }
}