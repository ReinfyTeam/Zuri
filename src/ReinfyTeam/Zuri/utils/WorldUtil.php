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


namespace ReinfyTeam\Zuri\utils;

use pocketmine\math\AxisAlignedBB;
use pocketmine\entity\Location;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\World;
use function max;
use function min;

/**
 * Utility helpers related to world/terrain checks.
 */
final class WorldUtil {
	public const COLLISION_QUERY_EPSILON = 0.001;
	public const SAMPLE_INSET_RATIO = 0.1;
	public const BLOCK_HIT_EPSILON_SQUARED = 0.0001;

	/**
	 * Checks whether the player's current chunk is loaded.
	 *
	 * @param \pocketmine\player\Player $player The player to check.
	 * @return bool True if the chunk is loaded, false otherwise.
	 */
	public static function isCurrentChunkIsLoaded(Player $player) : bool {
		return $player->getWorld()->isInLoadedTerrain($player->getLocation());
	}

	/**
	 * Collects the collision boxes of blocks in the specified area.
	 *
	 * @param World $world The world to query.
	 * @param Vector3 $start The starting position.
	 * @param AxisAlignedBB $boundingBox The bounding box to check.
	 * @param float $hitboxTolerance The tolerance for the hitbox.
	 * @return array<AxisAlignedBB> The list of collision boxes.
	 */
	public static function collectBlockCollisionBoxes(World $world, Vector3 $start, AxisAlignedBB $boundingBox, float $hitboxTolerance = 0.08) : array {
		$targetBox = MathUtil::expandedCopyAABB($boundingBox, $hitboxTolerance);
		$epsilon = self::COLLISION_QUERY_EPSILON;
		$queryBox = new AxisAlignedBB(
			min($start->x, $targetBox->minX) - $epsilon,
			min($start->y, $targetBox->minY) - $epsilon,
			min($start->z, $targetBox->minZ) - $epsilon,
			max($start->x, $targetBox->maxX) + $epsilon,
			max($start->y, $targetBox->maxY) + $epsilon,
			max($start->z, $targetBox->maxZ) + $epsilon
		);

		return $world->getBlockCollisionBoxes($queryBox);
	}

	/**
	 * Check if there is a clear line of sight from the start position to any point within the target bounding box.
	 * @param array<AxisAlignedBB> $collisionBoxes The list of collision boxes to check against.
	 * @param Vector3 $start The starting position (e.g., player's eye position).
	 * @param AxisAlignedBB $box The target bounding box to check for line of sight.
	 */
	public static function hasLineOfSightToAnyPoint(array $collisionBoxes, Vector3 $start, AxisAlignedBB $box) : bool {
		$widthX = $box->maxX - $box->minX;
		$height = $box->maxY - $box->minY;
		$widthZ = $box->maxZ - $box->minZ;

		$insetX = $widthX * self::SAMPLE_INSET_RATIO;
		$insetY = $height * self::SAMPLE_INSET_RATIO;
		$insetZ = $widthZ * self::SAMPLE_INSET_RATIO;

		$minX = $box->minX + $insetX;
		$midX = ($box->minX + $box->maxX) / 2.0;
		$maxX = $box->maxX - $insetX;
		$minY = $box->minY + $insetY;
		$midY = ($box->minY + $box->maxY) / 2.0;
		$maxY = $box->maxY - $insetY;
		$minZ = $box->minZ + $insetZ;
		$midZ = ($box->minZ + $box->maxZ) / 2.0;
		$maxZ = $box->maxZ - $insetZ;

		$points = [];
		foreach ([$minX, $midX, $maxX] as $x) {
			foreach ([$minZ, $midZ, $maxZ] as $z) {
				$points[] = new Vector3($x, $midY, $z);
			}
		}
		$points[] = new Vector3($midX, $minY, $midZ);
		$points[] = new Vector3($midX, $maxY, $midZ);

		foreach ($points as $point) {
			if (!self::isAttackPathBlocked($collisionBoxes, $start, $point)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks if the line segment from start to end intersects any of the provided collision boxes.
	 * @param array<AxisAlignedBB> $collisionBoxes The list of collision boxes to check against.
	 * @param Vector3 $start The starting position of the line segment.
	 * @param Vector3 $end The ending position of the line segment.
	 */
	public static function isAttackPathBlocked(array $collisionBoxes, Vector3 $start, Vector3 $end) : bool {
		$segmentLengthSquared = $start->distanceSquared($end);
		if ($segmentLengthSquared < 0.000001) {
			return false;
		}

		foreach ($collisionBoxes as $collisionBox) {
			$intersection = $collisionBox->calculateIntercept($start, $end);
			if ($intersection === null) {
				continue;
			}

			$hitDistanceSquared = $start->distanceSquared($intersection->getHitVector());
			if ($hitDistanceSquared + self::BLOCK_HIT_EPSILON_SQUARED < $segmentLengthSquared) {
				return true;
			}
		}

		return false;
	}

	public static function isBouncingSlime(bool $onSlime, float $dy, bool $sneaking) : bool {
		// Positive dy means rising; negative dy means falling.
		$wasFalling = $dy < -0.02;
		$isRising = $dy > 0.02;

		$directionReversed = $wasFalling && $isRising;
		return !$sneaking
			&& $directionReversed
			&& $onSlime;
	}
}