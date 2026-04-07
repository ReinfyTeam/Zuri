<?php

declare(strict_types=1);

namespace ReinfyTeam\Zuri\checks\combat;

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
use function strtolower;

class Hitbox extends Check {
	private const string BUFFER_KEY = CacheData::HITBOX_A_BUFFER;

	public function getName() : string {
		return "Hitbox";
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
		$damager = $event->getDamager();
		if (!$victim instanceof Player || !$damager instanceof Player) {
			return;
		}

		$damagerAPI = PlayerAPI::getAPIPlayer($damager);
		if ($this->shouldSkip($damager, $victim, $damagerAPI)) {
			$this->setBuffer($damagerAPI, max(0, $this->getBuffer($damagerAPI) - 1));
			return;
		}

		$attackTicks = $damagerAPI->getAttackTicks();
		if ($attackTicks > (float) $this->profileConstant("combat-window-ticks")) {
			$this->setBuffer($damagerAPI, max(0, $this->getBuffer($damagerAPI) - 1));
			return;
		}

		$eye = $damager->getEyePos();
		$box = $victim->getBoundingBox();
		$distance = MathUtil::distanceFromComponents($eye->x, $eye->y, $eye->z, $victim->getEyePos()->x, $victim->getEyePos()->y, $victim->getEyePos()->z);
		if ($distance < (float) $this->profileConstant("min-distance") || $distance > (float) $this->profileConstant("max-distance")) {
			return;
		}

		$closestX = max($box->minX, min($eye->x, $box->maxX));
		$closestY = max($box->minY, min($eye->y, $box->maxY));
		$closestZ = max($box->minZ, min($eye->z, $box->maxZ));

		$toX = $closestX - $eye->x;
		$toY = $closestY - $eye->y;
		$toZ = $closestZ - $eye->z;
		$toLength = MathUtil::distanceFromComponents(0.0, 0.0, 0.0, $toX, $toY, $toZ);
		if ($toLength < 0.0001) {
			return;
		}

		$dir = $damager->getDirectionVector();
		$alignment = $dir->x * ($toX / $toLength) + $dir->y * ($toY / $toLength) + $dir->z * ($toZ / $toLength);
		$projection = ($toX * $dir->x) + ($toY * $dir->y) + ($toZ * $dir->z);
		$rayX = $eye->x + ($dir->x * $projection);
		$rayY = $eye->y + ($dir->y * $projection);
		$rayZ = $eye->z + ($dir->z * $projection);
		$missDistance = MathUtil::distanceFromComponents($rayX, $rayY, $rayZ, $closestX, $closestY, $closestZ);
		$ping = (int) $damagerAPI->getPing();
		$minDot = (float) $this->profileConstant("min-dot") - min(0.18, $ping * (float) $this->profileConstant("dot-ping-compensation"));
		$missLimit = (float) $this->profileConstant("max-miss-distance") + min(0.45, $ping * (float) $this->profileConstant("miss-ping-compensation"));

		$suspicious = $alignment < $minDot || $missDistance > $missLimit;
		$buffer = $this->getBuffer($damagerAPI);
		if ($suspicious) {
			$buffer++;
		} else {
			$buffer = max(0, $buffer - 1);
		}

		$this->setBuffer($damagerAPI, $buffer);
		$this->debug($damagerAPI, "alignment={$alignment}, missDistance={$missDistance}, minDot={$minDot}, missLimit={$missLimit}, attackTicks={$attackTicks}, ping={$ping}, buffer={$buffer}");
		if ($buffer >= (int) $this->profileConstant("buffer-limit")) {
			$this->setBuffer($damagerAPI, 0);
			$this->failed($damagerAPI);
		}
	}

	private function shouldSkip(Player $damager, Player $victim, PlayerAPI $damagerAPI) : bool {
		return
			!$damager->isSurvival() ||
			!$victim->isSurvival() ||
			$damagerAPI->isRecentlyCancelledEvent() ||
			$damagerAPI->getTeleportTicks() < (float) $this->profileConstant("min-teleport-ticks") ||
			(int) $damagerAPI->getPing() > (int) $this->profileConstant("max-ping");
	}

	private function getBuffer(PlayerAPI $playerAPI) : int {
		return (int) $playerAPI->getExternalData(self::BUFFER_KEY, 0);
	}

	private function setBuffer(PlayerAPI $playerAPI, int $buffer) : void {
		$playerAPI->setExternalData(self::BUFFER_KEY, $buffer);
	}

	private function profileConstant(string $name) : mixed {
		$default = $this->getConstant($name);
		$profile = strtolower((string) self::getData("zuri.check.hitbox.tuning-presets.active", "default"));
		if ($profile === "custom") {
			$profile = "default";
		}
		if ($profile !== "low-latency" && $profile !== "high-latency") {
			return $default;
		}

		return self::getData("zuri.check.hitbox.tuning-presets." . $profile . "." . $name, $default);
	}
}
