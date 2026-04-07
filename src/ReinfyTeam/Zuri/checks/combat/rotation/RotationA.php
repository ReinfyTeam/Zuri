<?php

declare(strict_types=1);

namespace ReinfyTeam\Zuri\checks\combat\rotation;

use ReinfyTeam\Zuri\config\CheckConstants;
use ReinfyTeam\Zuri\config\CacheData;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function abs;
use function fmod;
use function max;

class RotationA extends Check {
	private const string LAST_YAW = CacheData::ROTATION_A_LAST_YAW;
	private const string LAST_PITCH = CacheData::ROTATION_A_LAST_PITCH;
	private const string LAST_DELTA_YAW = CacheData::ROTATION_A_LAST_DELTA_YAW;
	private const string LAST_DELTA_PITCH = CacheData::ROTATION_A_LAST_DELTA_PITCH;
	private const string BUFFER = CacheData::ROTATION_A_BUFFER;

	public function getName() : string {
		return "Rotation";
	}

	public function getSubType() : string {
		return "A";
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		if (!$packet instanceof PlayerAuthInputPacket) {
			return;
		}

		$player = $playerAPI->getPlayer();
		if (!$player->isSurvival() || (int) $playerAPI->getPing() > (int) $this->getConstant(CheckConstants::ROTATIONA_MAX_PING)) {
			$this->setBuffer($playerAPI, 0);
			$this->storeAngles($playerAPI, $packet->getYaw(), $packet->getPitch(), 0.0, 0.0);
			return;
		}

		$lastYaw = $playerAPI->getExternalData(self::LAST_YAW);
		$lastPitch = $playerAPI->getExternalData(self::LAST_PITCH);
		if (!is_float($lastYaw) && !is_int($lastYaw) || !is_float($lastPitch) && !is_int($lastPitch)) {
			$this->storeAngles($playerAPI, $packet->getYaw(), $packet->getPitch(), 0.0, 0.0);
			return;
		}

		$yaw = $packet->getYaw();
		$pitch = $packet->getPitch();
		$deltaYaw = $this->angleDelta((float) $lastYaw, $yaw);
		$deltaPitch = abs($pitch - (float) $lastPitch);
		$lastDeltaYaw = (float) $playerAPI->getExternalData(self::LAST_DELTA_YAW, $deltaYaw);
		$lastDeltaPitch = (float) $playerAPI->getExternalData(self::LAST_DELTA_PITCH, $deltaPitch);

		$inCombatWindow = $playerAPI->getAttackTicks() < (float) $this->getConstant(CheckConstants::ROTATIONA_COMBAT_WINDOW_TICKS);
		$isPatternStable =
			$deltaYaw >= (float) $this->getConstant(CheckConstants::ROTATIONA_MIN_DELTA_YAW) &&
			$deltaYaw <= (float) $this->getConstant(CheckConstants::ROTATIONA_MAX_DELTA_YAW) &&
			$deltaPitch >= (float) $this->getConstant(CheckConstants::ROTATIONA_MIN_DELTA_PITCH) &&
			$deltaPitch <= (float) $this->getConstant(CheckConstants::ROTATIONA_MAX_DELTA_PITCH) &&
			abs($deltaYaw - $lastDeltaYaw) <= (float) $this->getConstant(CheckConstants::ROTATIONA_YAW_STEP_EPSILON) &&
			abs($deltaPitch - $lastDeltaPitch) <= (float) $this->getConstant(CheckConstants::ROTATIONA_PITCH_STEP_EPSILON);

		$buffer = $this->getBuffer($playerAPI);
		if ($inCombatWindow && $isPatternStable) {
			$buffer++;
		} else {
			$buffer = max(0, $buffer - 1);
		}

		$this->setBuffer($playerAPI, $buffer);
		$this->storeAngles($playerAPI, $yaw, $pitch, $deltaYaw, $deltaPitch);
		$this->debug($playerAPI, "deltaYaw={$deltaYaw}, deltaPitch={$deltaPitch}, lastDeltaYaw={$lastDeltaYaw}, lastDeltaPitch={$lastDeltaPitch}, buffer={$buffer}");

		if ($buffer >= (int) $this->getConstant(CheckConstants::ROTATIONA_BUFFER_LIMIT)) {
			$this->setBuffer($playerAPI, 0);
			$this->failed($playerAPI);
		}
	}

	private function angleDelta(float $from, float $to) : float {
		return abs(fmod(($to - $from + 540.0), 360.0) - 180.0);
	}

	private function getBuffer(PlayerAPI $playerAPI) : int {
		return (int) $playerAPI->getExternalData(self::BUFFER, 0);
	}

	private function setBuffer(PlayerAPI $playerAPI, int $value) : void {
		$playerAPI->setExternalData(self::BUFFER, $value);
	}

	private function storeAngles(PlayerAPI $playerAPI, float $yaw, float $pitch, float $deltaYaw, float $deltaPitch) : void {
		$playerAPI->setExternalData(self::LAST_YAW, $yaw);
		$playerAPI->setExternalData(self::LAST_PITCH, $pitch);
		$playerAPI->setExternalData(self::LAST_DELTA_YAW, $deltaYaw);
		$playerAPI->setExternalData(self::LAST_DELTA_PITCH, $deltaPitch);
	}
}
