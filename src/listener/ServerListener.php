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

namespace ReinfyTeam\Zuri\listener;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use ReinfyTeam\Zuri\config\ConfigManager;
use ReinfyTeam\Zuri\events\CaptchaEvent;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\Discord;

class ServerListener implements Listener {
	private array $ip = [];

	public function onPlayerJoin(PlayerJoinEvent $event) : void {
		$player = $event->getPlayer();
		$playerAPI = PlayerAPI::getAPIPlayer($player);
		Discord::Send($playerAPI, Discord::JOIN);
	}

	public function onPlayerQuit(PlayerQuitEvent $event) : void {
		$player = $event->getPlayer();
		$playerAPI = PlayerAPI::getAPIPlayer($player);
		Discord::Send($playerAPI, Discord::LEAVE);
		$ip = $player->getNetworkSession()->getIp();
		if (isset($this->ip[$ip])) {
			$this->ip[$ip]--;
		}
	}

	public function onPlayerChat(PlayerChatEvent $event) : void {
		$player = $event->getPlayer();
		$message = $event->getMessage();
		$playerAPI = PlayerAPI::getAPIPlayer($player);

		if ($playerAPI->isCaptcha()) {
			if ($message === $playerAPI->getCaptchaCode()) {
				$playerAPI->setCaptcha(false);
				$playerAPI->setCaptchaCode("nocode");
				$playerAPI->getPlayer()->sendMessage(ConfigManager::getData(ConfigManager::PREFIX) . TextFormat::GREEN . " Successfully completed the captcha!");
			}
			(new CaptchaEvent($playerAPI))->sendCaptcha();
			$event->cancel();
		}
	}

	public function onEntityDamageByEntity(EntityDamageByEntityEvent $event) : void {
		$damager = $event->getDamager();
		if (!$damager instanceof Player) {
			return;
		}
		$playerAPI = PlayerAPI::getAPIPlayer($damager);
		if ($playerAPI->isCaptcha()) {
			(new CaptchaEvent($playerAPI))->sendCaptcha();
			$event->cancel();
		}
	}

	public function onPlayerInteract(PlayerInteractEvent $event) : void {
		$player = $event->getPlayer();
		$playerAPI = PlayerAPI::getAPIPlayer($player);
		if ($playerAPI->isCaptcha()) {
			(new CaptchaEvent($playerAPI))->sendCaptcha();
			$event->cancel();
		}
	}

	public function onPlayerMove(PlayerMoveEvent $event) : void {
		$player = $event->getPlayer();
		$playerAPI = PlayerAPI::getAPIPlayer($player);
		if ($playerAPI->isCaptcha()) {
			(new CaptchaEvent($playerAPI))->sendCaptcha();
			$event->cancel();
		}
	}

	public function onBlockBreak(BlockBreakEvent $event) : void {
		$player = $event->getPlayer();
		$playerAPI = PlayerAPI::getAPIPlayer($player);
		if ($playerAPI->isCaptcha()) {
			(new CaptchaEvent($playerAPI))->sendCaptcha();
			$event->cancel();
		}
	}
}
