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

namespace ReinfyTeam\Zuri\checks\modules\combat\killaura;

use pocketmine\block\BlockTypeIds;
use pocketmine\event\Event;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemOnEntityTransactionData;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\config\CheckConstants;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use ReinfyTeam\Zuri\utils\MathUtil;
use function count;
use function is_array;
use function is_numeric;
use function sqrt;

/**
 * Detects aura-like attacks against entities outside line-of-sight context.
 */
class KillAuraC extends Check {
	private bool $interact = false;

	/**
	 * Returns the check name.
	 *
	 * @return string Check identifier.
	 */
	public function getName() : string {
		return "KillAura";
	}

	/**
	 * Returns the check subtype.
	 *
	 * @return string Check subtype identifier.
	 */
	public function getSubType() : string {
		return "C";
	}

	/**
	 * Tracks interaction events used as an exclusion signal.
	 *
	 * @param Event $event Triggered event.
	 * @param PlayerAPI $playerAPI Player context.
	 */
	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if ($event instanceof PlayerInteractEvent) {
			$this->interact = true;
		}
	}

	/**
	 * Processes inventory transaction packets for KillAura C evaluation.
	 *
	 * @param DataPacket $packet Incoming packet.
	 * @param PlayerAPI $playerAPI Player context.
	 * @throws DiscordWebhookException
	 */
	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		if ($playerAPI->getAttackTicks() > 40 || $this->interact) {
			return;
		}

		$player = $playerAPI->getPlayer();

		if (
			$playerAPI->getProjectileAttackTicks() < 40 ||
			$playerAPI->getBowShotTicks() < 40 ||
			$playerAPI->isRecentlyCancelledEvent()
		) { // false-positive in projectiles
			return;
		}
		if ($packet instanceof InventoryTransactionPacket) {
			if ($packet->trData instanceof UseItemOnEntityTransactionData) {
				$delta = MathUtil::getDeltaDirectionVector($playerAPI, 3);
				$maxDistanceRaw = $this->getConstant(CheckConstants::KILLAURAC_MAX_DISTANCE);
				$suspiciousPitchRaw = $this->getConstant(CheckConstants::KILLAURAC_SUSPECIOUS_PITCH);
				$suspiciousCountRaw = $this->getConstant(CheckConstants::KILLAURAC_SUSPECIOUS_COUNT);
				$targetBlock = $player->getTargetBlock(10);
				$entities = [];
				foreach ($player->getWorld()->getEntities() as $target) {
					$entities[] = [
						"id" => $target->getId(),
						"x" => $target->getPosition()->getX(),
						"y" => $target->getPosition()->getY(),
						"z" => $target->getPosition()->getZ(),
					];
				}
				$this->dispatchAsyncCheck($player->getName(), [
					"checkName" => $this->getName(),
					"checkSubType" => $this->getSubType(),
					"playerId" => $player->getId(),
					"pitch" => $player->getLocation()->getPitch(),
					"locX" => $player->getLocation()->getX(),
					"locY" => $player->getLocation()->getY(),
					"locZ" => $player->getLocation()->getZ(),
					"eyeHeight" => $player->getEyeHeight(),
					"deltaX" => $delta->getX(),
					"deltaY" => $delta->getY(),
					"deltaZ" => $delta->getZ(),
					"maxDistance" => is_numeric($maxDistanceRaw) ? (float) $maxDistanceRaw : 0.0,
					"suspiciousPitch" => is_numeric($suspiciousPitchRaw) ? (float) $suspiciousPitchRaw : 0.0,
					"suspiciousCount" => is_numeric($suspiciousCountRaw) ? (int) $suspiciousCountRaw : 0,
					"targetBlockAir" => $targetBlock === null || $targetBlock->getTypeId() === BlockTypeIds::AIR,
					"entities" => $entities,
				]);
			}
		}
	}

	/**
	 * Evaluates an async payload for KillAura C violations.
	 *
	 * @param array<string,mixed> $payload Serialized check payload.
	 * @return array<string,mixed> Async decision data.
	 */
	public static function evaluateAsync(array $payload) : array {
    // Thread-safe: execute in async worker thread only; use only $payload (no Player objects)
    if (\pocketmine\thread\Thread::getCurrentThreadId() === 0) {
        throw new \RuntimeException("evaluateAsync must not be called on the main thread");
    }
		if (($payload["checkName"] ?? null) !== "KillAura" || ($payload["checkSubType"] ?? null) !== "C") {
			return [];
		}

		$pitchRaw = $payload["pitch"] ?? 0;
		$suspiciousPitchRaw = $payload["suspiciousPitch"] ?? 0;
		$pitch = is_numeric($pitchRaw) ? (float) $pitchRaw : 0.0;
		$suspiciousPitch = is_numeric($suspiciousPitchRaw) ? (float) $suspiciousPitchRaw : 0.0;
		if ($pitch >= $suspiciousPitch) {
			return [];
		}

		$locXRaw = $payload["locX"] ?? 0;
		$locYRaw = $payload["locY"] ?? 0;
		$locZRaw = $payload["locZ"] ?? 0;
		$eyeHeightRaw = $payload["eyeHeight"] ?? 0;
		$deltaXRaw = $payload["deltaX"] ?? 0;
		$deltaYRaw = $payload["deltaY"] ?? 0;
		$deltaZRaw = $payload["deltaZ"] ?? 0;
		$locX = is_numeric($locXRaw) ? (float) $locXRaw : 0.0;
		$locY = is_numeric($locYRaw) ? (float) $locYRaw : 0.0;
		$locZ = is_numeric($locZRaw) ? (float) $locZRaw : 0.0;
		$eyeHeight = is_numeric($eyeHeightRaw) ? (float) $eyeHeightRaw : 0.0;
		$deltaX = is_numeric($deltaXRaw) ? (float) $deltaXRaw : 0.0;
		$deltaY = is_numeric($deltaYRaw) ? (float) $deltaYRaw : 0.0;
		$deltaZ = is_numeric($deltaZRaw) ? (float) $deltaZRaw : 0.0;
		$fromX = $locX;
		$fromY = $locY + $eyeHeight;
		$fromZ = $locZ;
		$stepX = $deltaX;
		$stepY = $deltaY + $eyeHeight;
		$stepZ = $deltaZ;
		$distance = sqrt(($stepX ** 2) + ($stepY ** 2) + ($stepZ ** 2));
		if ($distance > 0.0) {
			$stepX /= $distance;
			$stepY /= $distance;
			$stepZ /= $distance;
		} else {
			$stepX = 0.0;
			$stepY = 0.0;
			$stepZ = 0.0;
		}
		$maxDistanceRaw = $payload["maxDistance"] ?? 0;
		$maxDistance = is_numeric($maxDistanceRaw) ? (float) $maxDistanceRaw : 0.0;
		$entitiesPayload = $payload["entities"] ?? [];
		$entitiesList = is_array($entitiesPayload) ? $entitiesPayload : [];
		$entities = [];
		for ($i = 0; $i <= $distance; ++$i) {
			$fromX += $stepX;
			$fromY += $stepY;
			$fromZ += $stepZ;
			foreach ($entitiesList as $target) {
				if (!is_array($target)) {
					continue;
				}
				$targetXRaw = $target["x"] ?? null;
				$targetYRaw = $target["y"] ?? null;
				$targetZRaw = $target["z"] ?? null;
				$targetIdRaw = $target["id"] ?? null;
				if (!is_numeric($targetXRaw) || !is_numeric($targetYRaw) || !is_numeric($targetZRaw) || !is_numeric($targetIdRaw)) {
					continue;
				}
				$targetX = (float) $targetXRaw;
				$targetY = (float) $targetYRaw;
				$targetZ = (float) $targetZRaw;
				$targetId = (int) $targetIdRaw;
				if (sqrt((($targetX - $fromX) ** 2) + (($targetY - $fromY) ** 2) + (($targetZ - $fromZ) ** 2)) <= $maxDistance) {
					$entities[$targetId] = true;
				}
			}
		}

		$debug = "distance={$distance}, entities=" . count($entities);
		$suspiciousCountRaw = $payload["suspiciousCount"] ?? 0;
		$suspiciousCount = is_numeric($suspiciousCountRaw) ? (int) $suspiciousCountRaw : 0;
		if (count($entities) < $suspiciousCount && !(bool) ($payload["targetBlockAir"] ?? true)) {
			return ["failed" => true, "debug" => $debug];
		}

		return ["debug" => $debug];
	}
}

