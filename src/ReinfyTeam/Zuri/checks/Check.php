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
use ReinfyTeam\Zuri\api\logging\LogManager;
use ReinfyTeam\Zuri\APIProvider;
use ReinfyTeam\Zuri\config\ConfigManager;
use ReinfyTeam\Zuri\events\BanEvent;
use ReinfyTeam\Zuri\events\KickEvent;
use ReinfyTeam\Zuri\events\ServerLagEvent;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\task\ServerTickTask;
use ReinfyTeam\Zuri\utils\ReplaceText;
use function microtime;
use function rand;
use function strtolower;

abstract class Check extends ConfigManager {
	public abstract function getName() : string;

	public abstract function getSubType() : string;

	public abstract function enable() : bool;

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
		$canCheck = self::getData(self::CHECK . "." . strtolower($this->getName()) . ".enable");
		$maxViolations = self::getData(self::CHECK . "." . strtolower($this->getName()) . ".maxvl");
		if ($canCheck !== null) {
			if ($canCheck === false) {
				return false;
			}
		}
		if (ServerTickTask::getInstance()->isLagging(microtime(true)) === true) {
			(new ServerLagEvent($playerAPI))->isLagging();
			return false;
		}
		$player = $playerAPI->getPlayer();
		$randomNumber = rand(0, 100);
		$server = $player->getServer();
		$randomizeBan = !(self::getData(self::BAN_RANDOMIZE) === true) || $randomNumber > 75;
		$randomizeCaptcha = !(self::getData(self::CAPTCHA_RANDOMIZE) === true) || $randomNumber > 75;
		$notify = self::getData(self::ALERTS_ENABLE) === true;
		$byPass = self::getData(self::PERMISSION_BYPASS_ENABLE) === true && $player->hasPermission(self::getData(self::PERMISSION_BYPASS_PERMISSION));
		$reachedMaxViolations = $playerAPI->getViolation($this->getName()) >= $this->maxViolations();
		$reachedMaxRealViolations = $playerAPI->getRealViolation($this->getName()) >= $maxViolations;
		$playerAPI->addViolation($this->getName());
		$automatic = self::getData(self::PROCESS_AUTO) === true;
		if (!$this->enable()) {
			return false;
		}

		if ($byPass) {
			return false;
		}

		if ($notify && $reachedMaxViolations) {
			$playerAPI->addRealViolation($this->getName());
			APIProvider::getInstance()->getServer()->getLogger()->info(ReplaceText::replace($playerAPI, self::getData(self::ALERTS_MESSAGE), $this->getName(), $this->getSubType()));
			foreach (APIProvider::getInstance()->getServer()->getOnlinePlayers() as $p) {
				if ($p->hasPermission("zuri.admin")) {
					$p->sendMessage(ReplaceText::replace($playerAPI, self::getData(self::ALERTS_MESSAGE), $this->getName(), $this->getSubType()));
				}
			}
		}
		if ($this->flag()) {
			$playerAPI->setFlagged(true);
			return true;
		}
		if ($automatic && $reachedMaxRealViolations && $this->ban() && $randomizeBan && self::getData(self::BAN_ENABLE) === true) {
			foreach (self::getData(self::BAN_COMMANDS) as $command) {
				$server->dispatchCommand(new ConsoleCommandSender($server, $server->getLanguage()), ReplaceText::replace($playerAPI, $command, $this->getName(), $this->getSubType()));
				APIProvider::getInstance()->getServer()->getLogger()->notice(ReplaceText::replace($playerAPI, self::getData(self::BAN_MESSAGE), $this->getName(), $this->getSubType()));
			}
			$playerAPI->resetViolation($this->getName());
			LogManager::sendLogger(ReplaceText::replace($playerAPI, self::getData(self::BAN_RECENT_LOGS_MESSAGE), $this->getName(), $this->getSubType()));
			(new BanEvent($playerAPI, $this->getName()))->ban();
			return true;
		}
		if ($reachedMaxRealViolations && $this->kick() && self::getData(self::KICK_ENABLE) === true) {
			if (self::getData(self::KICK_COMMANDS_ENABLED) === true) {
				foreach (self::getData(self::KICK_COMMANDS) as $command) {
					$server->dispatchCommand(new ConsoleCommandSender($server, $server->getLanguage()), ReplaceText::replace($playerAPI, $command, $this->getName(), $this->getSubType()));
					APIProvider::getInstance()->getServer()->getLogger()->notice(ReplaceText::replace($playerAPI, self::getData(self::KICK_MESSAGE), $this->getName(), $this->getSubType()));
					$playerAPI->resetViolation($this->getName());
				}
			} else {
				APIProvider::getInstance()->getServer()->getLogger()->info(ReplaceText::replace($playerAPI, self::getData(self::KICK_MESSAGE), $this->getName(), $this->getSubType()));
				LogManager::sendLogger(ReplaceText::replace($playerAPI, self::getData(self::KICK_RECENT_LOGS_MESSAGE), $this->getName(), $this->getSubType()));
				$player->kick("Unfair Advantage: Zuri Anticheat", null, ReplaceText::replace($playerAPI, self::getData(self::KICK_MESSAGE_UI), $this->getName(), $this->getSubType()));
				$playerAPI->resetViolation($this->getName());
			}
			(new KickEvent($playerAPI, $this->getName()))->kick(); // extra checks :D
			return true;
		}
		if ($reachedMaxRealViolations && $randomizeCaptcha && $this->captcha() && self::getData(self::CAPTCHA_ENABLE) === true) {
			$playerAPI->setCaptcha(true);
		}
		return true;
	}
}