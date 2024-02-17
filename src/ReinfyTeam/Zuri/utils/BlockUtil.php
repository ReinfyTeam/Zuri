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
 * @author ReinfyTeam
 * @link https://github.com/ReinfyTeam/
 *
 *
 */

declare(strict_types=1);

namespace ReinfyTeam\Zuri\utils;

use pocketmine\block\BlockTypeIds;
use pocketmine\entity\Location;
use pocketmine\math\Vector3;
use pocketmine\world\Position;
use function abs;
use function fmod;
use function in_array;
use function pow;
use function sqrt;

class BlockUtil {
	public static function isOnGround(Location $location, int $down) : bool {
		$id = [BlockTypeIds::AIR];
		$posX = $location->getX();
		$posZ = $location->getZ();
		$fracX = (fmod($posX, 1.0) > 0.0) ? abs(fmod($posX, 1.0)) : (1.0 - abs(fmod($posX, 1.0)));
		$fracZ = (fmod($posZ, 1.0) > 0.0) ? abs(fmod($posZ, 1.0)) : (1.0 - abs(fmod($posZ, 1.0)));
		$blockX = $location->getX();
		$blockY = $location->getY() - $down;
		$blockZ = $location->getZ();
		$world = $location->getWorld();
		if (!in_array($world->getBlock(new Vector3($blockX, $blockY, $blockZ))->getTypeId(), $id, true)) {
			return true;
		}
		if ($fracX < 0.3) {
			if (!in_array($world->getBlock(new Vector3($blockX - 1, $blockY, $blockZ))->getTypeId(), $id, true)) {
				return true;
			}
			if ($fracZ < 0.3) {
				if (!in_array($world->getBlock(new Vector3($blockX - 1, $blockY, $blockZ - 1))->getTypeId(), $id, true)) {
					return true;
				}
				if (!in_array($world->getBlock(new Vector3($blockX, $blockY, $blockZ - 1))->getTypeId(), $id, true)) {
					return true;
				}
				if (!in_array($world->getBlock(new Vector3($blockX + 1, $blockY, $blockZ - 1))->getTypeId(), $id, true)) {
					return true;
				}
			} elseif ($fracZ > 0.7) {
				if (!in_array($world->getBlock(new Vector3($blockX - 1, $blockY, $blockZ + 1))->getTypeId(), $id, true)) {
					return true;
				}
				if (!in_array($world->getBlock(new Vector3($blockX, $blockY, $blockZ + 1))->getTypeId(), $id, true)) {
					return true;
				}
				if (!in_array($world->getBlock(new Vector3($blockX + 1, $blockY, $blockZ + 1))->getTypeId(), $id, true)) {
					return true;
				}
			}
		} elseif ($fracX > 0.7) {
			if (!in_array($world->getBlock(new Vector3($blockX + 1, $blockY, $blockZ))->getTypeId(), $id, true)) {
				return true;
			}
			if ($fracZ < 0.3) {
				if (!in_array($world->getBlock(new Vector3($blockX - 1, $blockY, $blockZ - 1))->getTypeId(), $id, true)) {
					return true;
				}
				if (!in_array($world->getBlock(new Vector3($blockX, $blockY, $blockZ - 1))->getTypeId(), $id, true)) {
					return true;
				}
				if (!in_array($world->getBlock(new Vector3($blockX + 1, $blockY, $blockZ - 1))->getTypeId(), $id, true)) {
					return true;
				}
			} elseif ($fracZ > 0.7) {
				if (!in_array($world->getBlock(new Vector3($blockX - 1, $blockY, $blockZ + 1))->getTypeId(), $id, true)) {
					return true;
				}
				if (!in_array($world->getBlock(new Vector3($blockX, $blockY, $blockZ + 1))->getTypeId(), $id, true)) {
					return true;
				}
				if (!in_array($world->getBlock(new Vector3($blockX + 1, $blockY, $blockZ + 1))->getTypeId(), $id, true)) {
					return true;
				}
			}
		} elseif ($fracZ < 0.3) {
			if (!in_array($world->getBlock(new Vector3($blockX, $blockY, $blockZ - 1))->getTypeId(), $id, true)) {
				return true;
			}
		} elseif ($fracZ > 0.7 && !in_array($world->getBlock(new Vector3($blockX, $blockY, $blockZ + 1))->getTypeId(), $id, true)) {
			return true;
		}
		return false;
	}

