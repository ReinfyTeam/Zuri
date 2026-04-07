<?php

declare(strict_types=1);

namespace ReinfyTeam\Zuri\checks\blockplace\scaffold;

use ReinfyTeam\Zuri\config\CheckConstants;
use ReinfyTeam\Zuri\config\CacheData;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\Event;
use pocketmine\math\Vector3;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\MathUtil;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function is_array;
use function max;
use function microtime;

class ScaffoldF extends Check {
	private const string BUFFER_KEY = CacheData::SCAFFOLD_F_BUFFER;
	private const string LAST_PLACE_AT_KEY = CacheData::SCAFFOLD_F_LAST_PLACE_AT;
	private const string LAST_BLOCK_KEY = CacheData::SCAFFOLD_F_LAST_BLOCK;
	private const string LAST_PLAYER_KEY = CacheData::SCAFFOLD_F_LAST_PLAYER;

	public function getName() : string {
		return "Scaffold";
	}

	public function getSubType() : string {
		return "F";
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if (!$event instanceof BlockPlaceEvent) {
			return;
		}

		$player = $event->getPlayer();
		if (
			!$player->isSurvival() ||
			$player->getAllowFlight() ||
			$player->isFlying() ||
			$player->hasNoClientPredictions() ||
			$playerAPI->isOnAdhesion() ||
			$playerAPI->isInWeb() ||
			$playerAPI->isInLiquid() ||
			$playerAPI->isRecentlyCancelledEvent() ||
			(int) $playerAPI->getPing() > (int) $this->getConstant(CheckConstants::SCAFFOLDF_GHOST_MAX_PING)
		) {
			$this->resetState($playerAPI);
			return;
		}

		$now = microtime(true);
		$blockPos = $event->getBlock()->getPosition();
		$playerPos = $player->getPosition();
		$lastPlaceAt = (float) $playerAPI->getExternalData(self::LAST_PLACE_AT_KEY, 0.0);
		$interval = $lastPlaceAt > 0.0 ? $now - $lastPlaceAt : 999.0;
		$playerBlockDistanceSquared = MathUtil::XZDistanceSquared($playerPos, $blockPos);

		$suspicious = false;
		$blockStep = 0.0;
		$playerStepSquared = 0.0;
		$isBelow = $blockPos->getY() <= ($playerPos->getY() - 1.0);

		$lastBlock = $playerAPI->getExternalData(self::LAST_BLOCK_KEY);
		$lastPlayer = $playerAPI->getExternalData(self::LAST_PLAYER_KEY);
		if (is_array($lastBlock) && is_array($lastPlayer)) {
			$previousBlock = new Vector3((float) ($lastBlock["x"] ?? 0.0), (float) ($lastBlock["y"] ?? 0.0), (float) ($lastBlock["z"] ?? 0.0));
			$previousPlayer = new Vector3((float) ($lastPlayer["x"] ?? 0.0), (float) ($lastPlayer["y"] ?? 0.0), (float) ($lastPlayer["z"] ?? 0.0));

			$blockStep = MathUtil::distance($previousBlock, $blockPos->asVector3());
			$playerStepSquared = MathUtil::XZDistanceSquared($previousPlayer, $playerPos);
			$suspicious =
				$interval <= (float) $this->getConstant(CheckConstants::SCAFFOLDF_GHOST_MAX_PLACE_INTERVAL) &&
				$isBelow &&
				$blockStep >= (float) $this->getConstant(CheckConstants::SCAFFOLDF_GHOST_MIN_BLOCK_STEP) &&
				$playerStepSquared <= (float) $this->getConstant(CheckConstants::SCAFFOLDF_GHOST_MAX_PLAYER_STEP_SQUARED) &&
				$playerBlockDistanceSquared >= (float) $this->getConstant(CheckConstants::SCAFFOLDF_GHOST_MIN_PLAYER_BLOCK_DISTANCE_SQUARED);
		}

		$buffer = $this->getBuffer($playerAPI);
		if ($suspicious) {
			$buffer++;
		} else {
			$buffer = max(0, $buffer - 1);
		}

		$this->setBuffer($playerAPI, $buffer);
		$playerAPI->setExternalData(self::LAST_PLACE_AT_KEY, $now);
		$playerAPI->setExternalData(self::LAST_BLOCK_KEY, ["x" => $blockPos->getX(), "y" => $blockPos->getY(), "z" => $blockPos->getZ()]);
		$playerAPI->setExternalData(self::LAST_PLAYER_KEY, ["x" => $playerPos->getX(), "y" => $playerPos->getY(), "z" => $playerPos->getZ()]);
		$this->debug($playerAPI, "interval={$interval}, blockStep={$blockStep}, playerStepSquared={$playerStepSquared}, playerBlockDistanceSquared={$playerBlockDistanceSquared}, below={$isBelow}, buffer={$buffer}");

		if ($buffer >= (int) $this->getConstant(CheckConstants::SCAFFOLDF_GHOST_BUFFER_LIMIT)) {
			$this->resetState($playerAPI);
			$this->failed($playerAPI);
		}
	}

	private function resetState(PlayerAPI $playerAPI) : void {
		$playerAPI->setExternalData(self::BUFFER_KEY, 0);
		$playerAPI->unsetExternalData(self::LAST_PLACE_AT_KEY);
		$playerAPI->unsetExternalData(self::LAST_BLOCK_KEY);
		$playerAPI->unsetExternalData(self::LAST_PLAYER_KEY);
	}

	private function getBuffer(PlayerAPI $playerAPI) : int {
		return (int) $playerAPI->getExternalData(self::BUFFER_KEY, 0);
	}

	private function setBuffer(PlayerAPI $playerAPI, int $buffer) : void {
		$playerAPI->setExternalData(self::BUFFER_KEY, $buffer);
	}
}
