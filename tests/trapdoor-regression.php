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

use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\effect\EffectManager;
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
use pocketmine\world\Position;
use pocketmine\world\World;
use ReinfyTeam\Zuri\check\CheckRegistry;
use ReinfyTeam\Zuri\check\moving\speed\SpeedA;
use ReinfyTeam\Zuri\check\moving\speed\SpeedB;
use ReinfyTeam\Zuri\config\ConfigManager;
use ReinfyTeam\Zuri\config\ConstantValues;
use ReinfyTeam\Zuri\EventListener;
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
	public bool $terrainLoaded = false;

	public function __construct() {
	}

	public function getHighestBlockAt(int $x, int $z) : ?int {
		++$this->highestBlockQueries;
		return $this->roofY;
	}

	public function isInLoadedTerrain(Vector3 $pos) : bool {
		return $this->terrainLoaded;
	}

	public function getMinY() : int {
		return 0;
	}

	public function getMaxY() : int {
		return 256;
	}

	public function getBlockAt(int $x, int $y, int $z, bool $cached = true, bool $addToCache = true) : Block {
		return VanillaBlocks::AIR();
	}
};
$player = new class($world) extends Player {
	public int $teleports = 0;

	public function __construct(private World $testWorld) {
	}

	public function __destruct() {
	}

	public function getName() : string {
		return "Trapdoor Regression";
	}

	public function getWorld() : World {
		return $this->testWorld;
	}

	public function getLocation() : Location {
		return new Location(8.5, 64.0, 24.5, $this->testWorld, 0, 0);
	}

	public function getPosition() : Position {
		return $this->getLocation();
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

	public function getEffects() : EffectManager {
		return new EffectManager($this);
	}

	public function teleport(Vector3 $pos, ?float $yaw = null, ?float $pitch = null) : bool {
		++$this->teleports;
		return true;
	}
	public function getEffects() : EffectManager {
		return new EffectManager($this);
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
foreach (["checkRegistry" => CheckRegistry::loadChecks(), "checkQueue" => $queue, "constants" => $constants] as $name => $value) {
	(new ReflectionProperty(ZuriAC::class, $name))->setValue(null, $value);
}
$tracking = PlayerManager::get($player);
$from = new Location(8.5, 64.0, 24.5, $world, 0, 0);
$to = new Location(8.5, 63.8, 24.5, $world, 0, 0);
$tracking->setSwimming(true);
$tracking->setInLiquid(true);
$tracking->setOnGround(false);
$tracking->setMovement($from, new Location($from->x + 1.0, $to->y, $from->z, $world, 0, 0));
$serializedTracking = $tracking->jsonSerialize();
$expect(($serializedTracking["isSwimming"] ?? null) === true, "Player snapshots must serialize swimming state for movement checks");
$results = new CheckResults();
$worker = new CheckWorker($results, 2);
foreach ([new SpeedA(), new SpeedB()] as $check) {
	$queue->addCheck(["player" => $player, "type" => "PlayerMoveEvent"], $check);
	$serialized = $queue->getNextCheck();
	$expect($serialized !== null, "Swimming regression must use the production snapshot queue");
	$worker->enqueue($serialized);
}
while ($worker->processNext()) {
	$result = $results->getNextResult();
	$expect($result["result"]["failed"] === false, "Serialized swimming state must apply the configured movement allowance to " . $result["check"]);
}
$tracking->setSwimming(false);
$reset = static function() use ($tracking, $from, $to, $world, $player) : void {
	$tracking->setRecentlyCancelledEvent(0.0);
	$tracking->setJoinedAtTheTime(microtime(true) - 10.0);
	$tracking->setTeleportTicks(microtime(true) - 10.0);
	$world->terrainLoaded = true;
	PlayerManager::get($player);
	$tracking->setMovement($from, $to);
};
$runChecks = static function() use ($tracking, $constants) : array {
	$results = new CheckResults();
	$worker = new CheckWorker($results, 2);
	$playerData = $tracking->jsonSerialize();
	$playerData["movement"]["to"]["x"] += 5.0;
	foreach ([SpeedA::class => "PlayerMoveEvent", SpeedB::class => "PlayerMoveEvent"] as $check => $type) {
		$worker->enqueue((new CheckJob($check, ["type" => $type, "playerData" => $playerData, "constantData" => $constants->exportNumbers()]))->serialize());
	}
	$output = [];
	while ($worker->processNext()) {
		$result = $results->getNextResult();
		$output[$result["check"]] = $result["result"];
	}
	return $output;
};

foreach ([false, true, false, true] as $loaded) {
	$reset();
	$world->terrainLoaded = $loaded;
	$tracking->setMovement($from, new Location($from->x + 5.0, $from->y + 5.0, $from->z, $world, 0, 0));
	$results = new CheckResults();
	$worker = new CheckWorker($results, 2);
	foreach ([new SpeedA(), new SpeedB()] as $check) {
		$queue->addCheck(["player" => $player, "type" => "PlayerMoveEvent"], $check);
		$serialized = $queue->getNextCheck();
		$expect($serialized !== null, "Terrain regression must use the production snapshot queue");
		$job = CheckJob::unserialize($serialized);
		$expect($job->getData()["playerData"]["isCurrentChunkLoaded"] === $loaded, "Queued terrain readiness must refresh when chunks unload and reload");
		$worker->enqueue($serialized);
	}
	$world->terrainLoaded = !$loaded;
	while ($worker->processNext()) {
		$result = $results->getNextResult();
		$expect(isset($result["result"]["failed"]), "Speed check worker error: " . json_encode($result, JSON_THROW_ON_ERROR));
		$expect($result["result"]["failed"] === $loaded, "Speed checks must detect excessive movement only in the snapshot's loaded terrain");
	}
}

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
$tracking->setMovement($from, $from);
$move = new PlayerMoveEvent($player, $from, $to);
$move->call();
$expect($move->isCancelled() && $tracking->isRecentlyCancelledEvent(), "Late movement cancellation must grant grace");
$expect($tracking->getMovement()["to"]->equals($from), "Cancelled movement must preserve the accepted movement snapshot");
$expect($queue->isEmpty() && $player->teleports === 0, "Cancelled movement must not queue checks or teleport the player");
HandlerListManager::global()->unregisterAll();
PlayerManager::remove($player);
echo "Movement regression (#76 and terrain, " . ($buildPath === null ? "source" : "PHAR") . "): " . $assertions . " assertions passed\n";
