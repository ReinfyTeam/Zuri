<?php

declare(strict_types=1);

namespace ReinfyTeam\Zuri\checks\combat\rotation;

use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function abs;
use function fmod;
use function max;

class RotationA extends Check {
	private const LAST_YAW = "RotationA.lastYaw";
	private const LAST_PITCH = "RotationA.lastPitch";
	private const LAST_DELTA_YAW = "RotationA.lastDeltaYaw";
	private const LAST_DELTA_PITCH = "RotationA.lastDeltaPitch";
	private const BUFFER = "RotationA.buffer";

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
		if (!$player->isSurvival() || (int) $playerAPI->getPing() > (int) $this->getConstant("max-ping")) {
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

		$inCombatWindow = $playerAPI->getAttackTicks() < (float) $this->getConstant("combat-window-ticks");
		$isPatternStable =
			$deltaYaw >= (float) $this->getConstant("min-delta-yaw") &&
			$deltaYaw <= (float) $this->getConstant("max-delta-yaw") &&
			$deltaPitch >= (float) $this->getConstant("min-delta-pitch") &&
			$deltaPitch <= (float) $this->getConstant("max-delta-pitch") &&
			abs($deltaYaw - $lastDeltaYaw) <= (float) $this->getConstant("yaw-step-epsilon") &&
			abs($deltaPitch - $lastDeltaPitch) <= (float) $this->getConstant("pitch-step-epsilon");

		$buffer = $this->getBuffer($playerAPI);
		if ($inCombatWindow && $isPatternStable) {
			$buffer++;
		} else {
			$buffer = max(0, $buffer - 1);
		}

		$this->setBuffer($playerAPI, $buffer);
		$this->storeAngles($playerAPI, $yaw, $pitch, $deltaYaw, $deltaPitch);
		$this->debug($playerAPI, "deltaYaw={$deltaYaw}, deltaPitch={$deltaPitch}, lastDeltaYaw={$lastDeltaYaw}, lastDeltaPitch={$lastDeltaPitch}, buffer={$buffer}");

		if ($buffer >= (int) $this->getConstant("buffer-limit")) {
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
