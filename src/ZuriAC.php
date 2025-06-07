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

use Exception;
use Phar;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use ReinfyTeam\Zuri\checks\aimassist\AimAssistA;
use ReinfyTeam\Zuri\checks\aimassist\AimAssistB;
use ReinfyTeam\Zuri\checks\aimassist\AimAssistC;
use ReinfyTeam\Zuri\checks\aimassist\AimAssistD;
use ReinfyTeam\Zuri\checks\badpackets\Crasher;
use ReinfyTeam\Zuri\checks\badpackets\FastDrop;
use ReinfyTeam\Zuri\checks\badpackets\FastEat;
use ReinfyTeam\Zuri\checks\badpackets\FastThrow;
use ReinfyTeam\Zuri\checks\badpackets\ImpossiblePitch;
use ReinfyTeam\Zuri\checks\badpackets\InvalidPackets;
use ReinfyTeam\Zuri\checks\badpackets\MessageSpoof;
use ReinfyTeam\Zuri\checks\badpackets\regen\RegenA;
use ReinfyTeam\Zuri\checks\badpackets\regen\RegenB;
use ReinfyTeam\Zuri\checks\badpackets\SelfHit;
use ReinfyTeam\Zuri\checks\badpackets\timer\TimerA;
use ReinfyTeam\Zuri\checks\badpackets\timer\TimerB;
use ReinfyTeam\Zuri\checks\badpackets\timer\TimerC;
use ReinfyTeam\Zuri\checks\blockbreak\InstaBreak;
use ReinfyTeam\Zuri\checks\blockbreak\WrongMining;
use ReinfyTeam\Zuri\checks\blockinteract\BlockReach;
use ReinfyTeam\Zuri\checks\blockplace\FillBlock;
use ReinfyTeam\Zuri\checks\blockplace\scaffold\ScaffoldA;
use ReinfyTeam\Zuri\checks\blockplace\scaffold\ScaffoldB;
use ReinfyTeam\Zuri\checks\blockplace\scaffold\ScaffoldC;
use ReinfyTeam\Zuri\checks\blockplace\scaffold\ScaffoldD;
use ReinfyTeam\Zuri\checks\blockplace\Tower;
use ReinfyTeam\Zuri\checks\chat\SpamA;
use ReinfyTeam\Zuri\checks\chat\SpamB;
use ReinfyTeam\Zuri\checks\combat\autoclick\AutoClickA;
use ReinfyTeam\Zuri\checks\combat\autoclick\AutoClickB;
use ReinfyTeam\Zuri\checks\combat\autoclick\AutoClickC;
use ReinfyTeam\Zuri\checks\combat\FastBow;
use ReinfyTeam\Zuri\checks\combat\ImposibleHit;
use ReinfyTeam\Zuri\checks\combat\killaura\KillAuraA;
use ReinfyTeam\Zuri\checks\combat\killaura\KillAuraB;
use ReinfyTeam\Zuri\checks\combat\killaura\KillAuraC;
use ReinfyTeam\Zuri\checks\combat\killaura\KillAuraD;
use ReinfyTeam\Zuri\checks\combat\killaura\KillAuraE;
use ReinfyTeam\Zuri\checks\combat\reach\ReachA;
use ReinfyTeam\Zuri\checks\combat\reach\ReachB;
use ReinfyTeam\Zuri\checks\combat\reach\ReachC;
use ReinfyTeam\Zuri\checks\combat\reach\ReachD;
use ReinfyTeam\Zuri\checks\fly\FlyA;
use ReinfyTeam\Zuri\checks\fly\FlyB;
use ReinfyTeam\Zuri\checks\fly\FlyC;
use ReinfyTeam\Zuri\checks\inventory\AutoArmor;
use ReinfyTeam\Zuri\checks\inventory\ChestAura;
use ReinfyTeam\Zuri\checks\inventory\ChestStealer;
use ReinfyTeam\Zuri\checks\inventory\InventoryCleaner;
use ReinfyTeam\Zuri\checks\inventory\InventoryMove;
use ReinfyTeam\Zuri\checks\moving\AirJump;
use ReinfyTeam\Zuri\checks\moving\AirMovement;
use ReinfyTeam\Zuri\checks\moving\AntiImmobile;
use ReinfyTeam\Zuri\checks\moving\ClickTP;
use ReinfyTeam\Zuri\checks\moving\FakeGlide;
use ReinfyTeam\Zuri\checks\moving\FastLadder;
use ReinfyTeam\Zuri\checks\moving\Jesus;
use ReinfyTeam\Zuri\checks\moving\OmniSprint;
use ReinfyTeam\Zuri\checks\moving\Phase;
use ReinfyTeam\Zuri\checks\moving\speed\SpeedA;
use ReinfyTeam\Zuri\checks\moving\speed\SpeedB;
use ReinfyTeam\Zuri\checks\moving\Spider;
use ReinfyTeam\Zuri\checks\moving\Step;
use ReinfyTeam\Zuri\checks\moving\WrongPitch;
use ReinfyTeam\Zuri\checks\network\antibot\AntiBotA;
use ReinfyTeam\Zuri\checks\network\antibot\AntiBotB;
use ReinfyTeam\Zuri\checks\network\editionfaker\EditionFakerA;
use ReinfyTeam\Zuri\checks\network\editionfaker\EditionFakerB;
use ReinfyTeam\Zuri\checks\network\NetworkLimit;
use ReinfyTeam\Zuri\checks\network\ProxyBot;
use ReinfyTeam\Zuri\command\ZuriCommand;
use ReinfyTeam\Zuri\config\ConfigManager;
use ReinfyTeam\Zuri\config\ConfigPaths;
use ReinfyTeam\Zuri\listener\PlayerListener;
use ReinfyTeam\Zuri\listener\ServerListener;
use ReinfyTeam\Zuri\network\ProxyUDPSocket;
use ReinfyTeam\Zuri\task\CaptchaTask;
use ReinfyTeam\Zuri\task\ServerTickTask;
use ReinfyTeam\Zuri\task\UpdateCheckerAsyncTask;
use ReinfyTeam\Zuri\utils\InternetAddress;
use ReinfyTeam\Zuri\utils\PermissionManager;
use function version_compare;

