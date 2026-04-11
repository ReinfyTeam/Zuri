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
use pocketmine\event\server\CommandEvent;
use pocketmine\player\Player;
use pocketmine\Server;
use ReinfyTeam\Zuri\events\CaptchaEvent;
use ReinfyTeam\Zuri\events\player\PlayerTeleportByCommandEvent;
use ReinfyTeam\Zuri\lang\Lang;
use ReinfyTeam\Zuri\lang\LangKeys;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\Discord;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use ReinfyTeam\Zuri\utils\ExceptionHandler;
use function count;
use function explode;
use function ltrim;
use function str_contains;
use function strtolower;
use function trim;

/**
 * Handles non-check packetless server events such as captcha gating and join/quit hooks.
 */
class ServerListener implements Listener {
	/** @var array<string,int> */
	private array $ip = [];

	/**
	 * HACKK!! This is checking the teleport command if it is run by administrator / console.
	 * This will prevent any malicious teleportation from player. This is BC breaking until solution is polished. Because somehow pocketmine does not have any implementation for checking of teleportation cause.
	 * @See PlayerTeleportByCommandEvent::class for more details.
	 *
	 * @param CommandEvent $event Fired server command event.
	 */
	public function onCommandEvent(CommandEvent $event) : void {
		ExceptionHandler::wrapVoid(function() use ($event) : void {
			$rawCommand = trim($event->getCommand());
			if ($rawCommand === "") {
				return;
			}

			$commandArguments = explode(" ", $rawCommand);
			$commandName = strtolower(ltrim($commandArguments[0] ?? "", "/"));
			$commandSender = $event->getSender();
			if (count($commandArguments) !== 0 && ($commandName === "teleport" || $commandName === "tp")) {
				if (isset($commandArguments[1]) && str_contains($commandArguments[1], "~") && $commandSender instanceof Player) {
					(new PlayerTeleportByCommandEvent($commandSender))->call();
					return;
				} // in-game

				if (isset($commandArguments[1]) && ($player = Server::getInstance()->getPlayerByPrefix($commandArguments[1])) !== null) {
					(new PlayerTeleportByCommandEvent($player))->call();
				} // console
			}
		}, "ServerListener::onCommandEvent");
	}

	/**
	 * Sends join webhook events for online players.
	 *
	 * @param PlayerJoinEvent $event Player join event.
	 */
	public function onPlayerJoin(PlayerJoinEvent $event) : void {
		ExceptionHandler::wrapVoid(function() use ($event) : void {
			$player = $event->getPlayer();
			$playerAPI = PlayerAPI::getAPIPlayer($player);
			Discord::Send($playerAPI, Discord::JOIN);
		}, "ServerListener::onPlayerJoin");
	}

	/**
	 * Sends leave webhook events and updates per-IP join counters.
	 *
	 * @param PlayerQuitEvent $event Player quit event.
	 * @throws DiscordWebhookException
	 */
	public function onPlayerQuit(PlayerQuitEvent $event) : void {
		ExceptionHandler::wrapVoid(function() use ($event) : void {
			$player = $event->getPlayer();
			$playerAPI = PlayerAPI::getAPIPlayer($player);
			Discord::Send($playerAPI, Discord::LEAVE);
			$ip = $player->getNetworkSession()->getIp();
			if (isset($this->ip[$ip])) {
				$this->ip[$ip] -= 1;
			}
		}, "ServerListener::onPlayerQuit");
	}

	/**
	 * Handles captcha answer validation from chat messages.
	 *
	 * @param PlayerChatEvent $event Player chat event.
	 */
	public function onPlayerChat(PlayerChatEvent $event) : void {
		ExceptionHandler::wrapVoid(function() use ($event) : void {
			$player = $event->getPlayer();
			$message = $event->getMessage();
			$playerAPI = PlayerAPI::getAPIPlayer($player);

			if ($playerAPI->isCaptcha()) {
				if ($message === $playerAPI->getCaptchaCode()) {
					$playerAPI->setCaptcha(false);
					$playerAPI->setCaptchaCode("nocode");
					$playerAPI->getPlayer()->sendMessage(Lang::get(LangKeys::CAPTCHA_COMPLETED_MESSAGE));
				}
				(new CaptchaEvent($playerAPI))->call();
				$event->cancel();
			}
		}, "ServerListener::onPlayerChat");
	}

	/**
	 * Blocks combat actions while a player is in captcha verification.
	 *
	 * @param EntityDamageByEntityEvent $event Damage-by-entity event.
	 */
	public function onEntityDamageByEntity(EntityDamageByEntityEvent $event) : void {
		ExceptionHandler::wrapVoid(function() use ($event) : void {
			$damager = $event->getDamager();
			if (!$damager instanceof Player) {
				return;
			}
			$playerAPI = PlayerAPI::getAPIPlayer($damager);
			if ($playerAPI->isCaptcha()) {
				(new CaptchaEvent($playerAPI))->call();
				$event->cancel();
			}
		}, "ServerListener::onEntityDamageByEntity");
	}

	/**
	 * Cancels interaction while captcha verification is active.
	 *
	 * @param PlayerInteractEvent $event Player interaction event.
	 */
	public function onPlayerInteract(PlayerInteractEvent $event) : void {
		ExceptionHandler::wrapVoid(function() use ($event) : void {
			$player = $event->getPlayer();
			$playerAPI = PlayerAPI::getAPIPlayer($player);
			if ($playerAPI->isCaptcha()) {
				(new CaptchaEvent($playerAPI))->call();
				$event->cancel();
			}
		}, "ServerListener::onPlayerInteract");
	}

	/**
	 * Cancels movement while captcha verification is active.
	 *
	 * @param PlayerMoveEvent $event Player movement event.
	 */
	public function onPlayerMove(PlayerMoveEvent $event) : void {
		ExceptionHandler::wrapVoid(function() use ($event) : void {
			$player = $event->getPlayer();
			$playerAPI = PlayerAPI::getAPIPlayer($player);
			if ($playerAPI->isCaptcha()) {
				(new CaptchaEvent($playerAPI))->call();
				$event->cancel();
			}
		}, "ServerListener::onPlayerMove");
	}

	/**
	 * Cancels block breaking while captcha verification is active.
	 *
	 * @param BlockBreakEvent $event Block break event.
	 */
	public function onBlockBreak(BlockBreakEvent $event) : void {
		ExceptionHandler::wrapVoid(function() use ($event) : void {
			$player = $event->getPlayer();
			$playerAPI = PlayerAPI::getAPIPlayer($player);
			if ($playerAPI->isCaptcha()) {
				(new CaptchaEvent($playerAPI))->call();
				$event->cancel();
			}
		}, "ServerListener::onBlockBreak");
	}
}
