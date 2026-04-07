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

class SpeedA extends Check {
	public function getName() : string {
		return "Speed";
	}

	public function getSubType() : string {
		return "A";
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
			$snapshot->addCachedData("blockAboveSolid", BlockUtil::getBlockAbove($player)->isSolid());
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

			// Dispatch async check with snapshot payload
			$payload = $snapshot->build();
			$this->dispatchAsyncCheck($player->getName(), $payload);
		}
	}

	public static function evaluateAsync(array $payload) : array {
		if (($payload["type"] ?? null) !== "SpeedA") {
			return [];
		}

		$cachedData = (array) ($payload["cachedData"] ?? []);
		$previous = new Vector3((float) ($cachedData["fromX"] ?? 0), 0, (float) ($cachedData["fromZ"] ?? 0));
		$next = new Vector3((float) ($cachedData["toX"] ?? 0), 0, (float) ($cachedData["toZ"] ?? 0));
		$constants = $cachedData["constants"] ?? [];
		$friction = (float) ($cachedData["friction"] ?? 0.91);
		$lastDistance = (float) ($cachedData[CacheData::SPEED_A_LAST_DISTANCE_XZ] ?? ($constants["xz-distance"] ?? 0));
		$momentum = MathUtil::getMomentum($lastDistance, $friction);
		$movement = MathUtil::getMovementSnapshot((bool) ($cachedData["sprinting"] ?? false), (bool) ($cachedData["sneaking"] ?? false), (bool) ($cachedData["usingItem"] ?? false), (int) ($cachedData["swiftSneakLevel"] ?? 0));
		$effects = MathUtil::getEffectsMultiplierSnapshot((int) ($constants["speedLevel"] ?? 0), (int) ($constants["slownessLevel"] ?? 0));
		$acceleration = MathUtil::getAcceleration($movement, $effects, $friction, (bool) ($payload["onGround"] ?? false));
		$expected = $momentum + $acceleration;
		$expected += ((int) ($cachedData["jumpTicks"] ?? 0) < 5 && (bool) ($cachedData["blockAboveSolid"] ?? false)) ? (float) ($constants["jump-factor"] ?? 0) : 0;
		$expected += (bool) ($payload["onGround"] ?? false) ? (float) ($constants["ground-factor"] ?? 0) : 0;
		$expected += ((bool) ($cachedData["startJumping"] ?? false) && (float) ($cachedData["lastMoveTick"] ?? 0) > 5) ? (float) ($constants["lastjump-factor"] ?? 0) : 0;
		$expected += ((int) ($cachedData["jumpTicks"] ?? 0) <= 20 && (bool) ($cachedData["onIce"] ?? false)) ? (float) ($constants["ice-factor"] ?? 0) : 0;
		$motionX = abs((float) ($payload["motionX"] ?? 0));
		$motionZ = abs((float) ($payload["motionZ"] ?? 0));
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
