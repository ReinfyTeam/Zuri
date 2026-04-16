<?php

namespace ReinfyTeam\Zuri\check;

abstract class Check {
	
	public const TYPE_PACKET = 0;
	public const TYPE_PLAYER = 1;
	public const TYPE_EVENT = 2;

	abstract public function getName() : string;
	
	abstract public function getSubType() : string;

	abstract public function getType() : int;
	
	abstract public static function check(array $data) : bool;
}