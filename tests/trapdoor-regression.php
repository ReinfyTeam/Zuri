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

require dirname(__DIR__) . "/vendor/autoload.php";

$buildPath = $argv[1] ?? null;
if ($buildPath !== null) {
	$buildPath = realpath($buildPath);
	if ($buildPath === false) {
		throw new RuntimeException("PHAR regression build does not exist");
	}
	$phar = new Phar($buildPath);
	spl_autoload_register(static function(string $class) use ($buildPath, $phar) : void {
		if (str_starts_with($class, 'ReinfyTeam\\Zuri\\')) {
			$entry = "src/" . str_replace("\\", "/", $class) . ".php";
			if (!isset($phar[$entry])) {
				throw new RuntimeException("PHAR regression build is missing " . $entry);
			}
			require "phar://" . $buildPath . "/" . $entry;
		}
	}, true, true);
}

use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Location;
use pocketmine\event\EventPriority;
use pocketmine\event\HandlerListManager;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\plugin\PluginDescription;
use pocketmine\plugin\PluginManager;
use pocketmine\utils\Config;
use pocketmine\world\World;
use ReinfyTeam\Zuri\check\CheckRegistry;
use ReinfyTeam\Zuri\check\moving\speed\SpeedA;
use ReinfyTeam\Zuri\check\moving\speed\SpeedB;
use ReinfyTeam\Zuri\config\ConfigManager;
use ReinfyTeam\Zuri\config\ConstantValues;
use ReinfyTeam\Zuri\EventListener;
use ReinfyTeam\Zuri\player\ExternalData;
use ReinfyTeam\Zuri\player\PlayerManager;
use ReinfyTeam\Zuri\player\PlayerZuri;
use ReinfyTeam\Zuri\thread\CheckJob;
use ReinfyTeam\Zuri\thread\CheckQueue;
use ReinfyTeam\Zuri\thread\CheckResults;
use ReinfyTeam\Zuri\thread\CheckWorker;
use ReinfyTeam\Zuri\ZuriAC;

set_error_handler(static function(int $severity, string $message, string $file, int $line) : never {
	throw new ErrorException($message, 0, $severity, $file, $line);
});
$assertions = 0;
$expect = static function(bool $condition, string $message) use (&$assertions) : void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
	++$assertions;
};

if ($buildPath !== null) {
	foreach ([EventListener::class, PlayerZuri::class, SpeedA::class, SpeedB::class] as $class) {
		$expect(str_starts_with((string) (new ReflectionClass($class))->getFileName(), "phar://"), "Build regression must execute packaged code for " . $class);
	}
}

$world = new class extends World {
	public int $roofY = 67;
	public int $highestBlockQueries = 0;

	public function __construct() {
	}

	public function getHighestBlockAt(int $x, int $z) : ?int {
		++$this->highestBlockQueries;
		return $this->roofY;
	}
};
$player = new class extends Player {
	public int $teleports = 0;

	public function __construct() {
	}

	public function __destruct() {
	}

	public function getName() : string {
		return "Trapdoor Regression";
	}

	public function isConnected() : bool {
		return true;
	}

	public function hasNoClientPredictions() : bool {
		return false;
	}

	public function isFlying() : bool {
		return false;
	}

	public function isCreative(bool $literal = false) : bool {
		return false;
	}

	public function isSurvival(bool $literal = false) : bool {
		return true;
	}

	public function isSpectator() : bool {
		return false;
	}

	public function getAllowFlight() : bool {
		return false;
	}

	public function teleport(Vector3 $pos, ?float $yaw = null, ?float $pitch = null) : bool {
		++$this->teleports;
		return true;
	}
};
$plugin = (new ReflectionClass(ZuriAC::class))->newInstanceWithoutConstructor();
(new ReflectionProperty(PluginBase::class, "isEnabled"))->setValue($plugin, true);
(new ReflectionProperty(PluginBase::class, "description"))->setValue($plugin, new PluginDescription([
	"name" => "Zuri", "version" => "test", "main" => ZuriAC::class, "api" => "5.0.0"
]));
$pluginManager = (new ReflectionClass(PluginManager::class))->newInstanceWithoutConstructor();
$constants = (new ReflectionClass(ConstantValues::class))->newInstanceWithoutConstructor();
(new ReflectionProperty(ConfigManager::class, "config"))->setValue($constants, new Config(dirname(__DIR__) . "/resources/constants.yml", Config::YAML));
$queue = new CheckQueue();
$externalData = new ExternalData();
foreach (["checkRegistry" => CheckRegistry::loadChecks(), "checkQueue" => $queue, "constants" => $constants, "externalData" => $externalData] as $name => $value) {
	(new ReflectionProperty(ZuriAC::class, $name))->setValue(null, $value);
}
$tracking = PlayerManager::get($player);
$from = new Location(8.5, 64.0, 24.5, $world, 0, 0);
$to = new Location(8.5, 63.9, 24.5, $world, 0, 0);
$reset = static function() use ($tracking, $externalData, $from, $to) : void {
	$tracking->setRecentlyCancelledEvent(0.0);
	$tracking->setJoinedAtTheTime(microtime(true) - 10.0);
	$tracking->setTeleportTicks(microtime(true) - 10.0);
	$tracking->setCurrentChunkLoaded(true);
	$tracking->synchronizePositions($from);
	$tracking->setMovement($from, $to);
	$externalData->setExternalData($tracking, "Speed", "speedBBuffer", 3.9);
	$externalData->setExternalData($tracking, "Speed", "speedBVerticalBuffer", 3.9);
};
$runChecks = static function() use ($tracking, $constants) : array {
	$results = new CheckResults();
	$worker = new CheckWorker($results, 2);
	$playerData = $tracking->jsonSerialize();
	$playerData["movement"]["to"]["x"] += 5.0;
	$playerData["verticalState"] = PlayerZuri::VERTICAL_FALLING;
	$playerData["verticalError"] = 5.0;
	foreach ([SpeedA::class => "PlayerAuthInputPacket", SpeedB::class => "PlayerMoveEvent"] as $check => $type) {
		$worker->enqueue((new CheckJob($check, ["type" => $type, "playerData" => $playerData, "constantData" => $constants->exportNumbers()]))->serialize());
	}
	$output = [];
	while ($worker->processNext()) {
		$result = $results->getNextResult();
		$output[$result["check"]] = $result["result"];
	}
	return $output;
};

