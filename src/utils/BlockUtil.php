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
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\Position;
use function abs;
use function fmod;
use function in_array;
use function sqrt;

class BlockUtil {
	public static function getBlockAbove(Player $player) : ?Block {
		$position = $player->getPosition()->add(0, 1.0, 0);
		return $player->getWorld()->getBlock($position->getSide(Facing::UP));
	}

	public static function isGroundSolid(Player $player) : bool {
		$world = $player->getWorld();
		$pos = $player->getPosition();

		for ($x = -2; $x <= 2; $x++) {
			for ($z = -2; $z <= 2; $z++) {
				$blockPos = new Vector3($pos->x + $x, $pos->y - 1, $pos->z + $z);  // Check 1 block under the player
				$block = $world->getBlock($blockPos);

				// If the block is not a solid, return false
				if (!$block->isSolid()) {
					return false;
				}
			}
		}

		return true;
	}

	public static function getSurroundingBlocks(Player $player) : array {
		$world = $player->getWorld();

		$posX = $player->getLocation()->getX();
		$posY = $player->getLocation()->getY();
		$posZ = $player->getLocation()->getZ();

		$pos1 = new Vector3($posX  , $posY, $posZ  );
		$pos2 = new Vector3($posX - 1, $posY, $posZ  );
		$pos3 = new Vector3($posX - 1, $posY, $posZ - 1);
		$pos4 = new Vector3($posX  , $posY, $posZ - 1);
		$pos5 = new Vector3($posX + 1, $posY, $posZ  );
		$pos6 = new Vector3($posX + 1, $posY, $posZ + 1);
		$pos7 = new Vector3($posX  , $posY, $posZ + 1);
		$pos8 = new Vector3($posX + 1, $posY, $posZ - 1);
		$pos9 = new Vector3($posX - 1, $posY, $posZ + 1);

		$bpos1 = $world->getBlock($pos1)->getTypeId();
		$bpos2 = $world->getBlock($pos2)->getTypeId();
		$bpos3 = $world->getBlock($pos3)->getTypeId();
		$bpos4 = $world->getBlock($pos4)->getTypeId();
		$bpos5 = $world->getBlock($pos5)->getTypeId();
		$bpos6 = $world->getBlock($pos6)->getTypeId();
		$bpos7 = $world->getBlock($pos7)->getTypeId();
		$bpos8 = $world->getBlock($pos8)->getTypeId();
		$bpos9 = $world->getBlock($pos9)->getTypeId();

		return  [$bpos1, $bpos2, $bpos3, $bpos4, $bpos5, $bpos6, $bpos7, $bpos8, $bpos9];
	}

