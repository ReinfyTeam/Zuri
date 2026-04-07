<?php

declare(strict_types=1);

namespace ReinfyTeam\Zuri\checks\combat;

use ReinfyTeam\Zuri\config\CacheData;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Event;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\player\Player;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\MathUtil;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function max;
use function microtime;
use function strtolower;

class ItemLerp extends Check {
	private const string BUFFER_KEY = CacheData::ITEMLERP_A_BUFFER;
	private const string LAST_SWITCH_KEY = CacheData::ITEMLERP_A_LAST_HELD_SWITCH;

	public function getName() : string {
		return "ItemLerp";
	}

	public function getSubType() : string {
		return "A";
	}

	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if (!$event instanceof PlayerItemHeldEvent) {
			return;
		}

		$playerAPI->setExternalData(self::LAST_SWITCH_KEY, microtime(true));
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function checkJustEvent(Event $event) : void {
		if (!$event instanceof EntityDamageByEntityEvent || $event->isCancelled()) {
			return;
		}

		$damager = $event->getDamager();
		$victim = $event->getEntity();
		if (!$damager instanceof Player || !$victim instanceof Player) {
			return;
		}

		$damagerAPI = PlayerAPI::getAPIPlayer($damager);
		if ($this->shouldSkip($damager, $victim, $damagerAPI)) {
			$this->setBuffer($damagerAPI, max(0, $this->getBuffer($damagerAPI) - 1));
			return;
		}

		$attackTicks = $damagerAPI->getAttackTicks();
		$cps = $damagerAPI->getCPS();
		if ($attackTicks > (float) $this->profileConstant("combat-window-ticks") || $cps < (int) $this->profileConstant("min-cps")) {
			$this->setBuffer($damagerAPI, max(0, $this->getBuffer($damagerAPI) - 1));
			return;
		}

		$lastSwitch = (float) $damagerAPI->getExternalData(self::LAST_SWITCH_KEY, 0.0);
		if ($lastSwitch <= 0.0) {
			return;
		}

		$switchTicks = MathUtil::ticksSince($lastSwitch);
		$buffer = $this->getBuffer($damagerAPI);
		if ($switchTicks <= (float) $this->profileConstant("max-switch-ticks")) {
			$buffer++;
		} else {
			$buffer = max(0, $buffer - 1);
		}

		$this->setBuffer($damagerAPI, $buffer);
		$this->debug($damagerAPI, "switchTicks={$switchTicks}, attackTicks={$attackTicks}, cps={$cps}, buffer={$buffer}");

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
			$damagerAPI->getHurtTicks() < 8 ||
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
		$profile = strtolower((string) self::getData("zuri.check.itemlerp.tuning-presets.active", "default"));
		if ($profile === "custom") {
			$profile = "default";
		}
		if ($profile !== "low-latency" && $profile !== "high-latency") {
			return $default;
		}

		return self::getData("zuri.check.itemlerp.tuning-presets." . $profile . "." . $name, $default);
	}
}
