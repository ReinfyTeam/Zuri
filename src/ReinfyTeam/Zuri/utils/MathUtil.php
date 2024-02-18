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
 * @author ReinfyTeam
 * @link https://github.com/ReinfyTeam/
 *
 *
 */

declare(strict_types=1);

namespace ReinfyTeam\Zuri\utils;

use pocketmine\math\Vector3;
use ReinfyTeam\Zuri\player\PlayerAPI;
use function ceil;
use function pow;
use function sqrt;

class MathUtil {
	public static function getVectorOnEyeHeight(PlayerAPI $playerAPI) {
		return $playerAPI->getPlayer()->getLocation()->add(0, $playerAPI->getPlayer()->getEyeHeight(), 0);
	}

	public static function getDeltaDirectionVector(PlayerAPI $playerAPI, float $distance) : Vector3 {
		return $playerAPI->getPlayer()->getDirectionVector()->multiply($distance);
	}

	public static function distance(Vector3 $from, Vector3 $to) {
		return sqrt(pow($from->getX() - $to->getX(), 2) + pow($from->getY() - $to->getY(), 2) + pow($from->getZ() - $to->getZ(), 2));
	}

	public static function pingFormula(float $ping) {
		return (int) ceil($ping / 50);
	}

	public static function XZDistanceSquared(Vector3 $v1, Vector3 $v2) : float {
		return ($v1->x - $v2->x) ** 2 + ($v1->z - $v2->z) ** 2;
	}
}