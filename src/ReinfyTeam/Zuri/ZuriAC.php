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
use ReflectionMethod;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\checks\modules\aimassist\AimAssistA;
use ReinfyTeam\Zuri\checks\modules\aimassist\AimAssistB;
use ReinfyTeam\Zuri\checks\modules\aimassist\AimAssistC;
use ReinfyTeam\Zuri\checks\modules\aimassist\AimAssistD;
use ReinfyTeam\Zuri\checks\modules\badpackets\Crasher;
use ReinfyTeam\Zuri\checks\modules\badpackets\FastDrop;
use ReinfyTeam\Zuri\checks\modules\badpackets\FastEat;
use ReinfyTeam\Zuri\checks\modules\badpackets\FastThrow;
use ReinfyTeam\Zuri\checks\modules\badpackets\ImpossiblePitch;
use ReinfyTeam\Zuri\checks\modules\badpackets\inputspoof\InputSpoofA;
use ReinfyTeam\Zuri\checks\modules\badpackets\InvalidPackets;
use ReinfyTeam\Zuri\checks\modules\badpackets\MessageSpoof;
use ReinfyTeam\Zuri\checks\modules\badpackets\regen\RegenA;
use ReinfyTeam\Zuri\checks\modules\badpackets\regen\RegenB;
use ReinfyTeam\Zuri\checks\modules\badpackets\SelfHit;
use ReinfyTeam\Zuri\checks\modules\badpackets\timer\TimerA;
use ReinfyTeam\Zuri\checks\modules\badpackets\timer\TimerB;
use ReinfyTeam\Zuri\checks\modules\badpackets\timer\TimerC;
use ReinfyTeam\Zuri\checks\modules\badpackets\timer\TimerD;
use ReinfyTeam\Zuri\checks\modules\blockbreak\InstaBreak;
use ReinfyTeam\Zuri\checks\modules\blockbreak\WrongMining;
use ReinfyTeam\Zuri\checks\modules\blockinteract\BlockReach;
use ReinfyTeam\Zuri\checks\modules\blockplace\FillBlock;
use ReinfyTeam\Zuri\checks\modules\blockplace\scaffold\ScaffoldA;
use ReinfyTeam\Zuri\checks\modules\blockplace\scaffold\ScaffoldB;
use ReinfyTeam\Zuri\checks\modules\blockplace\scaffold\ScaffoldC;
use ReinfyTeam\Zuri\checks\modules\blockplace\scaffold\ScaffoldD;
use ReinfyTeam\Zuri\checks\modules\blockplace\scaffold\ScaffoldE;
use ReinfyTeam\Zuri\checks\modules\blockplace\scaffold\ScaffoldF;
use ReinfyTeam\Zuri\checks\modules\blockplace\Tower;
use ReinfyTeam\Zuri\checks\modules\chat\SpamA;
use ReinfyTeam\Zuri\checks\modules\chat\SpamB;
use ReinfyTeam\Zuri\checks\modules\combat\autoclick\AutoClickA;
use ReinfyTeam\Zuri\checks\modules\combat\autoclick\AutoClickB;
use ReinfyTeam\Zuri\checks\modules\combat\autoclick\AutoClickC;
use ReinfyTeam\Zuri\checks\modules\combat\FastBow;
use ReinfyTeam\Zuri\checks\modules\combat\GhostHand;
use ReinfyTeam\Zuri\checks\modules\combat\Hitbox;
use ReinfyTeam\Zuri\checks\modules\combat\ImposibleHit;
use ReinfyTeam\Zuri\checks\modules\combat\ItemLerp;
use ReinfyTeam\Zuri\checks\modules\combat\killaura\KillAuraA;
use ReinfyTeam\Zuri\checks\modules\combat\killaura\KillAuraB;
use ReinfyTeam\Zuri\checks\modules\combat\killaura\KillAuraC;
use ReinfyTeam\Zuri\checks\modules\combat\killaura\KillAuraD;
use ReinfyTeam\Zuri\checks\modules\combat\killaura\KillAuraE;
use ReinfyTeam\Zuri\checks\modules\combat\reach\ReachA;
use ReinfyTeam\Zuri\checks\modules\combat\reach\ReachB;
use ReinfyTeam\Zuri\checks\modules\combat\reach\ReachC;
use ReinfyTeam\Zuri\checks\modules\combat\reach\ReachD;
use ReinfyTeam\Zuri\checks\modules\combat\reach\ReachE;
use ReinfyTeam\Zuri\checks\modules\combat\rotation\RotationA;
use ReinfyTeam\Zuri\checks\modules\combat\rotation\RotationB;
use ReinfyTeam\Zuri\checks\modules\combat\velocity\VelocityA;
use ReinfyTeam\Zuri\checks\modules\fly\FlyA;
use ReinfyTeam\Zuri\checks\modules\fly\FlyB;
use ReinfyTeam\Zuri\checks\modules\fly\FlyC;
use ReinfyTeam\Zuri\checks\modules\inventory\AutoArmor;
use ReinfyTeam\Zuri\checks\modules\inventory\ChestAura;
use ReinfyTeam\Zuri\checks\modules\inventory\ChestStealer;
use ReinfyTeam\Zuri\checks\modules\inventory\InventoryCleaner;
use ReinfyTeam\Zuri\checks\modules\inventory\InventoryMove;
use ReinfyTeam\Zuri\checks\modules\moving\AirJump;
use ReinfyTeam\Zuri\checks\modules\moving\AirMovement;
use ReinfyTeam\Zuri\checks\modules\moving\AntiImmobile;
use ReinfyTeam\Zuri\checks\modules\moving\ClickTP;
use ReinfyTeam\Zuri\checks\modules\moving\FakeGlide;
use ReinfyTeam\Zuri\checks\modules\moving\FastLadder;
use ReinfyTeam\Zuri\checks\modules\moving\Jesus;
use ReinfyTeam\Zuri\checks\modules\moving\noslow\NoSlowA;
use ReinfyTeam\Zuri\checks\modules\moving\OmniSprint;
use ReinfyTeam\Zuri\checks\modules\moving\Phase;
use ReinfyTeam\Zuri\checks\modules\moving\speed\SpeedA;
use ReinfyTeam\Zuri\checks\modules\moving\speed\SpeedB;
use ReinfyTeam\Zuri\checks\modules\moving\Spider;
use ReinfyTeam\Zuri\checks\modules\moving\Step;
use ReinfyTeam\Zuri\checks\modules\moving\WrongPitch;
use ReinfyTeam\Zuri\checks\modules\network\antibot\AntiBotA;
use ReinfyTeam\Zuri\checks\modules\network\antibot\AntiBotB;
use ReinfyTeam\Zuri\checks\modules\network\editionfaker\DeviceSpoofID;
use ReinfyTeam\Zuri\checks\modules\network\editionfaker\EditionFakerA;
use ReinfyTeam\Zuri\checks\modules\network\editionfaker\EditionFakerB;
use ReinfyTeam\Zuri\checks\modules\network\NetworkLimit;
use ReinfyTeam\Zuri\checks\modules\network\ProxyBot;
use ReinfyTeam\Zuri\command\ZuriCommand;
use ReinfyTeam\Zuri\config\ConfigManager;
use ReinfyTeam\Zuri\config\ConfigPaths;
use ReinfyTeam\Zuri\lang\Lang;
use ReinfyTeam\Zuri\lang\LangKeys;
use ReinfyTeam\Zuri\listener\PlayerListener;
use ReinfyTeam\Zuri\listener\ServerListener;
use ReinfyTeam\Zuri\network\ProxyUDPSocket;
use ReinfyTeam\Zuri\task\CaptchaTask;
use ReinfyTeam\Zuri\task\ServerTickTask;
use ReinfyTeam\Zuri\task\UpdateCheckerAsyncTask;
use ReinfyTeam\Zuri\utils\InternetAddress;
use ReinfyTeam\Zuri\utils\PermissionManager;
use vennv\vapm\VapmPMMP;
use function class_exists;
use function version_compare;