	public static function isUnderBlock(Location $location, array $id, int $down) : bool {
		$posX = $location->getX();
		$posZ = $location->getZ();
		$fracX = (fmod($posX, 1.0) > 0.0) ? abs(fmod($posX, 1.0)) : (1.0 - abs(fmod($posX, 1.0)));
		$fracZ = (fmod($posZ, 1.0) > 0.0) ? abs(fmod($posZ, 1.0)) : (1.0 - abs(fmod($posZ, 1.0)));
		$blockX = $location->getX();
		$blockY = $location->getY() - $down;
		$blockZ = $location->getZ();
		$world = $location->getWorld();
		if (in_array($world->getBlock(new Vector3($blockX, $blockY, $blockZ))->getTypeId(), $id, true)) {
			return true;
		}
		if ($fracX < 0.3) {
			if (in_array($world->getBlock(new Vector3($blockX - 1, $blockY, $blockZ))->getTypeId(), $id, true)) {
				return true;
			}
			if ($fracZ < 0.3) {
				if (in_array($world->getBlock(new Vector3($blockX - 1, $blockY, $blockZ - 1))->getTypeId(), $id, true)) {
					return true;
				}
				if (in_array($world->getBlock(new Vector3($blockX, $blockY, $blockZ - 1))->getTypeId(), $id, true)) {
					return true;
				}
				if (in_array($world->getBlock(new Vector3($blockX + 1, $blockY, $blockZ - 1))->getTypeId(), $id, true)) {
					return true;
				}
			} elseif ($fracZ > 0.7) {
				if (in_array($world->getBlock(new Vector3($blockX - 1, $blockY, $blockZ + 1))->getTypeId(), $id, true)) {
					return true;
				}
				if (in_array($world->getBlock(new Vector3($blockX, $blockY, $blockZ + 1))->getTypeId(), $id, true)) {
					return true;
				}
				if (in_array($world->getBlock(new Vector3($blockX + 1, $blockY, $blockZ + 1))->getTypeId(), $id, true)) {
					return true;
				}
			}
		} elseif ($fracX > 0.7) {
			if (in_array($world->getBlock(new Vector3($blockX + 1, $blockY, $blockZ))->getTypeId(), $id, true)) {
				return true;
			}
			if ($fracZ < 0.3) {
				if (in_array($world->getBlock(new Vector3($blockX - 1, $blockY, $blockZ - 1))->getTypeId(), $id, true)) {
					return true;
				}
				if (in_array($world->getBlock(new Vector3($blockX, $blockY, $blockZ - 1))->getTypeId(), $id, true)) {
					return true;
				}
				if (in_array($world->getBlock(new Vector3($blockX + 1, $blockY, $blockZ - 1))->getTypeId(), $id, true)) {
					return true;
				}
			} elseif ($fracZ > 0.7) {
				if (in_array($world->getBlock(new Vector3($blockX - 1, $blockY, $blockZ + 1))->getTypeId(), $id, true)) {
					return true;
				}
				if (in_array($world->getBlock(new Vector3($blockX, $blockY, $blockZ + 1))->getTypeId(), $id, true)) {
					return true;
				}
				if (in_array($world->getBlock(new Vector3($blockX + 1, $blockY, $blockZ + 1))->getTypeId(), $id, true)) {
					return true;
				}
			}
		} elseif ($fracZ < 0.3) {
			if (in_array($world->getBlock(new Vector3($blockX, $blockY, $blockZ - 1))->getTypeId(), $id, true)) {
				return true;
			}
		} elseif ($fracZ > 0.7 && in_array($world->getBlock(new Vector3($blockX, $blockY, $blockZ + 1))->getTypeId(), $id, true)) {
			return true;
		}
		return false;
	}

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
			BlockTypeIds::WOODEN_STAIRS,
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

	public static function isOnIce(Location $location, int $down) : bool {
		$ice = [
			BlockTypeIds::ICE,
			BlockTypeIds::BLUE_ICE,
			BlockTypeIds::PACKED_ICE,
			BlockTypeIds::FROSTED_ICE
		];
		return self::isUnderBlock($location, $ice, $down);
	}

	public static function isOnLiquid(Location $location, int $down) : bool {
		$liquid = [
			BlockTypeIds::WATER,
			BlockTypeIds::LAVA
		];
		return self::isUnderBlock($location, $liquid, $down);
	}

