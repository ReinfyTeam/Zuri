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

namespace ReinfyTeam\Zuri\checks\moving;

use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\Event;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\types\InputMode;
use pocketmine\network\mcpe\protocol\types\PlayerAuthInputFlags;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use ReinfyTeam\Zuri\utils\MathUtil;
use function spl_object_id;

class OmniSprint extends Check {
	public function getName() : string {
		return "OmniSprint";
	}

	public function getSubType() : string {
		return "A";
	}

	private array $check = [];

	/**
	 * @throws DiscordWebhookException
	 */
	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		$player = $playerAPI->getPlayer();
		if ($packet instanceof PlayerAuthInputPacket) {
			if (
				!$player->isSurvival() ||
				$playerAPI->getTeleportTicks() < 40 ||
				$playerAPI->getTeleportCommandTicks() < 40 ||
				$playerAPI->getHurtTicks() < 20 ||
				$player->hasNoClientPredictions() ||
				$playerAPI->isRecentlyCancelledEvent()
			) {
				unset($this->check[spl_object_id($playerAPI)]);
				return;
			}

			if ($packet->getInputMode() === InputMode::MOUSE_KEYBOARD || $packet->getInputMode() === InputMode::TOUCHSCREEN) { // for windows and mobile, ios only..
				$inputFlags = $packet->getInputFlags();
				$left = $inputFlags->get(PlayerAuthInputFlags::LEFT);
				$right = $inputFlags->get(PlayerAuthInputFlags::RIGHT);
				$down = $inputFlags->get(PlayerAuthInputFlags::DOWN);
				$up = $inputFlags->get(PlayerAuthInputFlags::UP);
				if ($down || $right || $left || $up) {
					$movingFast = isset($this->check[spl_object_id($playerAPI)]);
					$invalidSprint = $player->isSprinting() && ($down || (($left || $right) && !$up));
					if ($invalidSprint && $movingFast) {
						$this->failed($playerAPI);
					}
					$this->debug($playerAPI, "inputMode=" . $packet->getInputMode() . ", left=" . ($left ? "1" : "0") . ", right=" . ($right ? "1" : "0") . ", down=" . ($down ? "1" : "0") . ", up=" . ($up ? "1" : "0") . ", movingFast=" . $movingFast . ", invalidSprint=" . $invalidSprint);
				}
			}
		}
	}

	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
		if ($event instanceof PlayerMoveEvent) {
			$player = $playerAPI->getPlayer();
			if (
				!$player->isSurvival() ||
				$playerAPI->getTeleportTicks() < 40 ||
				$playerAPI->getTeleportCommandTicks() < 40 ||
				$playerAPI->isRecentlyCancelledEvent()
			) {
				unset($this->check[spl_object_id($playerAPI)]);
				return;
			}

			if (($d = MathUtil::XZDistanceSquared($event->getFrom(), $event->getTo())) > $this->getConstant("max-speed") && !$player->getEffects()->has(VanillaEffects::SPEED())) {
				$this->check[spl_object_id($playerAPI)] = true; // moving too fast?
			} else {
				if (isset($this->check[spl_object_id($playerAPI)])) {
					unset($this->check[spl_object_id($playerAPI)]);
				}
			}
			$this->debug($playerAPI, "speed=$d, isSprinting=" . $player->isSprinting());
		}
	}
}