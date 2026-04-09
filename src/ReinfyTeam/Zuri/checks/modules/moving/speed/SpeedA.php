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

namespace ReinfyTeam\Zuri\checks\modules\moving\speed;

use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\types\PlayerAuthInputFlags;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\checks\snapshots\MovementSnapshot;
use ReinfyTeam\Zuri\config\CacheData;
use ReinfyTeam\Zuri\config\CheckConstants;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\BlockUtil;
use ReinfyTeam\Zuri\utils\MathUtil;
use function abs;
use function is_array;
use function is_numeric;

class SpeedA extends Check {
	public function getName() : string {
		return "Speed";
	}

	public function getSubType() : string {
		return "A";
	}

	public function getCorrelationGroup() : ?string {
		return \ReinfyTeam\Zuri\checks\CrossCheckCorrelation::GROUP_MOVEMENT;
	}

	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		$player = $playerAPI->getPlayer();
		if ($packet instanceof PlayerAuthInputPacket) {
			if (
				$playerAPI->getAttackTicks() < 20 ||
				$playerAPI->getProjectileAttackTicks() < 20 ||
				$playerAPI->getTeleportTicks() < 60 ||
				$playerAPI->getBowShotTicks() < 20 ||
				$playerAPI->getHurtTicks() < 40 ||
				$playerAPI->getTeleportCommandTicks() < 40 ||
				$playerAPI->isOnAdhesion() ||
				$player->getAllowFlight() ||
				$player->getInAirTicks() > 40 ||
				$player->isFlying() ||
				$player->hasNoClientPredictions() ||
				!$player->isSurvival() ||
				$player->isCreative() ||
				$player->isSpectator() ||
				!$playerAPI->isCurrentChunkIsLoaded() ||
				$playerAPI->isRecentlyCancelledEvent()
			) {
				return;
			}

			$snapshot = new MovementSnapshot("SpeedA", $player, $playerAPI);
			$snapshot->setEnvironmentState(
				BlockUtil::isGroundSolid($player),
				$playerAPI->isCurrentChunkIsLoaded(),
				$playerAPI->isRecentlyCancelledEvent()
			);

			// Add SpeedA-specific cached data
			$snapshot->addCachedData("fromX", $player->getPosition()->getX());
			$snapshot->addCachedData("fromZ", $player->getPosition()->getZ());
			$snapshot->addCachedData("toX", $packet->getPosition()->getX());
			$snapshot->addCachedData("toZ", $packet->getPosition()->getZ());
			$snapshot->addCachedData("moveVecX", $packet->getMoveVecX());
			$snapshot->addCachedData("moveVecZ", $packet->getMoveVecZ());
			$snapshot->addCachedData("sprinting", $player->isSprinting());
			$snapshot->addCachedData("sneaking", $player->isSneaking());
			$snapshot->addCachedData("usingItem", $player->isUsingItem());
			$snapshot->addCachedData("swiftSneakLevel", $player->getArmorInventory()->getLeggings()->getEnchantmentLevel(\pocketmine\item\enchantment\VanillaEnchantments::SWIFT_SNEAK()));
			$snapshot->addCachedData("jumpTicks", $playerAPI->getJumpTicks());
			$snapshot->addCachedData("lastMoveTick", $playerAPI->getLastMoveTick());
			$snapshot->addCachedData("onIce", $playerAPI->isOnIce());
			$blockAbove = BlockUtil::getBlockAbove($player);
			$snapshot->addCachedData("blockAboveSolid", $blockAbove !== null && $blockAbove->isSolid());
			$snapshot->addCachedData("startJumping", $packet->getInputFlags()->get(PlayerAuthInputFlags::START_JUMPING));
			$snapshot->addCachedData(CacheData::SPEED_A_LAST_DISTANCE_XZ, $playerAPI->getExternalData(CacheData::SPEED_A_LAST_DISTANCE_XZ, $this->getConstant(CheckConstants::SPEEDA_XZ_DISTANCE)));
			$snapshot->addCachedData("friction", $playerAPI->isOnGround() ? $player->getWorld()->getBlock($player->getPosition()->getSide(Facing::DOWN))->getFrictionFactor() : $this->getConstant(CheckConstants::SPEEDA_FRICTION_FACTOR));
			$snapshot->addCachedData("threshold", $this->getConstant(CheckConstants::SPEEDA_THRESHOLD));
			$snapshot->addCachedData("constants", [
				"xz-distance" => $this->getConstant(CheckConstants::SPEEDA_XZ_DISTANCE),
				"jump-factor" => $this->getConstant(CheckConstants::SPEEDA_JUMP_FACTOR),
				"ground-factor" => $this->getConstant(CheckConstants::SPEEDA_GROUND_FACTOR),
				"lastjump-factor" => $this->getConstant(CheckConstants::SPEEDA_LASTJUMP_FACTOR),
				"ice-factor" => $this->getConstant(CheckConstants::SPEEDA_ICE_FACTOR),
				"knockback-factor" => $this->getConstant(CheckConstants::SPEEDA_KNOCKBACK_FACTOR),
				"lastmove-factor" => $this->getConstant(CheckConstants::SPEEDA_LASTMOVE_FACTOR),
				"speedLevel" => (($effect = $player->getEffects()->get(\pocketmine\entity\effect\VanillaEffects::SPEED())) !== null) ? $effect->getEffectLevel() : 0,
				"slownessLevel" => (($effect = $player->getEffects()->get(\pocketmine\entity\effect\VanillaEffects::SLOWNESS())) !== null) ? $effect->getEffectLevel() : 0,
			]);

			$snapshot->validate();

			// Dispatch async check with snapshot payload
			$payload = $snapshot->build();
			$this->dispatchAsyncCheck($player->getName(), $payload);
		}
	}

	/** @param array<string,mixed> $payload
	 *  @return array<string,mixed>
	 */
	public static function evaluateAsync(array $payload) : array {
		if (!MovementSnapshot::validatePayload(
			$payload,
			"SpeedA",
			MovementSnapshot::SCHEMA_VERSION,
			["type", "schemaVersion", "onGround", "motionX", "motionZ", "cachedData"],
			[
				"motionX" => [-20.0, 20.0],
				"motionZ" => [-20.0, 20.0],
			]
		)) {
			return [];
		}

		$cachedData = (array) ($payload["cachedData"] ?? []);
		$fromXRaw = $cachedData["fromX"] ?? 0;
		$fromZRaw = $cachedData["fromZ"] ?? 0;
		$toXRaw = $cachedData["toX"] ?? 0;
		$toZRaw = $cachedData["toZ"] ?? 0;
		$fromX = is_numeric($fromXRaw) ? (float) $fromXRaw : 0.0;
		$fromZ = is_numeric($fromZRaw) ? (float) $fromZRaw : 0.0;
		$toX = is_numeric($toXRaw) ? (float) $toXRaw : 0.0;
		$toZ = is_numeric($toZRaw) ? (float) $toZRaw : 0.0;
		$previous = new Vector3($fromX, 0, $fromZ);
		$next = new Vector3($toX, 0, $toZ);
		$constants = is_array($cachedData["constants"] ?? null) ? $cachedData["constants"] : [];
		$speedLevelRaw = $constants["speedLevel"] ?? 0;
		$speedLevel = is_numeric($speedLevelRaw) ? (int) $speedLevelRaw : 0;
		$slownessLevelRaw = $constants["slownessLevel"] ?? 0;
		$slownessLevel = is_numeric($slownessLevelRaw) ? (int) $slownessLevelRaw : 0;
		$friction = (float) ($cachedData["friction"] ?? 0.91);
		$lastDistance = (float) ($cachedData[CacheData::SPEED_A_LAST_DISTANCE_XZ] ?? ($constants["xz-distance"] ?? 0));
		$momentum = MathUtil::getMomentum($lastDistance, $friction);
		$movement = MathUtil::getMovementSnapshot((bool) ($cachedData["sprinting"] ?? false), (bool) ($cachedData["sneaking"] ?? false), (bool) ($cachedData["usingItem"] ?? false), (int) ($cachedData["swiftSneakLevel"] ?? 0));
		$effects = MathUtil::getEffectsMultiplierSnapshot($speedLevel, $slownessLevel);
		$acceleration = MathUtil::getAcceleration($movement, $effects, $friction, (bool) ($payload["onGround"] ?? false));
		$expected = $momentum + $acceleration;
		$expected += ((int) ($cachedData["jumpTicks"] ?? 0) < 5 && (bool) ($cachedData["blockAboveSolid"] ?? false)) ? (float) ($constants["jump-factor"] ?? 0) : 0;
		$expected += (bool) ($payload["onGround"] ?? false) ? (float) ($constants["ground-factor"] ?? 0) : 0;
		$expected += ((bool) ($cachedData["startJumping"] ?? false) && (float) ($cachedData["lastMoveTick"] ?? 0) > 5) ? (float) ($constants["lastjump-factor"] ?? 0) : 0;
		$expected += ((int) ($cachedData["jumpTicks"] ?? 0) <= 20 && (bool) ($cachedData["onIce"] ?? false)) ? (float) ($constants["ice-factor"] ?? 0) : 0;
		$motionXRaw = $payload["motionX"] ?? 0;
		$motionZRaw = $payload["motionZ"] ?? 0;
		$motionX = abs(is_numeric($motionXRaw) ? (float) $motionXRaw : 0.0);
		$motionZ = abs(is_numeric($motionZRaw) ? (float) $motionZRaw : 0.0);
		if ($motionX > 0 || $motionZ > 0) {
			$knockback = (($motionX * $motionX) + ($motionZ * $motionZ)) * (float) ($constants["knockback-factor"] ?? 0);
			$expected += $knockback;
		}
		$expected += ((float) ($cachedData["lastMoveTick"] ?? 0) < 5) ? (float) ($constants["lastmove-factor"] ?? 0) : 0;
		$dist = $previous->distance($next);
		$distDiff = abs($dist - $expected);
		$result = ["set" => [CacheData::SPEED_A_LAST_DISTANCE_XZ => $dist]];
		if ($dist > $expected && $distDiff > (float) ($cachedData["threshold"] ?? 0.0)) {
			$result["failed"] = true;
			$result["debug"] = "expected={$expected}, distance={$distDiff}";
		}
		return $result;
	}
}
