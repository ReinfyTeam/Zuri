<?php

declare(strict_types=1);

namespace ReinfyTeam\Zuri\checks\combat;

use ReinfyTeam\Zuri\config\CacheData;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Event;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\World;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\MathUtil;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function ceil;
use function floor;
use function is_array;
use function max;
use function min;
use function str_contains;
use function strtolower;

class GhostHand extends Check {
	private const string BUFFER_KEY = CacheData::GHOSTHAND_A_BUFFER;

	public function getName() : string {
		return "GhostHand";
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

		$damagerEye = $damager->getEyePos();
		$box = $victim->getBoundingBox();
		$target = new Vector3(
			max($box->minX, min($damagerEye->x, $box->maxX)),
			max($box->minY, min($damagerEye->y, $box->maxY)),
			max($box->minZ, min($damagerEye->z, $box->maxZ))
		);
		$distance = MathUtil::distanceFromComponents($damagerEye->x, $damagerEye->y, $damagerEye->z, $target->x, $target->y, $target->z);
		$minDistance = (float) $this->profileConstant("min-distance");
		if ($distance < $minDistance || $distance > (float) $this->profileConstant("max-distance")) {
			$this->setBuffer($damagerAPI, max(0, $this->getBuffer($damagerAPI) - 1));
			return;
		}

		$isBlocked = $this->hasSolidBetween(
			$damager->getWorld(),
			$damagerEye,
			$target,
			(float) $this->profileConstant("ray-step"),
			$this->getIgnoredBlockCategories()
		);
		$buffer = $this->getBuffer($damagerAPI);
		if ($isBlocked) {
			$buffer++;
		} else {
			$buffer = max(0, $buffer - 1);
		}

		$this->setBuffer($damagerAPI, $buffer);
		$this->debug($damagerAPI, "distance={$distance}, minDistance={$minDistance}, blocked=" . ($isBlocked ? "true" : "false") . ", buffer={$buffer}");

		if ($buffer >= (int) $this->profileConstant("buffer-limit")) {
			$this->setBuffer($damagerAPI, 0);
			$this->failed($damagerAPI);
		}
	}

	private function hasSolidBetween(World $world, Vector3 $from, Vector3 $to, float $step, array $ignoredCategories) : bool {
		$dx = $to->x - $from->x;
		$dy = $to->y - $from->y;
		$dz = $to->z - $from->z;
		$distance = MathUtil::distanceFromComponents($from->x, $from->y, $from->z, $to->x, $to->y, $to->z);
		$steps = (int) ceil($distance / max(0.05, $step));

		for ($i = 1; $i < $steps; $i++) {
			$ratio = $i / $steps;
			$x = (int) floor($from->x + ($dx * $ratio));
			$y = (int) floor($from->y + ($dy * $ratio));
			$z = (int) floor($from->z + ($dz * $ratio));
			$block = $world->getBlockAt($x, $y, $z);
			if (!$block->isSolid()) {
				continue;
			}

			if ($this->isIgnoredSolid(strtolower($block->getName()), $ignoredCategories)) {
				continue;
			}

			return true;
		}

		return false;
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
		$profile = strtolower((string) self::getData("zuri.tuning-presets.active", "custom"));
		if ($profile !== "low-latency" && $profile !== "high-latency") {
			return $this->getConstant($name);
		}

		return self::getData("zuri.tuning-presets.combat." . $profile . ".ghosthand." . $name, $this->getConstant($name));
	}

	private function getIgnoredBlockCategories() : array {
		$categories = $this->profileConstant("ignore-block-categories");
		if (!is_array($categories)) {
			return [];
		}

		$normalized = [];
		foreach ($categories as $category) {
			$normalized[] = strtolower((string) $category);
		}

		return $normalized;
	}

	private function isIgnoredSolid(string $blockName, array $ignoredCategories) : bool {
		foreach ($ignoredCategories as $category) {
			switch ($category) {
				case "slabs":
					if (str_contains($blockName, "slab")) {
						return true;
					}
					break;

				case "stairs":
					if (str_contains($blockName, "stair")) {
						return true;
					}
					break;

				case "walls":
					if (str_contains($blockName, "wall")) {
						return true;
					}
					break;

				case "fences":
					if (str_contains($blockName, "fence")) {
						return true;
					}
					break;

				case "gates":
					if (str_contains($blockName, "gate")) {
						return true;
					}
					break;

				case "trapdoors":
					if (str_contains($blockName, "trapdoor")) {
						return true;
					}
					break;

				case "doors":
					if (str_contains($blockName, "door")) {
						return true;
					}
					break;

				case "glass":
					if (str_contains($blockName, "glass")) {
						return true;
					}
					break;

				case "panes":
					if (str_contains($blockName, "pane")) {
						return true;
					}
					break;

				case "leaves":
					if (str_contains($blockName, "leaves")) {
						return true;
					}
					break;
			}
		}

		return false;
	}
}
