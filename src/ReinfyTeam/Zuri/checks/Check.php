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

namespace ReinfyTeam\Zuri\checks;

use pocketmine\console\ConsoleCommandSender;
use pocketmine\event\Event;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\utils\TextFormat;
use ReinfyTeam\Zuri\APIProvider;
use ReinfyTeam\Zuri\config\ConfigManager;
use ReinfyTeam\Zuri\events\api\CheckFailedEvent;
use ReinfyTeam\Zuri\events\BanEvent;
use ReinfyTeam\Zuri\events\ServerLagEvent;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\task\ServerTickTask;
use ReinfyTeam\Zuri\utils\ReplaceText;
use function in_array;
use function microtime;
use function strtolower;

abstract class Check extends ConfigManager {
	public bool $enabled = true;

	public abstract function getName() : string;

	public abstract function getSubType() : string;

	public function enable() : bool {
		return $this->enabled;
	}

	public abstract function ban() : bool;

	public abstract function kick() : bool;

	public abstract function flag() : bool;

	public abstract function captcha() : bool;

	public abstract function maxViolations() : int;

	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
	}

	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
	}

	public function checkJustEvent(Event $event) : void {
	}

	public function replaceText(PlayerAPI $player, string $text, string $reason = "", string $subType = "") : string {
		return ReplaceText::replace($player, $text, $reason, $subType);
	}

	public function failed(PlayerAPI $playerAPI) : bool {
		if (($canCheck = self::getData(self::CHECK . "." . strtolower($this->getName()) . ".enable")) !== null) {
			if ($canCheck === false) {
				return false;
			}
		}

		if (ServerTickTask::getInstance()->isLagging(microtime(true)) === true) {
			(new ServerLagEvent($playerAPI))->isLagging();
			return false;
		}

		if (!$this->enable()) {
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
		$server = APIProvider::getInstance()->getServer();

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
			APIProvider::getInstance()->getServer()->getLogger()->info(ReplaceText::replace($playerAPI, self::getData(self::ALERTS_MESSAGE), $this->getName(), $this->getSubType()));
			foreach (APIProvider::getInstance()->getServer()->getOnlinePlayers() as $p) {
				if ($p->hasPermission("zuri.admin")) {
					$p->sendMessage(ReplaceText::replace($playerAPI, self::getData(self::ALERTS_MESSAGE), $this->getName(), $this->getSubType()));
				}
			}
		} else {
			if ($detectionsAllowedToSend) {
				APIProvider::getInstance()->getServer()->getLogger()->info(ReplaceText::replace($playerAPI, self::getData(self::DETECTION_MESSAGE), $this->getName(), $this->getSubType()));
				foreach (APIProvider::getInstance()->getServer()->getOnlinePlayers() as $p) {
					if ($p->hasPermission("zuri.admin")) {
						$p->sendMessage(ReplaceText::replace($playerAPI, self::getData(self::DETECTION_MESSAGE), $this->getName(), $this->getSubType()));
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

		if ($this->flag()) {
			$playerAPI->setFlagged(true);
			return true;
		}

		if ($reachedMaxRealViolations && $reachedMaxViolations && $this->ban() && self::getData(self::BAN_ENABLE) === true) {
			APIProvider::getInstance()->getServer()->getLogger()->notice(ReplaceText::replace($playerAPI, self::getData(self::BAN_MESSAGE), $this->getName(), $this->getSubType()));
			foreach (APIProvider::getInstance()->getServer()->getOnlinePlayers() as $p) {
				if ($p->hasPermission("zuri.admin")) {
					$p->sendMessage(ReplaceText::replace($playerAPI, self::getData(self::BAN_MESSAGE), $this->getName(), $this->getSubType()));
				}
			}
			foreach (self::getData(self::BAN_COMMANDS) as $command) {
				$server->dispatchCommand(new ConsoleCommandSender($server, $server->getLanguage()), ReplaceText::replace($playerAPI, $command, $this->getName(), $this->getSubType()));
			}

			$playerAPI->resetViolation($this->getName());
			$playerAPI->resetRealViolation($this->getName());
			(new BanEvent($playerAPI, $this->getName()))->ban();
			return true;
		}

		if ($reachedMaxRealViolations && $reachedMaxViolations && $this->kick() && self::getData(self::KICK_ENABLE) === true) {
			if (self::getData(self::KICK_COMMANDS_ENABLED) === true) {
				APIProvider::getInstance()->getServer()->getLogger()->notice(ReplaceText::replace($playerAPI, self::getData(self::KICK_MESSAGE), $this->getName(), $this->getSubType()));
				$playerAPI->resetViolation($this->getName());
				$playerAPI->resetRealViolation($this->getName());
				foreach (APIProvider::getInstance()->getServer()->getOnlinePlayers() as $p) {
					if ($p->hasPermission("zuri.admin")) {
						$p->sendMessage(ReplaceText::replace($playerAPI, self::getData(self::KICK_MESSAGE), $this->getName(), $this->getSubType()));
					}
				}
				foreach (self::getData(self::KICK_COMMANDS) as $command) {
					$server->dispatchCommand(new ConsoleCommandSender($server, $server->getLanguage()), ReplaceText::replace($playerAPI, $command, $this->getName(), $this->getSubType()));
				}
			} else {
				APIProvider::getInstance()->getServer()->getLogger()->notice(ReplaceText::replace($playerAPI, self::getData(self::KICK_MESSAGE), $this->getName(), $this->getSubType()));
				foreach (APIProvider::getInstance()->getServer()->getOnlinePlayers() as $p) {
					if ($p->hasPermission("zuri.admin")) {
						$p->sendMessage(ReplaceText::replace($playerAPI, self::getData(self::KICK_MESSAGE), $this->getName(), $this->getSubType()));
					}
				}
				$playerAPI->resetViolation($this->getName());
				$playerAPI->resetRealViolation($this->getName());
				$player->kick("Unfair Advantage: Zuri Anticheat", null, ReplaceText::replace($playerAPI, self::getData(self::KICK_MESSAGE_UI), $this->getName(), $this->getSubType()));
				return true;
			}
		}

		if ($reachedMaxRealViolations && $this->captcha() && self::getData(self::CAPTCHA_ENABLE) === true) {
			$playerAPI->setCaptcha(true);
			return true;
		}


		return false;
	}
	
	public function warning(string $username) : void {
		
		if(!self::getData(self::WARNING_ENABLE)) {
			return;
		}
		
		APIProvider::getInstance()->getServer()->getLogger()->info(ReplaceText::replace($username, self::getData(self::WARNING_MESSAGE), $this->getName(), $this->getSubType()));
		foreach (APIProvider::getInstance()->getServer()->getOnlinePlayers() as $p) {
			if ($p->hasPermission("zuri.admin")) {
				$p->sendMessage(ReplaceText::replace($username, self::getData(self::WARNING_MESSAGE), $this->getName(), $this->getSubType()));
			}
		}
	}

	public function debug(PlayerAPI $playerAPI, string $text) : void {
		$player = $playerAPI->getPlayer();

		if ($playerAPI->isDebug()) {
			$player->sendMessage(self::getData(self::PREFIX) . " " . TextFormat::GRAY . "[DEBUG] " . TextFormat::RED . $this->getName() . TextFormat::GRAY . " (" . TextFormat::YELLOW . $this->getSubType() . TextFormat::GRAY . ") " . TextFormat::AQUA . $text);
		}
	}
}