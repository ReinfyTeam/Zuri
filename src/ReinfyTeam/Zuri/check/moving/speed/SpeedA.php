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
		return self::TYPE_PACKET;
	}

	public static function check(array $data) : array {

		$playerData = $data["playerData"];
		$constantData = $data["constantData"];

		if (
			$playerData["attackTicks"] < 20 ||
			$playerData["projectileTicks"] < 20 ||
			$playerData["teleportTicks"] < 60 ||
			$playerData["bowShotTicks"] < 20 ||
			$playerData["hurtTicks"] < 40 ||
			$playerData["commandTicks"] < 40 ||
			$playerData["isOnAdhesion"] ||
			$playerData["allowFlight"] ||
			$playerData["airTicks"] > 40 ||
			$playerData["isFlying"] ||
			$playerData["hasNoClientPredictions"] ||
			$playerData["isSurvival"] ||
			$playerData["isCreative"] ||
			$playerData["isSpectator"] ||
			!$playerData["isCurrentChunkLoaded"] ||
			$playerData["isRecentlyCancelled"] < 40
		) {
			return false;
		}

		$previous = $playerData["movement"]["from"];
		$next = $playerData["movement"]["from"];

		$externalData = $playerData["externalData"];

		$friction = $externalData[ExternalDataPath::FRICTION_FACTOR];
		$lastDistanceXZ = $externalData[ExternalDataPath::LAST_DISTANCE_XZ];
		$momentum = $externalData[ExternalDataPath::MOMENTUM];
		$movementMultiplier = $externalData[ExternalDataPath::MOVEMENT_MULTIPLIER];
		$acceleration = $externalData[ExternalDataPath::ACCELERATION];

		$expected = $momentum + $acceleration;
		$expected += ($playerData["jumpTicks"] < 5 && $player["isBlockAbove"]) ? $constantData[ConstantPath::JUMP_FACTOR] : 0;
		$expected += ($playerData["isOnGround"]) ? $constantData[ConstantPath::GROUND_FACTOR] : 0;
		$expected += ($playerData["isStartedJumping"] && $playerData["lastMoveTick"] > 5) ? $constantData[ConstantPath::LAST_JUMP_FACTOR] : 0;
		$expected += ($playerData["jumpTicks"] <= 20 && $playerData["isOnIce"]) ? $constantData[ConstantPath::ICE_FACTOR] : 0;
		
		$motion = Utils::arrayToVector3($playerData["motion"]);
		if (abs($motion->getX()) > 0 || abs($motion->getZ()) > 0) {
			$motion = Utils::arrayToVector3($playerData["motion"]);
			$motionX = abs($motion->getX());
			$motionZ = abs($motion->getZ());
			$knockback = $motionX * $motionX + $motionZ * $motionZ;
			
			$knockback *= $constantData[ConstantPath::KNOCKBACK_FACTOR];
			$expected += $knockback;
		}

		$expected += $playerData["lastMoveTick"] < 5 ? $constantData[ConstantPath::LAST_MOVE_FACTOR] : 0;

		$dist = $previous->distance($next);
		$distDiff = abs($dist - $expected);

		if ($dist > $expected && $distDiff > $constantData[ConstantPath::SPEED_THRESHOLD]) {
			$failed = true;
		}

		return self::buildResult($failed, [
			"expected" => $expected,
			"dist" => $dist,
			"distDiff" => $distDiff,
		]);
	}

}