class ZuriAC extends PluginBase {
	private static ZuriAC $instance;

	private array $checks = [];
	private array $packetChecks = [];
	private array $eventChecks = [];
	private array $justEventChecks = [];

	private const string MINIMUM_PHP_VERSION = "8.4.0";

	protected function onLoad() : void {
		self::$instance = $this;
		ConfigManager::checkConfig();
		Lang::boot();

		$minimumVersion = self::MINIMUM_PHP_VERSION;
		if (version_compare(PHP_VERSION, $minimumVersion, '<')) {
			$this->getLogger()->error(Lang::get(LangKeys::STARTUP_PHP_TOO_OLD, [
				"phpVersion" => PHP_VERSION,
				"minimumVersion" => $minimumVersion,
			]));
			$this->getServer()->shutdown();
		}

		if (!Phar::running()) {
			$this->getServer()->getLogger()->notice(Lang::get(LangKeys::STARTUP_SOURCE_WARNING));
		}
	}

	public static function getInstance() : ZuriAC {
		return self::$instance;
	}

	public function reloadChecks() : void {
		$this->loadChecks();
	}

	public function rebuildCheckBuckets() : void {
		$this->buildCheckBuckets();
	}

	protected function onEnable() : void {
		if (!class_exists(VapmPMMP::class)) {
			$this->getLogger()->error(Lang::get(LangKeys::STARTUP_VAPM_MISSING));
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}

		VapmPMMP::init($this);
		$this->loadChecks();
		$this->getScheduler()->scheduleRepeatingTask(new ServerTickTask($this), 20);
		$this->getScheduler()->scheduleRepeatingTask(new CaptchaTask($this), 20);
		$this->getServer()->getAsyncPool()->submitTask(new UpdateCheckerAsyncTask($this->getDescription()->getVersion()));
		PermissionManager::getInstance()->register(ConfigManager::getData(ConfigPaths::PERMISSION_BYPASS_PERMISSION), PermissionManager::OPERATOR);
		PermissionManager::getInstance()->register(ConfigManager::getData(ConfigPaths::ALERTS_PERMISSION), PermissionManager::OPERATOR);
		$this->getServer()->getPluginManager()->registerEvents(new PlayerListener(), $this);
		$this->getServer()->getPluginManager()->registerEvents(new ServerListener(), $this);
		$this->getServer()->getCommandMap()->register("zuri", new ZuriCommand($this));
		$proxyUDPSocket = new ProxyUDPSocket();
		if (ConfigManager::getData(ConfigPaths::PROXY_ENABLE)) {
			$ip = ConfigManager::getData(ConfigPaths::PROXY_IP);
			$port = ConfigManager::getData(ConfigPaths::PROXY_PORT);
			try {
				$proxyUDPSocket->bind(new InternetAddress($ip, $port));
			} catch (Exception $exception) {
				$this->getServer()->getLogger()->notice(Lang::get(LangKeys::STARTUP_PROXY_STOPPING, ["error" => $exception->getMessage()]));
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
			$this->packetChecks = [];
			$this->eventChecks = [];
			$this->justEventChecks = [];
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

		$this->checks[] = new InputSpoofA();

		$this->checks[] = new TimerA();
		$this->checks[] = new TimerB();
		$this->checks[] = new TimerC();
		$this->checks[] = new TimerD();

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
		$this->checks[] = new ScaffoldE();
		$this->checks[] = new ScaffoldF();

		// Chat
		$this->checks[] = new SpamA();
		$this->checks[] = new SpamB();

		// Combat
		$this->checks[] = new ReachA(); // Improve in next versions..
		$this->checks[] = new ReachB(); // Improve in next versions..
		$this->checks[] = new ReachC(); // Improve in next versions..
		$this->checks[] = new ReachD(); // Improve in next versions..
		$this->checks[] = new ReachE();

		$this->checks[] = new RotationA();
		$this->checks[] = new RotationB();

		$this->checks[] = new AutoClickA(); // Improve in next versions..
		$this->checks[] = new AutoClickB(); // Improve in next versions..
		$this->checks[] = new AutoClickC(); // Improve in next versions..

		$this->checks[] = new KillAuraA();
		$this->checks[] = new KillAuraB();
		$this->checks[] = new KillAuraC();
		$this->checks[] = new KillAuraD();
		$this->checks[] = new KillAuraE();

		$this->checks[] = new ImposibleHit();

		$this->checks[] = new GhostHand();

		$this->checks[] = new Hitbox();

		$this->checks[] = new ItemLerp();

		$this->checks[] = new VelocityA();

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

		$this->checks[] = new NoSlowA();

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
		$this->checks[] = new DeviceSpoofID();

		$this->checks[] = new ProxyBot(); // Improve in next versions..

		$this->buildCheckBuckets();
	}

	private function buildCheckBuckets() : void {
		$this->packetChecks = [];
		$this->eventChecks = [];
		$this->justEventChecks = [];

		foreach ($this->checks as $check) {
			if (!$check->enable()) {
				continue;
			}

			if ($this->isMethodOverridden($check, "check")) {
				$this->packetChecks[] = $check;
			}

			if ($this->isMethodOverridden($check, "checkEvent")) {
				$this->eventChecks[] = $check;
			}

			if ($this->isMethodOverridden($check, "checkJustEvent")) {
				$this->justEventChecks[] = $check;
			}
		}
	}

	private function isMethodOverridden(Check $check, string $method) : bool {
		return (new ReflectionMethod($check, $method))->getDeclaringClass()->getName() !== Check::class;
	}

	public static function Checks() : array {
		return ZuriAC::getInstance()->checks;
	}

	public static function PacketChecks() : array {
		return ZuriAC::getInstance()->packetChecks;
	}

	public static function EventChecks() : array {
		return ZuriAC::getInstance()->eventChecks;
	}

	public static function JustEventChecks() : array {
		return ZuriAC::getInstance()->justEventChecks;
	}

	public function setCheckEnabled(string $name, ?string $subType, bool $enabled) : bool {
		$changed = false;
		foreach ($this->checks as $check) {
			if ($check->getName() !== $name) {
				continue;
			}

			if ($subType !== null && $check->getSubType() !== $subType) {
				continue;
			}

			$check->setEnabledOverride($enabled);
			$check->resetCaches();
			$changed = true;
		}

		if ($changed) {
			$this->buildCheckBuckets();
		}

		return $changed;
	}
}
