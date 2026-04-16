<?php

namespace ReinfyTeam\Zuri\utils;

use pocketmine\math\Vector3;
use pocketmine\math\Vector2;

final class Utils {
    
    public static function vector3ToArray(Vector3 $vector3) : array {
        return [
            "x" => $vector3->getX(),
            "y" => $vector3->getY(),
            "z" => $vector3->getZ()
        ];
    }

    public static function arrayToVector3(array $array) {
        return new Vector3($array["x"], $array["y"], $array["z"]);
    }

    public static function vector2ToArray(Vector2 $vector2) : array {
        return [
            "x" => $vector2->getX(),
            "z" => $vector2->getZ()
        ];
    }

    public static function arrayToVector2(array $array) : Vector2 {
        return new Vector2($array["x"], $array["z"]);
    }
}