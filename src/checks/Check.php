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

namespace ReinfyTeam\Zuri\checks;

use pocketmine\console\ConsoleCommandSender;
use pocketmine\event\Event;
use pocketmine\network\mcpe\protocol\Packet;
use pocketmine\utils\TextFormat;
use ReinfyTeam\Zuri\config\ConfigManager;
use ReinfyTeam\Zuri\events\api\CheckFailedEvent;
use ReinfyTeam\Zuri\events\BanEvent;
use ReinfyTeam\Zuri\events\KickEvent;
use ReinfyTeam\Zuri\events\ServerLagEvent;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\task\ServerTickTask;
use ReinfyTeam\Zuri\utils\ReplaceText;
use ReinfyTeam\Zuri\ZuriAC;
use function implode;
use function in_array;
use function microtime;
use function strtolower;

abstract class Check extends ConfigManager {
	abstract public function getName() : string;

	abstract public function getSubType() : string;

	abstract public function maxViolations() : int;

	public function check(Packet $packet, PlayerAPI $playerAPI) : void {
	}

	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
	}

	public function checkJustEvent(Event $event) : void {
	}

	public function replaceText(PlayerAPI $player, string $text, string $reason = "", string $subType = "") : string {
		return ReplaceText::replace($player, $text, $reason, $subType);
	}

	public function getPunishment() : string {
		return strtolower(self::getData(self::CHECK . "." . strtolower($this->getName()) . ".punishment", "FLAG"));
	}

	public function enable() : bool {
		return self::getData(self::CHECK . "." . strtolower($this->getName()) . ".enable", false);
	}

	public function getConstant(string $name) : mixed {
		return self::getData(self::CHECK . "." . strtolower($this->getName()) . ".constants." . $name, null);
	}

	public function getAllSubTypes() : string {
		$list = [];
		foreach (ZuriAC::getChecks() as $check) {
			if ($check->getName() === $this->getName() && !in_array($check->getSubType(), $list, true)) {
				$list[] = $check->getSubType();
			}
		}
		return implode(", ", $list);
	}

	/**
	 * When multiple attempts of violations is within limit of < 0.5s.
	 * @internal
	 */
	public function failed(PlayerAPI $playerAPI) : bool {
		if (!$this->enable()) {
			return false;
		}

		if (ServerTickTask::getInstance()->isLagging(microtime(true)) === true) {
			(new ServerLagEvent($playerAPI))->call();
			return false;
		}

		$player = $playerAPI->getPlayer();

		$notify = self::getData(self::ALERTS_ENABLE) === true;
		$detectionsAllowedToSend = self::getData(self::DETECTION_ENABLE) === true;
		$bypass = self::getData(self::PERMISSION_BYPASS_ENABLE) === true && $player->hasPermission(self::getData(self::PERMISSION_BYPASS_PERMISSION));
		$reachedMaxViolations = $playerAPI->getViolation($this->getName()) > $this->maxViolations();
		$maxViolations = self::getData(self::CHECK . "." . strtolower($this->getName()) . ".maxvl");
		$playerAPI->addViolation($this->getName());
		$reachedMaxRealViolations = $playerAPI->getRealViolation($this->getName()) > $maxViolations;
		$server = ZuriAC::getInstance()->getServer();

		if (self::getData(self::WORLD_BYPASS_ENABLE) === true) {
			if (strtolower(self::getData(self::WORLD_BYPASS_MODE)) === "blacklist") {
				if (in_array($player->getWorld()->getFolderName(), self::getData(self::WORLD_BYPASS_LIST), true)) {
					return false;
				}
			} else {
				if (!in_array($player->getWorld()->getFolderName(), self::getData(self::WORLD_BYPASS_LIST), true)) {
					return false;
				}
			}
		}


		$checkEvent = new CheckFailedEvent($playerAPI, $this->getName(), $this->getSubType());
		$checkEvent->call();
		if ($checkEvent->isCancelled()) {
			return false;
		}

		if ($reachedMaxViolations) {
			$playerAPI->addRealViolation($this->getName());
			$message = ReplaceText::replace($playerAPI, self::getData(self::ALERTS_MESSAGE), $this->getName(), $this->getSubType());
			ZuriAC::getInstance()->getServer()->getLogger()->info($message);
			foreach (ZuriAC::getInstance()->getServer()->getOnlinePlayers() as $p) {
				if ($p->hasPermission("zuri.admin")) {
					$p->sendMessage($message);
				}
			}
		} else {
			if ($detectionsAllowedToSend) {
				$message = ReplaceText::replace($playerAPI, self::getData(self::DETECTION_MESSAGE), $this->getName(), $this->getSubType());
				ZuriAC::getInstance()->getServer()->getLogger()->info($message);
				foreach (ZuriAC::getInstance()->getServer()->getOnlinePlayers() as $p) {
					if ($p->hasPermission("zuri.admin")) {
						$p->sendMessage($message);
					}
				}
			}
		}

		if ($bypass) {
			return false;
		}

		if ($playerAPI->isDebug()) {
			return false;
		}

		if ($this->getPunishment() === "flag") {
			$playerAPI->setFlagged(true);
			return true;
		}

		if ($reachedMaxRealViolations && $reachedMaxViolations && $this->getPunishment() === "ban" && self::getData(self::BAN_ENABLE) === true) {
			(new BanEvent($playerAPI, $this->getName(), $this->getSubType()))->call();
			ZuriAC::getInstance()->getServer()->getLogger()->notice(ReplaceText::replace($playerAPI, self::getData(self::BAN_MESSAGE), $this->getName(), $this->getSubType()));
			foreach (ZuriAC::getInstance()->getServer()->getOnlinePlayers() as $p) {
				if ($p->hasPermission("zuri.admin")) {
					$p->sendMessage(ReplaceText::replace($playerAPI, self::getData(self::BAN_MESSAGE), $this->getName(), $this->getSubType()));
				}
			}
			foreach (self::getData(self::BAN_COMMANDS) as $command) {
				$server->dispatchCommand(new ConsoleCommandSender($server, $server->getLanguage()), ReplaceText::replace($playerAPI, $command, $this->getName(), $this->getSubType()));
			}

			$playerAPI->resetViolation($this->getName());
			$playerAPI->resetRealViolation($this->getName());
			return true;
		}

		if ($reachedMaxRealViolations && $reachedMaxViolations && $this->getPunishment() === "kick" && self::getData(self::KICK_ENABLE) === true) {
			(new KickEvent($playerAPI, $this->getName(), $this->getSubType()))->call();
			if (self::getData(self::KICK_COMMANDS_ENABLED) === true) {
				ZuriAC::getInstance()->getServer()->getLogger()->notice(ReplaceText::replace($playerAPI, self::getData(self::KICK_MESSAGE), $this->getName(), $this->getSubType()));
				$playerAPI->resetViolation($this->getName());
				$playerAPI->resetRealViolation($this->getName());
				foreach (ZuriAC::getInstance()->getServer()->getOnlinePlayers() as $p) {
					if ($p->hasPermission("zuri.admin")) {
						$p->sendMessage(ReplaceText::replace($playerAPI, self::getData(self::KICK_MESSAGE), $this->getName(), $this->getSubType()));
					}
				}
				foreach (self::getData(self::KICK_COMMANDS) as $command) {
					$server->dispatchCommand(new ConsoleCommandSender($server, $server->getLanguage()), ReplaceText::replace($playerAPI, $command, $this->getName(), $this->getSubType()));
				}
			} else {
				ZuriAC::getInstance()->getServer()->getLogger()->notice(ReplaceText::replace($playerAPI, self::getData(self::KICK_MESSAGE), $this->getName(), $this->getSubType()));
				foreach (ZuriAC::getInstance()->getServer()->getOnlinePlayers() as $p) {
					if ($p->hasPermission("zuri.admin")) {
						$p->sendMessage(ReplaceText::replace($playerAPI, self::getData(self::KICK_MESSAGE), $this->getName(), $this->getSubType()));
					}
				}
				$playerAPI->resetViolation($this->getName());
				$playerAPI->resetRealViolation($this->getName());
				$player->kick("Unfair Advantage: Zuri Anticheat" /** TODO: Customize logout message? */, null, ReplaceText::replace($playerAPI, self::getData(self::KICK_MESSAGE_UI), $this->getName(), $this->getSubType()));
			}
			return true;
		}

		if ($reachedMaxRealViolations && $this->getPunishment() === "captcha" && self::getData(self::CAPTCHA_ENABLE) === true) {
			$playerAPI->setCaptcha(true);
			return true;
		}


		return false;
	}

	/**
	 * For Login purposes warning system only!
	 * @internal
	 */
	public function warn(string $username) : void {
		if (!self::getData(self::WARNING_ENABLE)) {
			return;
		}

		ZuriAC::getInstance()->getServer()->getLogger()->info(ReplaceText::replace($username, self::getData(self::WARNING_MESSAGE), $this->getName(), $this->getSubType()));
		foreach (ZuriAC::getInstance()->getServer()->getOnlinePlayers() as $p) {
			if ($p->hasPermission("zuri.admin")) {
				$p->sendMessage(ReplaceText::replace($username, self::getData(self::WARNING_MESSAGE), $this->getName(), $this->getSubType()));
			}
		}
	}

	/**
	 * Developers: Debugger for Anticheat
	 * @internal
	 */
	public function debug(PlayerAPI $playerAPI, string $text) : void {
		$player = $playerAPI->getPlayer();

		if (self::getData(self::DEBUG_ENABLE)) {
			if ($playerAPI->isDebug()) {
				$player->sendMessage(self::getData(self::PREFIX) . " " . TextFormat::GRAY . "[DEBUG] " . TextFormat::RED . $this->getName() . TextFormat::GRAY . " (" . TextFormat::YELLOW . $this->getSubType() . TextFormat::GRAY . ") " . TextFormat::AQUA . $text);

				if (self::getData(self::DEBUG_LOG_SERVER)) {
					ZuriAC::getInstance()->getServer()->getLogger()->notice(self::getData(self::PREFIX) . " " . TextFormat::GRAY . "[DEBUG] " . TextFormat::YELLOW . $playerAPI->getPlayer()->getName() . ": " . TextFormat::RED . $this->getName() . TextFormat::GRAY . " (" . TextFormat::YELLOW . $this->getSubType() . TextFormat::GRAY . ") " . TextFormat::AQUA . $text);
				}

				if (self::getData(self::DEBUG_LOG_ADMIN)) {
					foreach (ZuriAC::getInstance()->getServer()->getOnlinePlayers() as $p) {
						if ($p->getName() === $playerAPI->getPlayer()->getName()) {
							continue;
						} // Skip same player. Prevent spam in the chat history.
						if ($p->hasPermission("zuri.admin")) {
							$p->sendMessage(self::getData(self::PREFIX) . " " . TextFormat::GRAY . "[DEBUG] " . TextFormat::YELLOW . $playerAPI->getPlayer()->getName() . ": " . TextFormat::RED . $this->getName() . TextFormat::GRAY . " (" . TextFormat::YELLOW . $this->getSubType() . TextFormat::GRAY . ") " . TextFormat::AQUA . $text);
						}
					}
				}
			}
		}
	}
}
