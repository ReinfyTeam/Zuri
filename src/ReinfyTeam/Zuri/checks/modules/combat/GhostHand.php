<?php

/*
 *
 *  ____           _            __           _____
 * |  _ \    ___  (_)  _ __    / _|  _   _  |_   _|   ___    __ _   _ __ ___
 * | |_) |  / _ \ | | | '_ \  | |_  | | | |   | |    / _ \  / _` | | '_ ` _ \
 * |  _ <  |  __/ | | | | | | |  _| | |_| |   | |   |  __/ | (_| | | | | | | |
 * |_| \_\  \___| |_| |_| |_| |_|    \__, |   |_|    \___|  \__,_| |_| |_| |_|
 *                                   |___/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Zuri attempts to enforce "vanilla Minecraft" mechanics, as well as preventing
 * players from abusing weaknesses in Minecraft or its protocol, making your server
 * more safe. Organized in different sections, various checks are performed to test
 * players doing, covering a wide range including flying and speeding, fighting
 * hacks, fast block breaking and nukers, inventory hacks, chat spam and other types
 * of malicious behaviour.
 *
 * @author ReinfyTeam
 * @link https://github.com/ReinfyTeam/
 *
 *
 */

declare(strict_types=1);

namespace ReinfyTeam\Zuri\checks\modules\combat;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Event;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\World;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\config\CacheData;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use ReinfyTeam\Zuri\utils\MathUtil;
use function ceil;
use function floor;
use function is_array;
use function is_numeric;
use function is_string;
use function max;
use function min;
use function str_contains;
use function strtolower;

class GhostHand extends Check {
	private const TYPE = "GhostHandA";
	private const BUFFER_KEY = CacheData::GHOSTHAND_A_BUFFER;

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
		$minDistance = $this->profileFloatConstant("min-distance", 0.0);
		$maxDistance = $this->profileFloatConstant("max-distance", 0.0);
		if ($distance < $minDistance || $distance > $maxDistance) {
			$this->setBuffer($damagerAPI, max(0, $this->getBuffer($damagerAPI) - 1));
			return;
		}

		$isBlocked = $this->hasSolidBetween(
			$damager->getWorld(),
			$damagerEye,
			$target,
			$this->profileFloatConstant("ray-step", 0.2),
			$this->getIgnoredBlockCategories()
		);
		$this->dispatchAsyncCheck($damager->getName(), [
			"type" => self::TYPE,
			"buffer" => $this->getBuffer($damagerAPI),
			"isBlocked" => $isBlocked,
			"distance" => $distance,
			"minDistance" => $minDistance,
			"bufferLimit" => $this->profileIntConstant("buffer-limit", 3),
		]);
	}

	public static function evaluateAsync(array $payload) : array {
		if (($payload["type"] ?? null) !== self::TYPE) {
			return [];
		}

		$bufferRaw = $payload["buffer"] ?? 0;
		$buffer = is_numeric($bufferRaw) ? (int) $bufferRaw : 0;
		if ((bool) ($payload["isBlocked"] ?? false)) {
			$buffer++;
		} else {
			$buffer = max(0, $buffer - 1);
		}
		$distanceRaw = $payload["distance"] ?? 0.0;
		$minDistanceRaw = $payload["minDistance"] ?? 0.0;
		$distance = is_numeric($distanceRaw) ? (float) $distanceRaw : 0.0;
		$minDistance = is_numeric($minDistanceRaw) ? (float) $minDistanceRaw : 0.0;

		$result = [
			"set" => [self::BUFFER_KEY => $buffer],
			"debug" => "distance={$distance}, minDistance={$minDistance}, blocked=" . ((bool) ($payload["isBlocked"] ?? false) ? "true" : "false") . ", buffer={$buffer}",
		];

		$bufferLimitRaw = $payload["bufferLimit"] ?? 0;
		$bufferLimit = is_numeric($bufferLimitRaw) ? (int) $bufferLimitRaw : 0;
		if ($buffer >= $bufferLimit) {
			$result["set"][self::BUFFER_KEY] = 0;
			$result["failed"] = true;
		}

		return $result;
	}

	/** @param list<string> $ignoredCategories */
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
			$damagerAPI->getTeleportTicks() < $this->profileFloatConstant("min-teleport-ticks", 0.0) ||
			$damagerAPI->getHurtTicks() < 8 ||
			(int) $damagerAPI->getPing() > $this->profileIntConstant("max-ping", 0);
	}

	private function getBuffer(PlayerAPI $playerAPI) : int {
		$raw = $playerAPI->getExternalData(self::BUFFER_KEY, 0);
		return is_numeric($raw) ? (int) $raw : 0;
	}

	private function setBuffer(PlayerAPI $playerAPI, int $buffer) : void {
		$playerAPI->setExternalData(self::BUFFER_KEY, $buffer);
	}

	private function profileConstant(string $name) : mixed {
		$default = $this->getConstant($name);
		$profileRaw = self::getData("zuri.check.ghosthand.tuning-presets.active", "default");
		$profile = strtolower(is_string($profileRaw) ? $profileRaw : "default");
		if ($profile === "custom") {
			$profile = "default";
		}
		if ($profile !== "low-latency" && $profile !== "high-latency") {
			return $default;
		}

		return self::getData("zuri.check.ghosthand.tuning-presets." . $profile . "." . $name, $default);
	}

	private function profileFloatConstant(string $name, float $default) : float {
		$raw = $this->profileConstant($name);
		return is_numeric($raw) ? (float) $raw : $default;
	}

	private function profileIntConstant(string $name, int $default) : int {
		$raw = $this->profileConstant($name);
		return is_numeric($raw) ? (int) $raw : $default;
	}

	/** @return list<string> */
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

	/** @param list<string> $ignoredCategories */
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
