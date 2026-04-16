<?php

namespace ReinfyTeam\Zuri\check\moving\speed;

use ReinfyTeam\Zuri\check\Check;

class SpeedA extends Check {
	
	public function getName() : string {
		return "Speed";
	}

	public function getSubType() : string {
		return "A";
	}

	public function getType() : int {
		return self::TYPE_PLAYER;
	}

	public static function check(array $data) : bool {
		if (
			$data["attackTicks"] < 20 ||
			$data["projectileTicks"] < 20 ||
			$data["teleportTicks"] < 60 ||
			$data["bowShotTicks"] < 20 ||
			$data["hurtTicks"] < 40 ||
			$data["commandTicks"] < 40 ||
			$data["isOnAdhesion"] ||
			$data["allowFlight"] ||
			$data["airTicks"] > 40 ||
			$data["isFlying"] ||
			$data["hasNoClientPredictions"] ||
			$data["isSurvival"] ||
			$data["isCreative"] ||
			$data["isSpectator"] ||
			!$data["isCurrentChunkLoaded"] ||
			$data["recentlyCancelled"] < 40
		) {
			return false;
		}
		
		$previous = $data["movement"]["from"];
		$next = $data["movement"]["from"];

		
	}

}