	public static function isOnGround(Location $location, int $down) : bool {
		$posX = $location->getX();
		$posZ = $location->getZ();
		$fracX = (fmod($posX, 1.0) > 0.0) ? abs(fmod($posX, 1.0)) : (1.0 - abs(fmod($posX, 1.0)));
		$fracZ = (fmod($posZ, 1.0) > 0.0) ? abs(fmod($posZ, 1.0)) : (1.0 - abs(fmod($posZ, 1.0)));
		$blockX = $location->getX();
		$blockY = $location->getY() - $down;
		$blockZ = $location->getZ();
		$world = $location->getWorld();
		if ($world->getBlockAt((int) $blockX, (int) $blockY, (int) $blockZ)->getTypeId() !== BlockTypeIds::AIR) {
			return true;
		}
		if ($fracX < 0.3) {
			if ($world->getBlockAt((int) $blockX - 1, (int) $blockY, (int) $blockZ)->getTypeId() !== BlockTypeIds::AIR) {
				return true;
			}
			if ($fracZ < 0.3) {
				if ($world->getBlockAt((int) $blockX - 1, (int) $blockY, (int) $blockZ - 1)->getTypeId() !== BlockTypeIds::AIR) {
					return true;
				}
				if ($world->getBlockAt((int) $blockX, (int) $blockY, (int) $blockZ - 1)->getTypeId() !== BlockTypeIds::AIR) {
					return true;
				}
				if ($world->getBlockAt((int) $blockX + 1, (int) $blockY, (int) $blockZ - 1)->getTypeId() !== BlockTypeIds::AIR) {
					return true;
				}
			} elseif ($fracZ > 0.7) {
				if ($world->getBlockAt((int) $blockX - 1, (int) $blockY, (int) $blockZ + 1)->getTypeId() !== BlockTypeIds::AIR) {
					return true;
				}
				if ($world->getBlockAt((int) $blockX, (int) $blockY, (int) $blockZ + 1)->getTypeId() !== BlockTypeIds::AIR) {
					return true;
				}
				if ($world->getBlockAt((int) $blockX + 1, (int) $blockY, (int) $blockZ + 1)->getTypeId() !== BlockTypeIds::AIR) {
					return true;
				}
			}
		} elseif ($fracX > 0.7) {
			if ($world->getBlockAt((int) $blockX + 1, (int) $blockY, (int) $blockZ)->getTypeId() !== BlockTypeIds::AIR) {
				return true;
			}
			if ($fracZ < 0.3) {
				if ($world->getBlockAt((int) $blockX - 1, (int) $blockY, (int) $blockZ - 1)->getTypeId() !== BlockTypeIds::AIR) {
					return true;
				}
				if ($world->getBlockAt((int) $blockX, (int) $blockY, (int) $blockZ - 1)->getTypeId() !== BlockTypeIds::AIR) {
					return true;
				}
				if ($world->getBlockAt((int) $blockX + 1, (int) $blockY, (int) $blockZ - 1)->getTypeId() !== BlockTypeIds::AIR) {
					return true;
				}
			} elseif ($fracZ > 0.7) {
				if ($world->getBlockAt((int) $blockX - 1, (int) $blockY, (int) $blockZ + 1)->getTypeId() !== BlockTypeIds::AIR) {
					return true;
				}
				if ($world->getBlockAt((int) $blockX, (int) $blockY, (int) $blockZ + 1)->getTypeId() !== BlockTypeIds::AIR) {
					return true;
				}
				if ($world->getBlockAt((int) $blockX + 1, (int) $blockY, (int) $blockZ + 1)->getTypeId() !== BlockTypeIds::AIR) {
					return true;
				}
			}
		} elseif ($fracZ < 0.3) {
			if ($world->getBlockAt((int) $blockX, (int) $blockY, (int) $blockZ - 1)->getTypeId() !== BlockTypeIds::AIR) {
				return true;
			}
		} elseif ($fracZ > 0.7 && $world->getBlockAt((int) $blockX, (int) $blockY, (int) $blockZ + 1)->getTypeId() !== BlockTypeIds::AIR) {
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

		if (in_array($world->getBlockAt((int) $blockX, (int) $blockY, (int) $blockZ)->getTypeId(), $id, true)) {
			return true;
		}
		if ($fracX < 0.3) {
			if (in_array($world->getBlockAt((int) $blockX - 1, (int) $blockY, (int) $blockZ)->getTypeId(), $id, true)) {
				return true;
			}
			if ($fracZ < 0.3) {
				if (in_array($world->getBlockAt((int) $blockX - 1, (int) $blockY, (int) $blockZ - 1)->getTypeId(), $id, true)) {
					return true;
				}
				if (in_array($world->getBlockAt((int) $blockX, (int) $blockY, (int) $blockZ - 1)->getTypeId(), $id, true)) {
					return true;
				}
				if (in_array($world->getBlockAt((int) $blockX + 1, (int) $blockY, (int) $blockZ - 1)->getTypeId(), $id, true)) {
					return true;
				}
			} elseif ($fracZ > 0.7) {
				if (in_array($world->getBlockAt((int) $blockX - 1, (int) $blockY, (int) $blockZ + 1)->getTypeId(), $id, true)) {
					return true;
				}
				if (in_array($world->getBlockAt((int) $blockX, (int) $blockY, (int) $blockZ + 1)->getTypeId(), $id, true)) {
					return true;
				}
				if (in_array($world->getBlockAt((int) $blockX + 1, (int) $blockY, (int) $blockZ + 1)->getTypeId(), $id, true)) {
					return true;
				}
			}
		} elseif ($fracX > 0.7) {
			if (in_array($world->getBlockAt((int) $blockX + 1, (int) $blockY, (int) $blockZ)->getTypeId(), $id, true)) {
				return true;
			}
			if ($fracZ < 0.3) {
				if (in_array($world->getBlockAt((int) $blockX - 1, (int) $blockY, (int) $blockZ - 1)->getTypeId(), $id, true)) {
					return true;
				}
				if (in_array($world->getBlockAt((int) $blockX, (int) $blockY, (int) $blockZ - 1)->getTypeId(), $id, true)) {
					return true;
				}
				if (in_array($world->getBlockAt((int) $blockX + 1, (int) $blockY, (int) $blockZ - 1)->getTypeId(), $id, true)) {
					return true;
				}
			} elseif ($fracZ > 0.7) {
				if (in_array($world->getBlockAt((int) $blockX - 1, (int) $blockY, (int) $blockZ + 1)->getTypeId(), $id, true)) {
					return true;
				}
				if (in_array($world->getBlockAt((int) $blockX, (int) $blockY, (int) $blockZ + 1)->getTypeId(), $id, true)) {
					return true;
				}
				if (in_array($world->getBlockAt((int) $blockX + 1, (int) $blockY, (int) $blockZ + 1)->getTypeId(), $id, true)) {
					return true;
				}
			}
		} elseif ($fracZ < 0.3) {
			if (in_array($world->getBlockAt((int) $blockX, (int) $blockY, (int) $blockZ - 1)->getTypeId(), $id, true)) {
				return true;
			}
		} elseif ($fracZ > 0.7 && in_array($world->getBlockAt((int) $blockX, (int) $blockY, (int) $blockZ + 1)->getTypeId(), $id, true)) {
			return true;
		}
		return false;
	}

	public static function isOnStairs(Location $location, int $down) : bool {
		static $stairs = [
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

	public static function isOnIce(Location $location, int $down) : bool {
		static $ice = [
			BlockTypeIds::ICE,
			BlockTypeIds::BLUE_ICE,
			BlockTypeIds::PACKED_ICE,
			BlockTypeIds::FROSTED_ICE
		];
		return self::isUnderBlock($location, $ice, $down);
	}

	public static function isOnLiquid(Location $location, int $down) : bool {
		static $liquid = [
			BlockTypeIds::WATER,
			BlockTypeIds::LAVA
		];
		return self::isUnderBlock($location, $liquid, $down);
	}

	public static function isOnAdhesion(Location $location, int $down) : bool {
		static $adhesion = [
			BlockTypeIds::LADDER,
			BlockTypeIds::VINES
		];
		return self::isUnderBlock($location, $adhesion, $down);
	}

	public static function isOnPlant(Location $location, int $down) : bool {
		static $plants = [
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

	public static function isOnDoor(Location $location, int $down) : bool {
		static $doors = [
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

	public static function isOnCarpet(Location $location, int $down) : bool {
		static $carpets = [
			BlockTypeIds::CARPET
		];
		return self::isUnderBlock($location, $carpets, $down);
	}

	public static function isOnPlate(Location $location, int $down) : bool {
		static $plates = [
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

	public static function isOnSnow(Location $location, int $down) : bool {
		static $snow = [
			BlockTypeIds::SNOW,
			BlockTypeIds::SNOW_LAYER
		];
		return self::isUnderBlock($location, $snow, $down);
	}

	public static function onSlimeBlock(Location $location, int $down) : bool {
		return self::isUnderBlock($location, [BlockTypeIds::SLIME], $down);
	}

	public static function distance(Position $a, Position $b) {
		return sqrt((($a->getX() - $b->getX()) ** 2) + (($a->getY() - $b->getY()) ** 2) + (($a->getZ() - $b->getZ()) ** 2));
	}
}