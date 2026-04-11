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

namespace ReinfyTeam\Zuri\checks\modules\combat\killaura;

use pocketmine\math\Facing;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PlayerActionPacket;
use pocketmine\network\mcpe\protocol\types\PlayerAction;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function in_array;
use function is_numeric;

/**
 * Detects invalid block interaction faces linked to aura behavior.
 */
class KillAuraA extends Check {
	/**
	 * Returns the check name.
	 *
	 * @return string Check identifier.
	 */
	public function getName() : string {
		return "KillAura";
	}

	/**
	 * Returns the check subtype.
	 *
	 * @return string Check subtype identifier.
	 */
	public function getSubType() : string {
		return "A";
	}

	/**
	 * Returns the correlation group used for multi-check escalation.
	 *
	 * @return string|null Correlation group identifier.
	 */
	public function getCorrelationGroup() : ?string {
		return \ReinfyTeam\Zuri\checks\CrossCheckCorrelation::GROUP_COMBAT;
	}

	/**
	 * Processes player action packets for KillAura A evaluation.
	 *
	 * @param DataPacket $packet Incoming packet.
	 * @param PlayerAPI $playerAPI Player context.
	 * @throws DiscordWebhookException
	 */
	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		if ($packet instanceof PlayerActionPacket) {
			$this->dispatchAsyncCheck($playerAPI->getPlayer()->getName(), [
				"checkName" => $this->getName(),
				"checkSubType" => $this->getSubType(),
				"action" => $packet->action,
				"face" => $packet->face,
			]);
		}
	}

	/**
	 * Evaluates an async payload for KillAura A violations.
	 *
	 * @param array<string,mixed> $payload Serialized check payload.
	 * @return array<string,mixed> Async decision data.
	 */
	public static function evaluateAsync(array $payload) : array {
		$check = new self();
		if (($payload["checkName"] ?? null) !== $check->getName() || ($payload["checkSubType"] ?? null) !== $check->getSubType()) {
			return [];
		}

		$actionRaw = $payload["action"] ?? -1;
		$faceRaw = $payload["face"] ?? -1;
		$action = is_numeric($actionRaw) ? (int) $actionRaw : -1;
		$face = is_numeric($faceRaw) ? (int) $faceRaw : -1;

		if (in_array($action, [PlayerAction::START_BREAK, PlayerAction::ABORT_BREAK, PlayerAction::CONTINUE_DESTROY_BLOCK, PlayerAction::INTERACT_BLOCK], true)) {
			switch ($face) {
				case Facing::UP:
				case Facing::DOWN:
				case Facing::EAST:
				case Facing::NORTH:
					return ["failed" => true];
			}
		}

		return [];
	}
}
