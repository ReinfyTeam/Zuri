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

namespace ReinfyTeam\Zuri\checks\moving\speed;

use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\types\PlayerAuthInputFlags;
use ReinfyTeam\Zuri\checks\Check;
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

			$this->dispatchAsyncCheck($player->getName(), [
				"type" => "SpeedA",
				"fromX" => $player->getPosition()->getX(),
				"fromZ" => $player->getPosition()->getZ(),
				"toX" => $packet->getPosition()->getX(),
				"toZ" => $packet->getPosition()->getZ(),
				"moveVecX" => $packet->getMoveVecX(),
				"moveVecZ" => $packet->getMoveVecZ(),
				"sprinting" => $player->isSprinting(),
				"sneaking" => $player->isSneaking(),
				"usingItem" => $player->isUsingItem(),
				"swiftSneakLevel" => $player->getArmorInventory()->getLeggings()->getEnchantmentLevel(\pocketmine\item\enchantment\VanillaEnchantments::SWIFT_SNEAK()),
				"onGround" => $player->isOnGround(),
				"jumpTicks" => $playerAPI->getJumpTicks(),
				"lastMoveTick" => $playerAPI->getLastMoveTick(),
				"onIce" => $playerAPI->isOnIce(),
				"blockAboveSolid" => BlockUtil::getBlockAbove($player)->isSolid(),
				"startJumping" => $packet->getInputFlags()->get(PlayerAuthInputFlags::START_JUMPING),
				CacheData::SPEED_A_LAST_DISTANCE_XZ => $playerAPI->getExternalData(CacheData::SPEED_A_LAST_DISTANCE_XZ, $this->getConstant(CheckConstants::SPEEDA_XZ_DISTANCE)),
				"motionX" => $playerAPI->getMotion()->getX(),
				"motionZ" => $playerAPI->getMotion()->getZ(),
				"friction" => $playerAPI->isOnGround() ? $player->getWorld()->getBlock($player->getPosition()->getSide(Facing::DOWN))->getFrictionFactor() : $this->getConstant(CheckConstants::SPEEDA_FRICTION_FACTOR),
				"threshold" => $this->getConstant(CheckConstants::SPEEDA_THRESHOLD),
				"constants" => [
					"xz-distance" => $this->getConstant(CheckConstants::SPEEDA_XZ_DISTANCE),
					"jump-factor" => $this->getConstant(CheckConstants::SPEEDA_JUMP_FACTOR),
					"ground-factor" => $this->getConstant(CheckConstants::SPEEDA_GROUND_FACTOR),
					"lastjump-factor" => $this->getConstant(CheckConstants::SPEEDA_LASTJUMP_FACTOR),
					"ice-factor" => $this->getConstant(CheckConstants::SPEEDA_ICE_FACTOR),
					"knockback-factor" => $this->getConstant(CheckConstants::SPEEDA_KNOCKBACK_FACTOR),
					"lastmove-factor" => $this->getConstant(CheckConstants::SPEEDA_LASTMOVE_FACTOR),
					"speedLevel" => (($effect = $player->getEffects()->get(\pocketmine\entity\effect\VanillaEffects::SPEED())) !== null) ? $effect->getEffectLevel() : 0,
					"slownessLevel" => (($effect = $player->getEffects()->get(\pocketmine\entity\effect\VanillaEffects::SLOWNESS())) !== null) ? $effect->getEffectLevel() : 0,
				],
			]);
		}
	}

	public static function evaluateAsync(array $payload) : array {
		if (($payload["type"] ?? null) !== "SpeedA") {
			return [];
		}

		$previous = new Vector3((float) ($payload["fromX"] ?? 0), 0, (float) ($payload["fromZ"] ?? 0));
		$next = new Vector3((float) ($payload["toX"] ?? 0), 0, (float) ($payload["toZ"] ?? 0));
		$constants = $payload["constants"] ?? [];
		$friction = (float) ($payload["friction"] ?? 0.91);
		$lastDistance = (float) ($payload[CacheData::SPEED_A_LAST_DISTANCE_XZ] ?? ($constants["xz-distance"] ?? 0));
		$momentum = MathUtil::getMomentum($lastDistance, $friction);
		$movement = MathUtil::getMovementSnapshot((bool) ($payload["sprinting"] ?? false), (bool) ($payload["sneaking"] ?? false), (bool) ($payload["usingItem"] ?? false), (int) ($payload["swiftSneakLevel"] ?? 0));
		$effects = MathUtil::getEffectsMultiplierSnapshot((int) ($constants["speedLevel"] ?? 0), (int) ($constants["slownessLevel"] ?? 0));
		$acceleration = MathUtil::getAcceleration($movement, $effects, $friction, (bool) ($payload["onGround"] ?? false));
		$expected = $momentum + $acceleration;
		$expected += ((int) ($payload["jumpTicks"] ?? 0) < 5 && (bool) ($payload["blockAboveSolid"] ?? false)) ? (float) ($constants["jump-factor"] ?? 0) : 0;
		$expected += (bool) ($payload["onGround"] ?? false) ? (float) ($constants["ground-factor"] ?? 0) : 0;
		$expected += ((bool) ($payload["startJumping"] ?? false) && (float) ($payload["lastMoveTick"] ?? 0) > 5) ? (float) ($constants["lastjump-factor"] ?? 0) : 0;
		$expected += ((int) ($payload["jumpTicks"] ?? 0) <= 20 && (bool) ($payload["onIce"] ?? false)) ? (float) ($constants["ice-factor"] ?? 0) : 0;
		$motionX = abs((float) ($payload["motionX"] ?? 0));
		$motionZ = abs((float) ($payload["motionZ"] ?? 0));
		if ($motionX > 0 || $motionZ > 0) {
			$knockback = (($motionX * $motionX) + ($motionZ * $motionZ)) * (float) ($constants["knockback-factor"] ?? 0);
			$expected += $knockback;
		}
		$expected += ((float) ($payload["lastMoveTick"] ?? 0) < 5) ? (float) ($constants["lastmove-factor"] ?? 0) : 0;
		$dist = $previous->distance($next);
		$distDiff = abs($dist - $expected);
		$result = ["set" => [CacheData::SPEED_A_LAST_DISTANCE_XZ => $dist]];
		if ($dist > $expected && $distDiff > (float) ($payload["threshold"] ?? 0.0)) {
			$result["failed"] = true;
			$result["debug"] = "expected={$expected}, distance={$distDiff}";
		}
		return $result;
	}
}
