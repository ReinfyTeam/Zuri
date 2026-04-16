<?php

namespace ReinfyTeam\Zuri\utils;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\entity\Location;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\Position;

final class BlockUtil {

    /**
     * Check if the entity location is on ground.
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
            if (isset($checked[$key])) continue;
            $checked[$key] = true;

            if ($world->getBlockAt($blockX + $ox, $blockY, $blockZ + $oz)->getTypeId() !== BlockTypeIds::AIR) {
                return true;
            }
        }

        return false;
    }

    public static function isUnderBlock(Location $location, array $id, int $down) : bool {
        static $map = [];
        $key = implode(':', $id);

        $map[$key] ??= array_flip($id);
        $idMap = $map[$key];

        $world = $location->getWorld();

        $x = $location->getX();
        $z = $location->getZ();

        $bx = (int) $x;
        $by = (int) $location->getY() - $down;
        $bz = (int) $z;

        $fx = $x - $bx;
        $fz = $z - $bz;

        $b = static fn($x, $z) =>
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

    public static function getSurroundingBlocks(Player $player) : array {
        $world = $player->getWorld();
        $loc = $player->getLocation();

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

    public static function isGroundSolid(Player $player) : bool {
        $world = $player->getWorld();
        $pos = $player->getPosition();

        $baseX = (int) $pos->x;
        $baseY = (int) $pos->y - 1;
        $baseZ = (int) $pos->z;

        for ($x = -2; $x <= 2; $x++) {
            $bx = $baseX + $x;

            for ($z = -2; $z <= 2; $z++) {
                $bz = $baseZ + $z;

                if (!$world->getBlockAt($bx, $baseY, $bz)->isSolid()) {
                    return false;
                }
            }
        }

        return true;
    }

    public static function getBlockAbove(Player $player) : ?Block {
        $pos = $player->getPosition();
        $world = $player->getWorld();

        return $world->getBlockAt(
            (int) $pos->x,
            (int) $pos->y + 1,
            (int) $pos->z
        );
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

    public static function getUnderBlock(Location $location, int $deep = 1) : Block {
        $world = $location->getWorld();

        $x = (int) $location->getX();
        $y = (int) $location->getY() - $deep;
        $z = (int) $location->getZ();

        return $world->getBlockAt($x, $y, $z);
    }
}