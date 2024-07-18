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

namespace ReinfyTeam\Zuri;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use ReinfyTeam\Zuri\command\ZuriCommand;
use ReinfyTeam\Zuri\config\ConfigManager;
use ReinfyTeam\Zuri\listener\PlayerListener;
use ReinfyTeam\Zuri\listener\ServerListener;
use ReinfyTeam\Zuri\network\ProxyUDPSocket;
use ReinfyTeam\Zuri\task\CaptchaTask;
use ReinfyTeam\Zuri\task\ServerTickTask;
use ReinfyTeam\Zuri\task\UpdateCheckerAsyncTask;
use ReinfyTeam\Zuri\utils\InternetAddress;
use ReinfyTeam\Zuri\utils\PermissionManager;

class ZuriAC extends PluginBase {
	private static ZuriAC $instance;
	private ProxyUDPSocket $proxyUDPSocket;

	private array $checks = [];

	public function onLoad() : void {
		self::$instance = $this;
		ConfigManager::checkConfig();

		if (!\Phar::running(true)) {
			$this->getServer()->getLogger()->notice(ConfigManager::getData(ConfigManager::PREFIX) . TextFormat::RED . " You are running source-code of the plugin, this might degrade checking performance. We recommended you to download phar plugin from poggit builds or github releases. Instead of using source-code from github.");
		}
	}

	public static function getInstance() : ZuriAC {
		return self::$instance;
	}

