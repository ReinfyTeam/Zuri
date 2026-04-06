<?php

declare(strict_types=1);

namespace ReinfyTeam\Zuri\checks\combat\velocity;

use ReinfyTeam\Zuri\config\CacheData;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Event;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\player\Player;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\MathUtil;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function max;
use function microtime;

class VelocityA extends Check {
	private const HIT_AT_KEY = CacheData::VELOCITY_A_HIT_AT;
	private const BUFFER_KEY = CacheData::VELOCITY_A_BUFFER;

	public function getName() : string {
		return "Velocity";
	}

	public function getSubType() : string {
		return "A";
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function checkJustEvent(Event $event) : void {
		if (!$event instanceof EntityDamageByEntityEvent || $event->isCancelled()) {
			return;
		}

		$victim = $event->getEntity();
		if (!$victim instanceof Player) {
			return;
		}

		$victimAPI = PlayerAPI::getAPIPlayer($victim);
		$victimAPI->setExternalData(self::HIT_AT_KEY, microtime(true));
		$victimAPI->setExternalData(self::BUFFER_KEY, 0);
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if (!$event instanceof PlayerMoveEvent) {
			return;
		}

		$hitAt = $playerAPI->getExternalData(self::HIT_AT_KEY);
		if (!is_float($hitAt) && !is_int($hitAt)) {
			return;
		}

		$elapsedTicks = MathUtil::ticksSince((float) $hitAt);
		if ($elapsedTicks > (float) $this->getConstant("max-observe-ticks")) {
			$this->resetState($playerAPI);
			return;
		}

		if ($elapsedTicks < (float) $this->getConstant("start-observe-ticks")) {
			return;
		}

		$player = $playerAPI->getPlayer();
		if (
			!$player->isSurvival() ||
			!$playerAPI->isCurrentChunkIsLoaded() ||
			$player->getAllowFlight() ||
			$player->isFlying() ||
			$player->hasNoClientPredictions() ||
			$playerAPI->isInLiquid() ||
			$playerAPI->isOnIce() ||
			$playerAPI->isOnStairs() ||
			$playerAPI->isOnAdhesion() ||
			$playerAPI->isInWeb() ||
			$playerAPI->isInBoundingBox() ||
			$playerAPI->getTeleportTicks() < 20 ||
			$playerAPI->isRecentlyCancelledEvent() ||
			(int) $playerAPI->getPing() > (int) $this->getConstant("max-ping")
		) {
			$this->resetState($playerAPI);
			return;
		}

		$moveXZ = MathUtil::XZDistanceSquared($event->getFrom(), $event->getTo());
		$buffer = (int) $playerAPI->getExternalData(self::BUFFER_KEY, 0);
		if ($moveXZ < (float) $this->getConstant("min-response-distance-squared")) {
			$buffer++;
		} else {
			$buffer = max(0, $buffer - 1);
		}

		$playerAPI->setExternalData(self::BUFFER_KEY, $buffer);
		$this->debug($playerAPI, "elapsedTicks={$elapsedTicks}, moveXZ={$moveXZ}, buffer={$buffer}");

		if ($buffer >= (int) $this->getConstant("buffer-limit")) {
			$this->resetState($playerAPI);
			$this->failed($playerAPI);
		}
	}

	private function resetState(PlayerAPI $playerAPI) : void {
		$playerAPI->unsetExternalData(self::HIT_AT_KEY);
		$playerAPI->setExternalData(self::BUFFER_KEY, 0);
	}
}
