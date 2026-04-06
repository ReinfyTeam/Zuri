<?php

declare(strict_types=1);

namespace ReinfyTeam\Zuri\checks\blockplace\scaffold;

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

class ScaffoldE extends Check {
	private const BUFFER_KEY = CacheData::SCAFFOLD_E_BUFFER;
	private const LAST_BLOCK_KEY = CacheData::SCAFFOLD_E_LAST_BLOCK;
	private const LAST_PLACE_AT_KEY = CacheData::SCAFFOLD_E_LAST_PLACE_AT;

	public function getName() : string {
		return "Scaffold";
	}

	public function getSubType() : string {
		return "E";
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
			(int) $playerAPI->getPing() > (int) $this->getConstant("expansion-max-ping")
		) {
			$this->setBuffer($playerAPI, 0);
			return;
		}

		$blockPos = $event->getBlock()->getPosition();
		$now = microtime(true);
		$lastPlaceAt = (float) $playerAPI->getExternalData(self::LAST_PLACE_AT_KEY, 0.0);
		$interval = $lastPlaceAt > 0 ? $now - $lastPlaceAt : 999.0;

		$horizontalDistanceSquared = MathUtil::XZDistanceSquared($player->getPosition(), $blockPos);
		$suspicious = $interval <= (float) $this->getConstant("max-place-interval") &&
			$horizontalDistanceSquared > (float) $this->getConstant("max-horizontal-distance-squared");

		$sequentialDistance = 0.0;
		$lastBlock = $playerAPI->getExternalData(self::LAST_BLOCK_KEY);
		if (is_array($lastBlock)) {
			$previousBlock = new Vector3((float) ($lastBlock["x"] ?? 0.0), (float) ($lastBlock["y"] ?? 0.0), (float) ($lastBlock["z"] ?? 0.0));
			$sequentialDistance = MathUtil::distance($previousBlock, $blockPos->asVector3());
			if ($interval <= (float) $this->getConstant("max-place-interval") && $sequentialDistance > (float) $this->getConstant("max-sequential-distance")) {
				$suspicious = true;
			}
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
		$this->debug($playerAPI, "horizontalDistanceSquared={$horizontalDistanceSquared}, sequentialDistance={$sequentialDistance}, interval={$interval}, buffer={$buffer}");

		if ($buffer >= (int) $this->getConstant("expansion-buffer-limit")) {
			$this->setBuffer($playerAPI, 0);
			$this->failed($playerAPI);
		}
	}

	private function getBuffer(PlayerAPI $playerAPI) : int {
		return (int) $playerAPI->getExternalData(self::BUFFER_KEY, 0);
	}

	private function setBuffer(PlayerAPI $playerAPI, int $buffer) : void {
		$playerAPI->setExternalData(self::BUFFER_KEY, $buffer);
	}
}
