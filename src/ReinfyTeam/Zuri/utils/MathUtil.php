<?php

namespace ReinfyTeam\Zuri\utils;

final class MathUtil {

    public static function distance(Position $a, Position $b) : float {
		return sqrt((($a->getX() - $b->getX()) ** 2) + (($a->getY() - $b->getY()) ** 2) + (($a->getZ() - $b->getZ()) ** 2));
	}
}