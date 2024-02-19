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

namespace ReinfyTeam\Zuri;

use pocketmine\plugin\PluginBase;
use ReinfyTeam\Zuri\command\ZuriCommand;
use ReinfyTeam\Zuri\components\IAPI;
use ReinfyTeam\Zuri\config\ConfigManager;
use ReinfyTeam\Zuri\listener\PlayerListener;
use ReinfyTeam\Zuri\listener\ServerListener;
use ReinfyTeam\Zuri\network\ProxyUDPSocket;
use ReinfyTeam\Zuri\task\CaptchaTask;
use ReinfyTeam\Zuri\task\NetworkTickTask;
use ReinfyTeam\Zuri\task\ServerTickTask;
use ReinfyTeam\Zuri\utils\InternetAddress;

class APIProvider extends PluginBase implements IAPI {
	private static APIProvider $instance;
	private ProxyUDPSocket $proxyUDPSocket;

	public const VERSION_PLUGIN = "1.1.0-BETA";

	private array $checks = [];

	public function onLoad() : void {
		self::$instance = $this;
	}

	public static function getInstance() : APIProvider {
		return self::$instance;
	}

	public function onEnable() : void {
		$this->loadChecks();
		$this->saveDefaultConfig();
		$this->saveResource("hash.txt");
		$this->getScheduler()->scheduleRepeatingTask(new ServerTickTask($this), 20);
		$this->getScheduler()->scheduleRepeatingTask(new CaptchaTask($this), 20);
		$this->getScheduler()->scheduleRepeatingTask(new NetworkTickTask($this), 100);
		$this->getServer()->getPluginManager()->registerEvents(new PlayerListener(), $this);
		$this->getServer()->getPluginManager()->registerEvents(new ServerListener(), $this);
		$this->getServer()->getCommandMap()->register("Zuri", new ZuriCommand());
		$this->proxyUDPSocket = new ProxyUDPSocket();
		if (ConfigManager::getData(ConfigManager::PROXY_ENABLE)) {
			$ip = ConfigManager::getData(ConfigManager::PROXY_IP);
			$port = ConfigManager::getData(ConfigManager::PROXY_PORT);
			try {
				$this->proxyUDPSocket->bind(new InternetAddress($ip, $port));
			} catch (\Exception $exception) {
				$this->getServer()->getLogger()->notice(ConfigManager::getData(ConfigManager::PREFIX) . " {$exception->getMessage()}, stopping proxy...");
				return;
			}
		}
	}

	private function loadChecks() : void {
		// Aim Assist
		$this->checks[] = new \ReinfyTeam\Zuri\checks\aimassist\AimAssistA();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\aimassist\AimAssistB();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\aimassist\AimAssistC();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\aimassist\AimAssistD();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\aimassist\AimAssistE();

		// Badpackets

		$this->checks[] = new \ReinfyTeam\Zuri\checks\badpackets\Crasher();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\badpackets\FastEat();

		// Blockbreak
		$this->checks[] = new \ReinfyTeam\Zuri\checks\blockbreak\WrongMining();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\blockbreak\InstaBreak();

		// BlockInteract
		$this->checks[] = new \ReinfyTeam\Zuri\checks\blockinteract\BlockReach();

		// BlockPlace
		$this->checks[] = new \ReinfyTeam\Zuri\checks\blockplace\FillBlock();

		// Chat
		$this->checks[] = new \ReinfyTeam\Zuri\checks\chat\SpamA();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\chat\SpamB();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\chat\SpamC();

		// Combat
		$this->checks[] = new \ReinfyTeam\Zuri\checks\combat\reach\ReachA();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\combat\reach\ReachB();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\combat\autoclick\AutoClickA();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\combat\autoclick\AutoClickB();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\combat\autoclick\AutoClickC();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\combat\killaura\KillAuraA();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\combat\killaura\KillAuraB();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\combat\killaura\KillAuraC();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\combat\killaura\KillAuraD();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\combat\killaura\KillAuraE();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\combat\velocity\VelocityA();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\combat\velocity\VelocityB();

		// Fly
		$this->checks[] = new \ReinfyTeam\Zuri\checks\fly\FlyA();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\fly\FlyB();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\fly\FlyC();

		// Inventory
		$this->checks[] = new \ReinfyTeam\Zuri\checks\inventory\AutoArmor();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\inventory\ChestAura();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\inventory\InventoryMove();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\inventory\ChestStealler();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\inventory\InventoryCleaner();

		// Movements
		$this->checks[] = new \ReinfyTeam\Zuri\checks\moving\WrongPitch();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\moving\AirMovement();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\moving\AntiImmobile();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\moving\Phase();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\moving\Step();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\moving\Timer();

		// Network related
		$this->checks[] = new \ReinfyTeam\Zuri\checks\network\AntiBot();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\network\EditionFaker();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\network\ProxyBot();

		// Payloads
		$this->checks[] = new \ReinfyTeam\Zuri\checks\payload\CustomPayloadA();

		// Scaffold
		$this->checks[] = new \ReinfyTeam\Zuri\checks\scaffold\ScaffoldA();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\scaffold\ScaffoldB();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\scaffold\ScaffoldC();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\scaffold\ScaffoldD();
	}

	public static function Checks() : array {
		return APIProvider::getInstance()->checks;
	}
}
