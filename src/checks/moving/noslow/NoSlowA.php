<?php

declare(strict_types=1);

namespace ReinfyTeam\Zuri\checks\moving\noslow;

use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\Event;
use pocketmine\event\player\PlayerMoveEvent;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\MathUtil;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function max;

class NoSlowA extends Check {
	private const BUFFER_KEY = "NoSlowA.buffer";

	public function getName() : string {
		return "NoSlow";
	}

	public function getSubType() : string {
		return "A";
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if (!$event instanceof PlayerMoveEvent) {
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
			$playerAPI->isRecentlyCancelledEvent()
		) {
			$this->setBuffer($playerAPI, 0);
			return;
		}

		if (!$player->isUsingItem() || $player->getEffects()->has(VanillaEffects::SPEED())) {
			$this->setBuffer($playerAPI, max(0, $this->getBuffer($playerAPI) - 1));
			return;
		}

		if ((int) $playerAPI->getPing() > (int) $this->getConstant("max-ping")) {
			return;
		}

		$moveXZ = MathUtil::XZDistanceSquared($event->getFrom(), $event->getTo());
		$buffer = $this->getBuffer($playerAPI);
		if ($moveXZ > (float) $this->getConstant("max-xz-distance-squared")) {
			$buffer++;
		} else {
			$buffer = max(0, $buffer - 1);
		}

		$this->setBuffer($playerAPI, $buffer);
		$this->debug($playerAPI, "moveXZ={$moveXZ}, buffer={$buffer}, ping=" . (int) $playerAPI->getPing());

		if ($buffer >= (int) $this->getConstant("buffer-limit")) {
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
