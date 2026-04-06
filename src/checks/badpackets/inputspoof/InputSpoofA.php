<?php

declare(strict_types=1);

namespace ReinfyTeam\Zuri\checks\badpackets\inputspoof;

use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\MathUtil;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function abs;

class InputSpoofA extends Check {
	public function getName() : string {
		return "InputSpoof";
	}

	public function getSubType() : string {
		return "A";
	}

	/**
	 * @throws DiscordWebhookException
	 */
	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		if (!$packet instanceof PlayerAuthInputPacket) {
			return;
		}

		$moveX = $packet->getMoveVecX();
		$moveZ = $packet->getMoveVecZ();
		$maxAxis = (float) $this->getConstant("max-axis");
		$maxVectorLength = (float) $this->getConstant("max-vector-length");
		$vectorLength = MathUtil::horizontalLength($moveX, $moveZ);

		$this->debug($playerAPI, "moveX={$moveX}, moveZ={$moveZ}, vectorLength={$vectorLength}");

		if (abs($moveX) > $maxAxis || abs($moveZ) > $maxAxis || $vectorLength > $maxVectorLength) {
			$this->failed($playerAPI);
		}
	}
}