foreach ([67, 164] as $roofY) {
	$world->roofY = $roofY;
	foreach ([VanillaBlocks::OAK_TRAPDOOR(), VanillaBlocks::IRON_TRAPDOOR()] as $trapdoor) {
		$trapdoor->position($world, 8, 63, 24);
		foreach (["cancel-low", "cancel-highest", "deny-block-use", "allowed"] as $mode) {
			HandlerListManager::global()->unregisterAll();
			$pluginManager->registerEvents(new EventListener(), $plugin);
			$pluginManager->registerEvent(PlayerInteractEvent::class, static function(PlayerInteractEvent $event) use ($mode) : void {
				if ($mode === "deny-block-use") {
					$event->setUseBlock(false);
				} elseif ($mode !== "allowed") {
					$event->cancel();
				}
			}, $mode === "cancel-low" ? EventPriority::LOW : EventPriority::HIGHEST, $plugin);
			$reset();
			$event = new PlayerInteractEvent($player, VanillaItems::AIR(), $trapdoor, null, Facing::UP);
			$event->call();
			$denied = $mode !== "allowed";
			$expect($tracking->isRecentlyCancelledEvent() === $denied, "Issue #76: final interaction denial must be observed at " . $mode);
			$expect(($tracking->getCurrentState() === PlayerZuri::STATE_GRACE) === $denied, "Issue #76: denial must grant movement grace without exempting allowed trapdoor use");
			$expect($event->isCancelled() === str_starts_with($mode, "cancel-"), "Zuri must preserve the protection plugin's cancellation decision");
			$expect($event->useBlock() === ($mode !== "deny-block-use"), "Zuri must preserve the protection plugin's block-use decision");
			$expect($player->teleports === 0 && $world->highestBlockQueries === 0, "Issue #76: denied trapdoor use must never teleport onto a roof");
			$expect($queue->isEmpty() === $denied, "Denied interactions must not enqueue rejected movement for checks");
			while ($queue->getNextCheck() !== null) {
			}
			$results = $runChecks();
			$expect($results[SpeedA::class]["failed"] === !$denied, "SpeedA must respect denied interaction grace and retain detection afterwards");
			$expect($results[SpeedB::class]["failed"] === !$denied, "SpeedB must respect denied interaction grace and retain detection afterwards");
			if ($denied) {
				$expect($results[SpeedB::class]["externalData"]["speedBBuffer"] === 0.0 && $results[SpeedB::class]["externalData"]["speedBVerticalBuffer"] === 0.0, "Denied interactions must reset accumulated speed buffers");
				$tracking->setRecentlyCancelledEvent(microtime(true) - 3.0);
				$expect($runChecks()[SpeedB::class]["failed"] === true, "Interaction grace must expire and restore detection");
			}
		}
	}
}

HandlerListManager::global()->unregisterAll();
$pluginManager->registerEvents(new EventListener(), $plugin);
$pluginManager->registerEvent(PlayerMoveEvent::class, static function(PlayerMoveEvent $event) : void {
	$event->cancel();
}, EventPriority::HIGHEST, $plugin);
$reset();
$tracking->synchronizePositions($from);
$move = new PlayerMoveEvent($player, $from, $to);
$move->call();
$expect($move->isCancelled() && $tracking->isRecentlyCancelledEvent(), "Late movement cancellation must grant grace");
$expect($tracking->getCurrentPosition()->equals($from), "Cancelled movement must preserve the accepted position");
$expect($queue->isEmpty() && $player->teleports === 0, "Cancelled movement must not queue checks or teleport the player");
HandlerListManager::global()->unregisterAll();
PlayerManager::remove($player);
echo "Issue #76 regression (" . ($buildPath === null ? "source" : "PHAR") . "): " . $assertions . " assertions passed\n";
