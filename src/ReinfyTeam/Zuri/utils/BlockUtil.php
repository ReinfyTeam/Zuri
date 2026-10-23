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

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\entity\Location;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\world\Position;
use function abs;
use function array_flip;
use function ceil;
use function count;
use function floor;
use function implode;
use function max;
use function min;

/**
 * Utilities for block-related environment checks.
 */
final class BlockUtil {
	/** @var array<string,array<int,int>> */
	private static array $blockMaps = [];

	/** @var float */
	public const BLOCK_PROBE_DISTANCE = 0.45;

	public static function isOnSoul(Location $location, int $depth = 1) : bool {
		return self::isUnderblock($location, [BlockTypeIds::SOUL_SAND, BlockTypeIds::SOUL_SOIL], $depth);
	}

	public static function isInLava(Location $location, int $depth = 1) : bool {
		return self::isUnderBlock($location, [BlockTypeIds::LAVA], $depth);
	}

	public static function isSolid(Block $block) : bool {
		if (!$block->canBeFlowedInto() || count($block->getCollisionBoxes()) > 0 || !$block->isTransparent()) {
			return true;
		}

		return false;
	}

	/**
	 * Check if the entity location is on ground.
	 *
	 * @param int $down Vertical offset to check below the location.
	 */
	public static function isOnGround(Location $location, int $down) : bool {
		$world = $location->getWorld();

		$x = $location->getX();
		$y = $location->getY() - $down;
		$z = $location->getZ();

		$blockX = (int) floor($x);
		$blockY = (int) floor($y);
		$blockZ = (int) floor($z);

		// Fractional position inside block (0.0 - 1.0)
		$fracX = $x - floor($x);
		$fracZ = $z - floor($z);

		// Determine which neighboring blocks matter
		$offsets = [[0, 0]];

		if ($fracX < 0.3) {
			$offsets[] = [-1, 0];
		} elseif ($fracX > 0.7) {
			$offsets[] = [1, 0];
		}

		if ($fracZ < 0.3) {
			$offsets[] = [0, -1];
		} elseif ($fracZ > 0.7) {
			$offsets[] = [0, 1];
		}

		// Add diagonal checks only when near corners
		if (count($offsets) > 2) {
			foreach ($offsets as [$ox1, $oz1]) {
				foreach ($offsets as [$ox2, $oz2]) {
					if ($ox1 !== 0 && $oz2 !== 0) {
						$offsets[] = [$ox1, $oz2];
					}
				}
			}
		}

		// Deduplicate offsets
		$checked = [];
		foreach ($offsets as [$ox, $oz]) {
			$key = $ox . ':' . $oz;
			if (isset($checked[$key])) {
				continue;
			}
			$checked[$key] = true;

			if ($world->getBlockAt($blockX + $ox, $blockY, $blockZ + $oz)->getTypeId() !== BlockTypeIds::AIR) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks whether any of the provided block type ids are present under the given location.
	 *
	 * @param int[] $id Array of block type ids to check.
	 * @param int $down Vertical offset below location.
	 */
	public static function isUnderBlock(Location $location, array $id, int $down) : bool {
		$key = implode(':', $id);
		$idMap = self::$blockMaps[$key] ??= array_flip($id);

		$world = $location->getWorld();

		$x = $location->getX();
		$z = $location->getZ();

		$bx = (int) $x;
		$by = (int) $location->getY() - $down;
		$bz = (int) $z;

		$fx = $x - $bx;
		$fz = $z - $bz;

		$b = static fn(int $x, int $z) =>
			isset($idMap[$world->getBlockAt($x, $by, $z)->getTypeId()]);

		return
			$b($bx, $bz) ||
			($fx < 0.3 && (
				$b($bx - 1, $bz) ||
				($fz < 0.3 && (
					$b($bx - 1, $bz - 1) ||
					$b($bx,     $bz - 1) ||
					$b($bx + 1, $bz - 1)
				)) ||
				($fz > 0.7 && (
					$b($bx - 1, $bz + 1) ||
					$b($bx,     $bz + 1) ||
					$b($bx + 1, $bz + 1)
				))
			)) ||
			($fx > 0.7 && (
				$b($bx + 1, $bz) ||
				($fz < 0.3 && (
					$b($bx - 1, $bz - 1) ||
					$b($bx,     $bz - 1) ||
					$b($bx + 1, $bz - 1)
				)) ||
				($fz > 0.7 && (
					$b($bx - 1, $bz + 1) ||
					$b($bx,     $bz + 1) ||
					$b($bx + 1, $bz + 1)
				))
			)) ||
			($fz < 0.3 && $b($bx, $bz - 1)) ||
			($fz > 0.7 && $b($bx, $bz + 1));
	}

	/**
	 * Returns the type ids of surrounding blocks at the player's horizontal plane.
	 *
	 * @return int[]
	 */
	public static function getSurroundingBlocks(Location $loc) : array {
		$world = $loc->getWorld();
		$x = (int) $loc->getX();
		$y = (int) $loc->getY();
		$z = (int) $loc->getZ();

		$coords = [
			[0, 0],
			[-1, 0],
			[-1, -1],
			[0, -1],
			[1, 0],
			[1, 1],
			[0, 1],
			[1, -1],
			[-1, 1],
		];

		$result = [];

		foreach ($coords as [$dx, $dz]) {
			$result[] = $world->getBlockAt($x + $dx, $y, $z + $dz)->getTypeId();
		}

		return $result;
	}

	/**
	 * Determines whether the ground in a 5x5 area beneath the player is solid.
	 */
	public static function isGroundSolid(Location $pos) : bool {
		$world = $pos->getWorld();

		$baseX = (int) $pos->x;
		$baseY = (int) $pos->y - 1;
		$baseZ = (int) $pos->z;

		for ($x = -2; $x <= 2; $x++) {
			$bx = $baseX + $x;

			for ($z = -2; $z <= 2; $z++) {
				$bz = $baseZ + $z;

				if (!self::isSolid($world->getBlockAt($bx, $baseY, $bz))) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Returns the block directly above the player's position.
	 */
	public static function getBlockAbove(Location $loc) : Block {
		$world = $loc->getWorld();
		$x = (int) $loc->getX();
		$y = (int) $loc->getY();
		$z = (int) $loc->getZ();

		return $world->getBlockAt(
			(int) $x,
			(int) $y + 1,
			(int) $z
		);
	}

	/**
	 * Checks whether the given location is on stairs.
	 */
	public static function isOnStairs(Location $location, int $down) : bool {
		$stairs = [
			BlockTypeIds::STONE_STAIRS,
			BlockTypeIds::OAK_STAIRS,
			BlockTypeIds::BIRCH_STAIRS,
			BlockTypeIds::BRICK_STAIRS,
			BlockTypeIds::STONE_BRICK_STAIRS,
			BlockTypeIds::ACACIA_STAIRS,
			BlockTypeIds::JUNGLE_STAIRS,
			BlockTypeIds::PURPUR_STAIRS,
			BlockTypeIds::QUARTZ_STAIRS,
			BlockTypeIds::SPRUCE_STAIRS,
			BlockTypeIds::DIORITE_STAIRS,
			BlockTypeIds::GRANITE_STAIRS,
			BlockTypeIds::ANDESITE_STAIRS,
			BlockTypeIds::DARK_OAK_STAIRS,
			BlockTypeIds::END_STONE_BRICKS,
			BlockTypeIds::SANDSTONE_STAIRS,
			BlockTypeIds::PRISMARINE_STAIRS,
			BlockTypeIds::COBBLESTONE_STAIRS,
			BlockTypeIds::NETHER_BRICK_STAIRS,
			BlockTypeIds::RED_SANDSTONE_STAIRS,
			BlockTypeIds::SMOOTH_QUARTZ_STAIRS,
			BlockTypeIds::DARK_PRISMARINE_STAIRS,
			BlockTypeIds::POLISHED_DIORITE_STAIRS,
			BlockTypeIds::POLISHED_GRANITE_STAIRS,
			BlockTypeIds::RED_NETHER_BRICK_STAIRS,
			BlockTypeIds::SMOOTH_SANDSTONE_STAIRS,
			BlockTypeIds::MOSSY_COBBLESTONE_STAIRS,
			BlockTypeIds::MOSSY_STONE_BRICK_STAIRS,
			BlockTypeIds::POLISHED_ANDESITE_STAIRS,
			BlockTypeIds::PRISMARINE_BRICKS_STAIRS,
			BlockTypeIds::SMOOTH_RED_SANDSTONE_STAIRS
		];
		return self::isUnderBlock($location, $stairs, $down);
	}

	/**
	 * Checks whether the given location is on ice.
	 */
	public static function isOnIce(Location $location, int $down) : bool {
		$ice = [
			BlockTypeIds::ICE,
			BlockTypeIds::BLUE_ICE,
			BlockTypeIds::PACKED_ICE,
			BlockTypeIds::FROSTED_ICE
		];
		return self::isUnderBlock($location, $ice, $down);
	}

	/**
	 * Checks whether the given location is on a magma block.
	 */
	public static function isOnMagma(Location $location, int $down) : bool {
		return self::isUnderBlock($location, [BlockTypeIds::MAGMA], $down);
	}

	/**
	 * Checks whether the given location is over a liquid block.
	 */
	public static function isOnLiquid(Location $location, int $down) : bool {
		$liquid = [
			BlockTypeIds::WATER,
			BlockTypeIds::LAVA
		];
		return self::isUnderBlock($location, $liquid, $down);
	}

	public static function isOnWater(Location $location, int $down) : bool {
		return self::isUnderBlock($location, [BlockTypeIds::WATER], $down);
	}

	public static function isOnLava(Location $location, int $down) : bool {
		return self::isUnderBlock($location, [BlockTypeIds::LAVA], $down);
	}

	public static function isTwoBlockPassage(Location $location) : bool {
		$world = $location->getWorld();
		$x = (int) floor($location->getX());
		$y = (int) floor($location->getY());
		$z = (int) floor($location->getZ());

		return !self::isSolid($world->getBlockAt($x, $y + 1, $z)) &&
			!self::isSolid($world->getBlockAt($x, $y + 2, $z));
	}

	/**
	 * Checks whether the given location is on adhesion blocks (ladders, vines).
	 */
	public static function isOnAdhesion(Location $location, int $down) : bool {
		$adhesion = [
			BlockTypeIds::LADDER,
			BlockTypeIds::VINES
		];
		return self::isUnderBlock($location, $adhesion, $down);
	}

	/**
	 * Checks whether the given location is on plant-type blocks.
	 */
	public static function isOnPlant(Location $location, int $down) : bool {
		$plants = [
			BlockTypeIds::GRASS_PATH,
			BlockTypeIds::CARROTS,
			BlockTypeIds::SUGARCANE,
			BlockTypeIds::PUMPKIN_STEM,
			BlockTypeIds::POTATOES,
			BlockTypeIds::DEAD_BUSH,
			BlockTypeIds::SWEET_BERRY_BUSH,
			BlockTypeIds::OAK_SAPLING,
			BlockTypeIds::WHEAT,
			BlockTypeIds::TALL_GRASS,
			BlockTypeIds::TORCHFLOWER,
			BlockTypeIds::CHORUS_FLOWER,
			BlockTypeIds::CORNFLOWER,
			BlockTypeIds::TORCHFLOWER_CROP,
			BlockTypeIds::FLOWERING_AZALEA_LEAVES,
			BlockTypeIds::FLOWER_POT
		];
		return self::isUnderBlock($location, $plants, $down);
	}

	/**
	 * Checks whether the given location is on door-type blocks.
	 */
	public static function isOnDoor(Location $location, int $down) : bool {
		$doors = [
			BlockTypeIds::OAK_DOOR,
			BlockTypeIds::IRON_DOOR,
			BlockTypeIds::DARK_OAK_DOOR,
			BlockTypeIds::BIRCH_DOOR,
			BlockTypeIds::ACACIA_DOOR,
			BlockTypeIds::JUNGLE_DOOR,
			BlockTypeIds::SPRUCE_DOOR,
			BlockTypeIds::DARK_OAK_TRAPDOOR,
			BlockTypeIds::OAK_TRAPDOOR,
			BlockTypeIds::IRON_TRAPDOOR,
			BlockTypeIds::BIRCH_TRAPDOOR,
			BlockTypeIds::ACACIA_TRAPDOOR,
			BlockTypeIds::JUNGLE_TRAPDOOR,
			BlockTypeIds::SPRUCE_TRAPDOOR,
			BlockTypeIds::DARK_OAK_TRAPDOOR
		];
		return self::isUnderBlock($location, $doors, $down);
	}

	/**
	 * Checks whether the given location is on carpet.
	 */
	public static function isOnCarpet(Location $location, int $down) : bool {
		$carpets = [
			BlockTypeIds::CARPET
		];
		return self::isUnderBlock($location, $carpets, $down);
	}

	/**
	 * Checks whether the given location is on a pressure plate.
	 */
	public static function isOnPlate(Location $location, int $down) : bool {
		$plates = [
			BlockTypeIds::CARPET,
			BlockTypeIds::BIRCH_PRESSURE_PLATE,
			BlockTypeIds::STONE_PRESSURE_PLATE,
			BlockTypeIds::ACACIA_PRESSURE_PLATE,
			BlockTypeIds::JUNGLE_PRESSURE_PLATE,
			BlockTypeIds::SPRUCE_PRESSURE_PLATE,
			BlockTypeIds::OAK_PRESSURE_PLATE,
			BlockTypeIds::DARK_OAK_PRESSURE_PLATE,
			BlockTypeIds::WEIGHTED_PRESSURE_PLATE_HEAVY,
			BlockTypeIds::WEIGHTED_PRESSURE_PLATE_LIGHT
		];
		return self::isUnderBlock($location, $plates, $down);
	}

	/**
	 * Checks whether the given location is on a web block.
	 */
	public static function isOnWeb(Location $location, int $down) : bool {
		return self::isUnderBlock($location, [BlockTypeIds::COBWEB], $down);
	}

	/**
	 * Checks whether the given location is on snow blocks.
	 */
	public static function isOnSnow(Location $location, int $down) : bool {
		$snow = [
			BlockTypeIds::SNOW,
			BlockTypeIds::SNOW_LAYER
		];
		return self::isUnderBlock($location, $snow, $down);
	}

	/**
	 * Checks whether the given location is on a slime block.
	 */
	public static function onSlimeBlock(Location $location, int $down) : bool {
		return self::isUnderBlock($location, [BlockTypeIds::SLIME], $down);
	}

	public static function hasSlimeWithinVerticalGap(AxisAlignedBB $box, Location $location, float $maxGap) : bool {
		$world = $location->getWorld();
		$feetY = $box->minY;

		$minX = (int) floor($box->minX + 0.001);
		$maxX = (int) floor($box->maxX - 0.001);
		$minZ = (int) floor($box->minZ + 0.001);
		$maxZ = (int) floor($box->maxZ - 0.001);

		$minY = (int) ceil($feetY - 1.0 - $maxGap - 0.001);
		$maxY = (int) floor($feetY - 1.0 + 0.001);

		for ($y = $minY; $y <= $maxY; ++$y) {
			$gap = $feetY - ($y + 1.0);

			if ($gap < -0.02 || $gap > $maxGap + 0.02) {
				continue;
			}

			for ($x = $minX; $x <= $maxX; ++$x) {
				for ($z = $minZ; $z <= $maxZ; ++$z) {
					if ($world->getBlockAt($x, $y, $z)->getTypeId() === BlockTypeIds::SLIME) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Checks if the player is blocked ahead.
	 *
	 * @param Location $location The player's location.
	 * @param AxisAlignedBB $bb The player's bounding box.
	 * @param Vector3 $motion The player's motion.
	 * @param float $horizontalSpeed The horizontal speed of the player.
	 * @return bool True if the player is blocked ahead, false otherwise.
	 */
	public static function isBlockedAhead(Location $location, AxisAlignedBB $bb, Vector3 $motion, float $horizontalSpeed) : bool{
		$dirX = $motion->x / $horizontalSpeed; 
		$dirZ = $motion->z / $horizontalSpeed;
        $shifted = new AxisAlignedBB(
            $bb->minX + ($dirX * self::BLOCK_PROBE_DISTANCE),
            $bb->minY,
            $bb->minZ + ($dirZ * self::BLOCK_PROBE_DISTANCE),
            $bb->maxX + ($dirX * self::BLOCK_PROBE_DISTANCE),
            $bb->maxY,
            $bb->maxZ + ($dirZ * self::BLOCK_PROBE_DISTANCE)
        );

        $world = $location->getWorld();
        for($x = (int) floor($shifted->minX); $x <= (int) floor($shifted->maxX); ++$x){
            for($y = (int) floor($shifted->minY); $y <= (int) floor($shifted->maxY); ++$y){
                for($z = (int) floor($shifted->minZ); $z <= (int) floor($shifted->maxZ); ++$z){
                    if($world->getBlockAt($x, $y, $z)->collidesWithBB($shifted)){
                        return true;
                    }
                }
            }
        }
        return false;
    }

	/**
	 * Returns the block under the given location at the specified depth.
	 *
	 * @param int $deep Depth below the location.
	 */
	public static function getUnderBlock(Location $location, int $deep = 1) : Block {
		$world = $location->getWorld();

		$x = (int) $location->getX();
		$y = (int) $location->getY() - $deep;
		$z = (int) $location->getZ();

		return $world->getBlockAt($x, $y, $z);
	}

	/**
	 * Finds a safe position in the world.
	 *
	 * @param Position $pos The position to search from.
	 * @return Vector3|null The safe position, or null if none is found.
	 */
	public static function findSafePosition(Position $pos) : ?Vector3 {
		$world = $pos->getWorld();

		$x = (int) $pos->getX();
		$z = (int) $pos->getZ();
		$startHintY = (int) $pos->getY();

		$minY = $world->getMinY();
		$maxY = $world->getMaxY();

		// Clamp the starting Y search coordinate within world boundaries
		$startHintY = max($minY + 1, min($maxY - 2, $startHintY));

		// Search outward from the initial Y hint position
		for ($offset = 0; $offset <= $maxY - $minY; $offset++) {
			foreach ([$startHintY + $offset, $startHintY - $offset] as $y) {
				// Stay within world barriers
				if ($y < $minY + 1 || $y >= $maxY - 1) {
					continue;
				}

				$feetBlock = $world->getBlockAt($x, $y, $z);
				$torsoBlock = $world->getBlockAt($x, $y + 1, $z);
				$groundBlock = $world->getBlockAt($x, $y - 1, $z);

				// Safety rules:
				// 1. Feet position must be passable (Air, tall grass, flowers, etc.)
				// 2. Torso position must be passable (Air, etc.)
				// 3. Ground position must be solid to stand on, and NOT dangerous (Lava/Fire)
				if (
					self::isSolid($feetBlock) &&
					self::isSolid($torsoBlock) &&
					self::isSolid($groundBlock) &&
					$groundBlock->getTypeId() !== BlockTypeIds::LAVA &&
					$groundBlock->getTypeId() !== BlockTypeIds::FIRE
				) {
					// Return center alignment of the safe block coordinate
					return new Vector3($x + 0.5, $y, $z + 0.5);
				}
			}
		}

		return null;
	}

	/**
	 * Scans horizontally outward from the player's position in a grid,
	 * using the player's current Y height as the origin point.
	 *
	 * @param Position $position The position to search from.
	 * @param int $radius   How many blocks out horizontally to search
	 */
	public static function findSafePositionInRadius(Position $position, int $radius = 5) : ?Vector3 {
		// Ring loop out from radius 0 (center column) to maximum boundary radius
		for ($r = 0; $r <= $radius; $r++) {
			for ($dx = -$r; $dx <= $r; $dx++) {
				for ($dz = -$r; $dz <= $r; $dz++) {
					// Only scan blocks sitting directly on the outer crust of the current ring
					if (abs($dx) !== $r && abs($dz) !== $r) {
						continue;
					}

					$centerX = $position->getX();
					$centerZ = $position->getZ();
					$targetX = $centerX + $dx;
					$targetZ = $centerZ + $dz;

					// Run the original column check function using the player's current Y height layer
					$pos = self::findSafePosition($position);
					if ($pos !== null) {
						return $pos;
					}
				}
			}
		}

		return null;
	}
}