class ZuriAC extends PluginBase {
	private static ZuriAC $instance;

	private array $checks = [];

	private const string MINIMUM_PHP_VERSION = "8.3.0";

	public function onLoad() : void {
		self::$instance = $this;
		ConfigManager::checkConfig();

		$minimumVersion = self::MINIMUM_PHP_VERSION;
		if (version_compare(PHP_VERSION, $minimumVersion, '<')) {
			$this->getLogger()->error("⚠️ You're running PHP " . PHP_VERSION . ", which is older than $minimumVersion. Please upgrade your PHP Installion to $minimumVersion or later! You may find PHP $minimumVersion builds at github.com/pmmp/PHP-Binaries/releases");
			$this->getServer()->shutdown();
		}

		if (!Phar::running()) {
			$this->getServer()->getLogger()->notice(ConfigManager::getData(ConfigPaths::PREFIX) . TextFormat::RED . " You are running source-code of the plugin, this might degrade checking performance. We recommended you to download phar plugin from Poggit builds or Github releases. Instead of using source-code from Github.");
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
		PermissionManager::getInstance()->register(ConfigManager::getData(ConfigPaths::PERMISSION_BYPASS_PERMISSION), PermissionManager::OPERATOR);
		PermissionManager::getInstance()->register(ConfigManager::getData(ConfigPaths::ALERTS_PERMISSION), PermissionManager::OPERATOR);
		$this->getServer()->getPluginManager()->registerEvents(new PlayerListener(), $this);
		$this->getServer()->getPluginManager()->registerEvents(new ServerListener(), $this);
		$this->getServer()->getCommandMap()->register("Zuri", new ZuriCommand());
		$proxyUDPSocket = new ProxyUDPSocket();
		if (ConfigManager::getData(ConfigPaths::PROXY_ENABLE)) {
			$ip = ConfigManager::getData(ConfigPaths::PROXY_IP);
			$port = ConfigManager::getData(ConfigPaths::PROXY_PORT);
			try {
				$proxyUDPSocket->bind(new InternetAddress($ip, $port));
			} catch (Exception $exception) {
				$this->getServer()->getLogger()->notice(ConfigManager::getData(ConfigPaths::PREFIX) . TextFormat::RED . " {$exception->getMessage()}, stopping proxy...");
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

		if (ConfigManager::getData(ConfigPaths::NETWORK_LIMIT_ENABLE)) {
			$this->checks[] = new NetworkLimit; // Required to reload the modules if modified at the game!!!
		}

		// Aim Assist
		$this->checks[] = new AimAssistA();
		$this->checks[] = new AimAssistB();
		$this->checks[] = new AimAssistC();
		$this->checks[] = new AimAssistD();

		// Badpackets
		$this->checks[] = new Crasher();

		$this->checks[] = new FastEat();

		$this->checks[] = new SelfHit();

		$this->checks[] = new FastThrow();

		$this->checks[] = new FastDrop();

		$this->checks[] = new ImpossiblePitch();

		$this->checks[] = new MessageSpoof();

		$this->checks[] = new InvalidPackets();

		$this->checks[] = new TimerA();
		$this->checks[] = new TimerB();
		$this->checks[] = new TimerC();

		$this->checks[] = new RegenA();
		$this->checks[] = new RegenB();

		// Blockbreak
		$this->checks[] = new WrongMining();

		$this->checks[] = new InstaBreak();

		// BlockInteract
		$this->checks[] = new BlockReach();

		// BlockPlace
		$this->checks[] = new FillBlock();

		$this->checks[] = new Tower();

		$this->checks[] = new ScaffoldA();
		$this->checks[] = new ScaffoldB();
		$this->checks[] = new ScaffoldC();
		$this->checks[] = new ScaffoldD();

		// Chat
		$this->checks[] = new SpamA();
		$this->checks[] = new SpamB();

		// Combat
		$this->checks[] = new ReachA(); // Improve in next versions..
		$this->checks[] = new ReachB(); // Improve in next versions..
		$this->checks[] = new ReachC(); // Improve in next versions..
		$this->checks[] = new ReachD(); // Improve in next versions..

		$this->checks[] = new AutoClickA(); // Improve in next versions..
		$this->checks[] = new AutoClickB(); // Improve in next versions..
		$this->checks[] = new AutoClickC(); // Improve in next versions..

		$this->checks[] = new KillAuraA();
		$this->checks[] = new KillAuraB();
		$this->checks[] = new KillAuraC();
		$this->checks[] = new KillAuraD();
		$this->checks[] = new KillAuraE();

		$this->checks[] = new ImposibleHit();

		$this->checks[] = new FastBow();

		// Fly
		$this->checks[] = new FlyA(); // Improve in next versions..
		$this->checks[] = new FlyB();
		$this->checks[] = new FlyC(); // Improve in next versions..

		// Inventory
		$this->checks[] = new AutoArmor();

		$this->checks[] = new ChestAura();

		$this->checks[] = new InventoryMove();

		$this->checks[] = new ChestStealer();

		$this->checks[] = new InventoryCleaner();

		// Movements
		$this->checks[] = new WrongPitch();

		$this->checks[] = new AirMovement();

		$this->checks[] = new AntiImmobile();

		$this->checks[] = new Phase();

		$this->checks[] = new Step();

		$this->checks[] = new OmniSprint(); // Improve in next versions..

		$this->checks[] = new Jesus();

		$this->checks[] = new Spider();

		$this->checks[] = new FastLadder();

		$this->checks[] = new ClickTP();

		$this->checks[] = new SpeedA(); // Improve in next versions.

		$this->checks[] = new SpeedB(); // Improve in next versions.

		$this->checks[] = new FakeGlide(); // Improve in next versions.

		$this->checks[] = new AirJump(); // Improve in next versions.

		// Network related
		$this->checks[] = new AntiBotA(); // Improve in next versions..
		$this->checks[] = new AntiBotB(); // Improve in next versions..

		$this->checks[] = new EditionFakerA();
		$this->checks[] = new EditionFakerB();

		$this->checks[] = new ProxyBot(); // Improve in next versions..
	}

	public static function Checks() : array {
		return ZuriAC::getInstance()->checks;
	}
}
