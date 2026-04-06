<?php

declare(strict_types=1);

namespace ReinfyTeam\Zuri\checks\combat\rotation;

use ReinfyTeam\Zuri\config\CacheData;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function abs;
use function fmod;
use function max;

class RotationB extends Check {
	private const string LAST_YAW = CacheData::ROTATION_B_LAST_YAW;
	private const string LAST_PITCH = CacheData::ROTATION_B_LAST_PITCH;
	private const string LAST_DELTA_YAW = CacheData::ROTATION_B_LAST_DELTA_YAW;
	private const string BUFFER = CacheData::ROTATION_B_BUFFER;

	public function getName() : string {
		return "Rotation";
	}

	public function getSubType() : string {
		return "B";
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		if (!$packet instanceof PlayerAuthInputPacket) {
			return;
		}

		$player = $playerAPI->getPlayer();
		if (
			!$player->isSurvival() ||
			$playerAPI->isRecentlyCancelledEvent() ||
			(int) $playerAPI->getPing() > (int) $this->getConstant("max-ping")
		) {
			$this->setBuffer($playerAPI, 0);
			$this->storeState($playerAPI, $packet->getYaw(), $packet->getPitch(), 0.0);
			return;
		}

		$lastYaw = $playerAPI->getExternalData(self::LAST_YAW);
		$lastPitch = $playerAPI->getExternalData(self::LAST_PITCH);
		if ((!is_float($lastYaw) && !is_int($lastYaw)) || (!is_float($lastPitch) && !is_int($lastPitch))) {
			$this->storeState($playerAPI, $packet->getYaw(), $packet->getPitch(), 0.0);
			return;
		}

		$yaw = $packet->getYaw();
		$pitch = $packet->getPitch();
		$deltaYaw = $this->angleDelta((float) $lastYaw, $yaw);
		$deltaPitch = abs($pitch - (float) $lastPitch);
		$lastDeltaYaw = (float) $playerAPI->getExternalData(self::LAST_DELTA_YAW, $deltaYaw);

		$inCombatWindow = $playerAPI->getAttackTicks() < (float) $this->getConstant("combat-window-ticks");
		$looksSnapped =
			$deltaYaw >= (float) $this->getConstant("snap-min-delta-yaw") &&
			abs($deltaYaw - $lastDeltaYaw) <= (float) $this->getConstant("snap-repeat-epsilon") &&
			$deltaPitch <= (float) $this->getConstant("snap-max-delta-pitch");

		$buffer = $this->getBuffer($playerAPI);
		if ($inCombatWindow && $looksSnapped) {
			$buffer++;
		} else {
			$buffer = max(0, $buffer - 1);
		}

		$this->setBuffer($playerAPI, $buffer);
		$this->storeState($playerAPI, $yaw, $pitch, $deltaYaw);
		$this->debug($playerAPI, "deltaYaw={$deltaYaw}, deltaPitch={$deltaPitch}, lastDeltaYaw={$lastDeltaYaw}, buffer={$buffer}");

		if ($buffer >= (int) $this->getConstant("snap-buffer-limit")) {
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

	private function storeState(PlayerAPI $playerAPI, float $yaw, float $pitch, float $deltaYaw) : void {
		$playerAPI->setExternalData(self::LAST_YAW, $yaw);
		$playerAPI->setExternalData(self::LAST_PITCH, $pitch);
		$playerAPI->setExternalData(self::LAST_DELTA_YAW, $deltaYaw);
	}
}