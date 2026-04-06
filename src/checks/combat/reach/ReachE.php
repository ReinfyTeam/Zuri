<?php

declare(strict_types=1);

namespace ReinfyTeam\Zuri\checks\combat\reach;

use ReinfyTeam\Zuri\config\CacheData;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Event;
use pocketmine\player\Player;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\MathUtil;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function max;
use function min;

class ReachE extends Check {
	private const string BUFFER_KEY = CacheData::REACH_E_BUFFER;

	public function getName() : string {
		return "Reach";
	}

	public function getSubType() : string {
		return "E";
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function checkJustEvent(Event $event) : void {
		if (!$event instanceof EntityDamageByEntityEvent || $event->isCancelled()) {
			return;
		}

		$victim = $event->getEntity();
		$damager = $event->getDamager();
		if (!$victim instanceof Player || !$damager instanceof Player) {
			return;
		}

		$damagerAPI = PlayerAPI::getAPIPlayer($damager);
		$victimAPI = PlayerAPI::getAPIPlayer($victim);
		if ($this->shouldSkip($damager, $victim, $damagerAPI, $victimAPI)) {
			$this->setBuffer($damagerAPI, max(0, $this->getBuffer($damagerAPI) - 1));
			return;
		}

		$eye = $damager->getEyePos();
		$box = $victim->getBoundingBox();
		$closestX = max($box->minX, min($eye->x, $box->maxX));
		$closestY = max($box->minY, min($eye->y, $box->maxY));
		$closestZ = max($box->minZ, min($eye->z, $box->maxZ));

		$distance = MathUtil::distanceFromComponents(
			$eye->x,
			$eye->y,
			$eye->z,
			$closestX,
			$closestY,
			$closestZ
		);
		$distance -= (int) $damagerAPI->getPing() * (float) $this->getConstant("edge-ping-compensation");
		$limit = (float) $this->getConstant("edge-reach-limit");

		$buffer = $this->getBuffer($damagerAPI);
		if ($distance > $limit) {
			$buffer++;
		} else {
			$buffer = max(0, $buffer - 1);
		}

		$this->setBuffer($damagerAPI, $buffer);
		$this->debug($damagerAPI, "distance={$distance}, limit={$limit}, buffer={$buffer}");

		if ($buffer >= (int) $this->getConstant("edge-buffer-limit")) {
			$this->setBuffer($damagerAPI, 0);
			$this->failed($damagerAPI);
		}
	}

	private function shouldSkip(Player $damager, Player $victim, PlayerAPI $damagerAPI, PlayerAPI $victimAPI) : bool {
		return
			!$damager->isSurvival() ||
			!$victim->isSurvival() ||
			(int) $damagerAPI->getPing() > (int) $this->getConstant("edge-max-ping") ||
			$damagerAPI->isRecentlyCancelledEvent() ||
			$victimAPI->isRecentlyCancelledEvent() ||
			$damagerAPI->getTeleportTicks() < (float) $this->getConstant("edge-min-teleport-ticks") ||
			$victimAPI->getProjectileAttackTicks() < (float) $this->getConstant("edge-min-stability-ticks") ||
			$damagerAPI->getProjectileAttackTicks() < (float) $this->getConstant("edge-min-stability-ticks") ||
			$victimAPI->getBowShotTicks() < (float) $this->getConstant("edge-min-stability-ticks") ||
			$damagerAPI->getBowShotTicks() < (float) $this->getConstant("edge-min-stability-ticks");
	}

	private function getBuffer(PlayerAPI $playerAPI) : int {
		return (int) $playerAPI->getExternalData(self::BUFFER_KEY, 0);
	}

	private function setBuffer(PlayerAPI $playerAPI, int $buffer) : void {
		$playerAPI->setExternalData(self::BUFFER_KEY, $buffer);
	}
}