	public function onEnable() : void {
		$this->loadChecks();
		$this->getScheduler()->scheduleRepeatingTask(new ServerTickTask($this), 20);
		$this->getScheduler()->scheduleRepeatingTask(new CaptchaTask($this), 20);
		$this->getServer()->getAsyncPool()->submitTask(new UpdateCheckerAsyncTask($this->getDescription()->getVersion()));
		PermissionManager::getInstance()->register(ConfigManager::getData(ConfigManager::PERMISSION_BYPASS_PERMISSION), PermissionManager::OPERATOR);
		PermissionManager::getInstance()->register(ConfigManager::getData(ConfigManager::ALERTS_PERMISSION), PermissionManager::OPERATOR);
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
				$this->getServer()->getLogger()->notice(ConfigManager::getData(ConfigManager::PREFIX) . TextFormat::RED . " {$exception->getMessage()}, stopping proxy...");
				return;
			}
		}
	}

	/**
	 * Do not call internally, or do not call it double.
	 * @internal
	 */
	public function loadChecks() : void {
		if (!empty($this->checks)) {
			$this->checks = [];
		}

		if (ConfigManager::getData(ConfigManager::NETWORK_LIMIT_ENABLE)) {
			$this->checks[] = new \ReinfyTeam\Zuri\checks\network\NetworkLimit; // Required to reload the modules if modified at the game!!!
		}

		// Aim Assist
		$this->checks[] = new \ReinfyTeam\Zuri\checks\aimassist\AimAssistA();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\aimassist\AimAssistB();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\aimassist\AimAssistC();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\aimassist\AimAssistD();

		// Badpackets
		$this->checks[] = new \ReinfyTeam\Zuri\checks\badpackets\Crasher();

		$this->checks[] = new \ReinfyTeam\Zuri\checks\badpackets\FastEat();

		$this->checks[] = new \ReinfyTeam\Zuri\checks\badpackets\SelfHit();

		$this->checks[] = new \ReinfyTeam\Zuri\checks\badpackets\FastThrow();

		$this->checks[] = new \ReinfyTeam\Zuri\checks\badpackets\FastDrop();

		$this->checks[] = new \ReinfyTeam\Zuri\checks\badpackets\ImpossiblePitch();

		$this->checks[] = new \ReinfyTeam\Zuri\checks\badpackets\MessageSpoof();

		$this->checks[] = new \ReinfyTeam\Zuri\checks\badpackets\InvalidPackets();

		$this->checks[] = new \ReinfyTeam\Zuri\checks\badpackets\timer\TimerA();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\badpackets\timer\TimerB();

		$this->checks[] = new \ReinfyTeam\Zuri\checks\badpackets\regen\RegenA();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\badpackets\regen\RegenB();

		// Blockbreak
		$this->checks[] = new \ReinfyTeam\Zuri\checks\blockbreak\WrongMining();

		$this->checks[] = new \ReinfyTeam\Zuri\checks\blockbreak\InstaBreak();

		// BlockInteract
		$this->checks[] = new \ReinfyTeam\Zuri\checks\blockinteract\BlockReach();

		// BlockPlace
		$this->checks[] = new \ReinfyTeam\Zuri\checks\blockplace\FillBlock();

		$this->checks[] = new \ReinfyTeam\Zuri\checks\blockplace\Tower();

		// Chat
		$this->checks[] = new \ReinfyTeam\Zuri\checks\chat\SpamA();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\chat\SpamB();

		// Combat
		$this->checks[] = new \ReinfyTeam\Zuri\checks\combat\reach\ReachA();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\combat\reach\ReachB();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\combat\reach\ReachC();

		$this->checks[] = new \ReinfyTeam\Zuri\checks\combat\autoclick\AutoClickA();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\combat\autoclick\AutoClickB();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\combat\autoclick\AutoClickC();

		$this->checks[] = new \ReinfyTeam\Zuri\checks\combat\killaura\KillAuraA();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\combat\killaura\KillAuraB();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\combat\killaura\KillAuraC();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\combat\killaura\KillAuraD();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\combat\killaura\KillAuraE();

		$this->checks[] = new \ReinfyTeam\Zuri\checks\combat\ImposibleHit();

		$this->checks[] = new \ReinfyTeam\Zuri\checks\combat\FastBow();

		// Fly
		$this->checks[] = new \ReinfyTeam\Zuri\checks\fly\FlyA();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\fly\FlyB();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\fly\FlyC();

		// Inventory
		$this->checks[] = new \ReinfyTeam\Zuri\checks\inventory\AutoArmor();

		$this->checks[] = new \ReinfyTeam\Zuri\checks\inventory\ChestAura();

		$this->checks[] = new \ReinfyTeam\Zuri\checks\inventory\InventoryMove();

		$this->checks[] = new \ReinfyTeam\Zuri\checks\inventory\ChestStealer();

		$this->checks[] = new \ReinfyTeam\Zuri\checks\inventory\InventoryCleaner();

		// Movements
		$this->checks[] = new \ReinfyTeam\Zuri\checks\moving\WrongPitch();

		$this->checks[] = new \ReinfyTeam\Zuri\checks\moving\AirMovement();

		$this->checks[] = new \ReinfyTeam\Zuri\checks\moving\AntiImmobile();

		$this->checks[] = new \ReinfyTeam\Zuri\checks\moving\Phase();

		$this->checks[] = new \ReinfyTeam\Zuri\checks\moving\Step();

		$this->checks[] = new \ReinfyTeam\Zuri\checks\moving\OmniSprint();

		$this->checks[] = new \ReinfyTeam\Zuri\checks\moving\Jesus();

		$this->checks[] = new \ReinfyTeam\Zuri\checks\moving\Spider();

		$this->checks[] = new \ReinfyTeam\Zuri\checks\moving\FastLadder();

		$this->checks[] = new \ReinfyTeam\Zuri\checks\moving\ClickTP();

		$this->checks[] = new \ReinfyTeam\Zuri\checks\moving\Speed(); // Improve in next versions..

		// Network related
		$this->checks[] = new \ReinfyTeam\Zuri\checks\network\antibot\AntiBotA();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\network\antibot\AntiBotB();

		$this->checks[] = new \ReinfyTeam\Zuri\checks\network\EditionFaker();

		$this->checks[] = new \ReinfyTeam\Zuri\checks\network\ProxyBot();

		// Scaffold
		// Todo: Improve and add more checks in next release..
		$this->checks[] = new \ReinfyTeam\Zuri\checks\scaffold\ScaffoldA();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\scaffold\ScaffoldB();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\scaffold\ScaffoldC();
		$this->checks[] = new \ReinfyTeam\Zuri\checks\scaffold\ScaffoldD();
	}

	public static function Checks() : array {
		return ZuriAC::getInstance()->checks;
	}
}