	public static function isOnAdhesion(Location $location, int $down) : bool {
		$adhesion = [
			BlockTypeIds::LADDER,
			BlockTypeIds::VINES
		];
		return self::isUnderBlock($location, $adhesion, $down);
	}

	public static function isOnPlant(Location $location, int $down) : bool {
		$plants = [
			BlockTypeIds::GRASS_PATH,
			BlockTypeIds::CARROTS,
			BlockTypeIds::SUGARCANE,
			BlockTypeIds::PUMPKIN_STEM,
			BlockTypeIds::POTATO,
			BlockTypeIds::DEAD_BUSH,
			BlockTypeIds::SWEET_BERRY_BUSH,
			BlockTypeIds::SAPLING,
			BlockTypeIds::SEAGRASS,
			BlockTypeIds::WHEAT,
			BlockTypeIds::TALL_GRASS,
			BlockTypeIds::TORCHFLOWER,
			BlockTypeIds::CHORUS_FLOWER,
			BlockTypeIds::CORNFLOWER,
			BlockTypeIds::TORCHFLOWER_CROP,
			BlockTypeIds::FLOWERING_AZALEA_LEAVES,
			BlockTypeIds::FLOWER_POT_BLOCK,
			BlockTypeIds::NETHER_WART_PLANT
		];
		return self::isUnderBlock($location, $plants, $down);
	}

	public static function isOnDoor(Location $location, int $down) : bool {
		$doors = [
			BlockTypeIds::OAK_DOOR,
			BlockTypeIds::IRON_DOOR,
			BlockTypeIds::DARK_OAK_DOOR,
			BlockTypeIds::BIRCH_DOOR,
			BlockTypeIds::ACACIA_DOOR,
			BlockTypeIds::JUNGLE_DOOR,
			BlockTypeIds::SPRUCE_DOOR,
			BlockTypeIds::WOODEN_DOOR,
			BlockTypeIds::DARK_OAK_TRAPDOOR,
			BlockTypeIds::TRAPDOOR,
			BlockTypeIds::IRON_TRAPDOOR,
			BlockTypeIds::BIRCH_TRAPDOOR,
			BlockTypeIds::ACACIA_TRAPDOOR,
			BlockTypeIds::JUNGLE_TRAPDOOR,
			BlockTypeIds::SPRUCE_TRAPDOOR,
			BlockTypeIds::WOODEN_TRAPDOOR,
			BlockTypeIds::DARK_OAK_TRAPDOOR
		];
		return self::isUnderBlock($location, $doors, $down);
	}

	public static function isOnCarpet(Location $location, int $down) : bool {
		$carpets = [
			BlockTypeIds::CARPET
		];
		return self::isUnderBlock($location, $carpets, $down);
	}

	public static function isOnPlate(Location $location, int $down) : bool {
		$plates = [
			BlockTypeIds::CARPET,
			BlockTypeIds::BIRCH_PRESSURE_PLATE,
			BlockTypeIds::STONE_PRESSURE_PLATE,
			BlockTypeIds::ACACIA_PRESSURE_PLATE,
			BlockTypeIds::JUNGLE_PRESSURE_PLATE,
			BlockTypeIds::SPRUCE_PRESSURE_PLATE,
			BlockTypeIds::WOODEN_PRESSURE_PLATE,
			BlockTypeIds::DARK_OAK_PRESSURE_PLATE,
			BlockTypeIds::HEAVY_WEIGHTED_PRESSURE_PLATE,
			BlockTypeIds::LIGHT_WEIGHTED_PRESSURE_PLATE
		];
		return self::isUnderBlock($location, $plates, $down);
	}

	public static function isOnSnow(Location $location, int $down) : bool {
		$snow = [
			BlockTypeIds::SNOW,
			BlockTypeIds::SNOW_LAYER
		];
		return self::isUnderBlock($location, $snow, $down);
	}

	public static function onSlimeBlock(Location $location, int $down) : bool {
		return self::isUnderBlock($location, [BlockTypeIds::SLIME_BLOCK], $down);
	}

	public static function distance(Position $a, Position $b) {
		return sqrt(pow($a->getX() - $b->getX(), 2) + pow($a->getY() - $b->getY(), 2) + pow($a->getZ() - $b->getZ(), 2));
	}
}