<?php

namespace ReinfyTeam\Zuri\utils;

use pocketmine\math\Vector3;
use pocketmine\math\Vector2;
use pocketmine\block\BlockTypeIds;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\item\enchantment\VanillaEnchantments;

final class Utils {
    
    public static function vector3ToArray(Vector3 $vector3) : array {
        return [
            "x" => $vector3->getX(),
            "y" => $vector3->getY(),
            "z" => $vector3->getZ()
        ];
    }

    public static function arrayToVector3(array $array) {
        return new Vector3($array["x"], $array["y"], $array["z"]);
    }

    public static function vector2ToArray(Vector2 $vector2) : array {
        return [
            "x" => $vector2->getX(),
            "z" => $vector2->getZ()
        ];
    }

    public static function arrayToVector2(array $array) : Vector2 {
        return new Vector2($array["x"], $array["z"]);
    }

    public static function getMovementMultiplier(Player $player) : float {
        $multiplier = 1.0;

        if ($player->isUsingItem()) {
            return 0.2;
        }

        if ($player->isSneaking()) {
            $leggings = $player->getArmorInventory()->getLeggings();
            $swift = $leggings?->getEnchantmentLevel(VanillaEnchantments::SWIFT_SNEAK()) ?? 0;

            $multiplier *= self::getSneakMultiplier($swift);
        }

        if ($player->isSprinting()) {
            $multiplier *= 1.3;
        }

        [$speedAmp, $slowAmp] = self::getSpeedEffects($player);

        $multiplier *= 1.0 + (0.2 * $speedAmp);
        $multiplier *= max(0.0, 1.0 - (0.15 * $slowAmp));

        $multiplier *= self::getSoulSpeedMultiplier($player);

        return $multiplier;
    }

    public static function getSneakMultiplier(int $level) : float {
        return min(1.0, max(0.3, 0.3 + ($level * 0.15)));
    }

    public static function getSpeedEffects(Player $player) : array {
        $effects = $player->getEffects();
		$speed = $effects->get(VanillaEffects::SPEED());
		$slowness = $effects->get(VanillaEffects::SLOWNESS());

		$speed = $speed != null ? $speed->getEffectLevel() : 0;
		$slowness = $slowness != null ? $slowness->getEffectLevel() : 0;

        return [$speed, $slowness];
    }

    public static function getSoulSpeedMultiplier(Player $player) : float {
        $pos = $player->getPosition();
        $world = $player->getWorld();

        $blockId = $world->getBlockAt(
            (int) $pos->x,
            (int) $pos->y - 1,
            (int) $pos->z
        )->getTypeId();

        if ($blockId !== BlockTypeIds::SOUL_SAND && $blockId !== BlockTypeIds::SOUL_SOIL) {
            return 1.0;
        }

        $boots = $player->getArmorInventory()->getBoots();
        $level = $boots?->getEnchantmentLevel(VanillaEnchantments::SOUL_SPEED()) ?? 0;

        if ($level > 0) {
            return 1.0 + (0.105 * $level);
        }

        return 0.4;
    }
}