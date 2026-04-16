<?php

namespace ReinfyTeam\Zuri\utils;

final class MathUtil {

    public static function distance(Position $a, Position $b) : float {
		return sqrt((($a->getX() - $b->getX()) ** 2) + (($a->getY() - $b->getY()) ** 2) + (($a->getZ() - $b->getZ()) ** 2));
	}

	
	public static function getMomentum(float $lastDistance, float $friction) : float {
		return $lastDistance * $friction * 0.91;
	}

	public static function getAcceleration(float $movement, float $effectMultiplier, float $friction, bool $onGround) : float {
		if (!$onGround) {
			return 0.02 * $movement;
		}

		return 0.1 * $movement * $effectMultiplier * ((0.6 / $friction) ** 3);
	}
}