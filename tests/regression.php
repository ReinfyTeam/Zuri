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

use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\utils\Config;
use ReinfyTeam\Zuri\check\Check;
use ReinfyTeam\Zuri\check\CheckRegistry;
use ReinfyTeam\Zuri\check\MetricsData;
use ReinfyTeam\Zuri\check\moving\speed\SpeedA;
use ReinfyTeam\Zuri\check\moving\speed\SpeedB;
use ReinfyTeam\Zuri\config\ConfigManager;
use ReinfyTeam\Zuri\config\ConfigPath;
use ReinfyTeam\Zuri\config\ConstantPath;
use ReinfyTeam\Zuri\config\ConstantValues;
use ReinfyTeam\Zuri\config\language\Language;
use ReinfyTeam\Zuri\config\language\LanguagePath;
use ReinfyTeam\Zuri\EventListener;
use ReinfyTeam\Zuri\player\PlayerZuri;
use ReinfyTeam\Zuri\player\Violation;
use ReinfyTeam\Zuri\thread\CheckError;
use ReinfyTeam\Zuri\thread\CheckJob;
use ReinfyTeam\Zuri\thread\CheckResults;
use ReinfyTeam\Zuri\thread\CheckThread;
use ReinfyTeam\Zuri\thread\CheckWorker;
use ReinfyTeam\Zuri\utils\MathUtil;
use ReinfyTeam\Zuri\utils\Utils;
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

foreach ([ZuriAC::class, EventListener::class, CheckThread::class, CheckError::class] as $class) {
	$expect(class_exists($class), "Composer must autoload " . $class);
}
$expect(Utils::vector2ToArray(new Vector2(1.5, -2.0)) === ["x" => 1.5, "z" => -2.0], "Vector2 must use its Y component for movement Z");
$expect((Utils::arrayToVector2(["x" => 1.5, "z" => -2.0])) == new Vector2(1.5, -2.0), "Vector2 must round-trip");
$expect(Utils::arrayToVector3(Utils::vector3ToArray(new Vector3(1, 2, 3)))->equals(new Vector3(1, 2, 3)), "Vector3 must round-trip");
$expect(Utils::readFloat("invalid", 2.0) === 2.0, "Invalid numeric data must use the fallback");
$expect(Utils::readFloat(3) === 3.0, "Integer movement data must be accepted");
$expect(MathUtil::horizontalVelocity(0, 0, 0.25, 0, 1) === ["x" => 5.0, "z" => 0.0], "Velocity must convert blocks per tick to blocks per second");

$violations = new Violation();
$speedA = new SpeedA();
$speedB = new SpeedB();
$expect($violations->getPreViolations($speedA) === 0, "An empty violation query must not insert a scalar into the timestamp array");
$violations->addPreViolation($speedA);
$violations->addPreViolation($speedA);
$expect($violations->getPreViolations($speedA) === 2, "Pre-violations must be counted");
$expect($violations->getPreViolations($speedB) === 0, "Violation subtypes must remain separate");
$violations->resetPreViolation($speedA);
$expect($violations->getPreViolations($speedA) === 0, "Pre-violations must reset");
$violations->preViolation["Speed"]["A"] = [microtime(true) - 3.0];
$violations->addPreViolation($speedA);
$expect($violations->getPreViolations($speedA) === 1, "Expired pre-violations must be pruned");
$expect($violations->getViolations($speedA) === 0, "Empty violation queries must be safe");
$violations->addViolation($speedA);
$expect($violations->getViolations($speedA) === 1, "Violations must be counted");
$violations->resetViolation($speedA);
$expect($violations->getViolations($speedA) === 0, "Violations must reset");

$tracking = (new ReflectionClass(PlayerZuri::class))->newInstanceWithoutConstructor();
$expect($tracking->getRawMove() == new Vector2(0, 0), "Raw movement must start at a valid Vector2");
$tracking->setRecentlyCancelledEvent(microtime(true));
$expect($tracking->isRecentlyCancelledEvent(), "Recent event cancellation must be remembered");
$tracking->setRecentlyCancelledEvent(microtime(true) - 3.0);
$expect(!$tracking->isRecentlyCancelledEvent(), "Event cancellation must expire");

$config = (new ReflectionClass(ConfigManager::class))->newInstanceWithoutConstructor();
$property = new ReflectionProperty(ConfigManager::class, "config");
$property->setValue($config, new Config(dirname(__DIR__) . "/resources/config.yml", Config::YAML));
$expect($config->getString(ConfigPath::PUNISHMENT_BAN_TYPE) === "command", "Ban type must match the shipped YAML path");
$expect($config->getString(ConfigPath::PUNISHMENT_KICK_COMMAND, "", ["{player}" => '"Test Player"']) === 'kick "Test Player" Unfair Advantage', "Command placeholders must preserve player names with spaces");
$expect($config->getInt(ConfigPath::THREAD_MAX_WORKER) === 4, "Worker count must be read as an integer");
$expect($config->getInt(ConfigPath::PUNISHMENT_BAN_TYPE, 7) === 7, "Wrong configuration types must use the fallback");
$expect($config->getString(ConfigPath::CURRENT_CONFIG_VERSION) === ConfigPath::CONFIG_VERSION, "Current resource versions must not trigger a configuration replacement");
$constants = (new ReflectionClass(ConstantValues::class))->newInstanceWithoutConstructor();
$property->setValue($constants, new Config(dirname(__DIR__) . "/resources/constants.yml", Config::YAML));
$expect($constants->getNumber(ConstantPath::FRICTION_FACTOR) === 1.0, "Movement constants must match the shipped YAML path");
$expect($constants->getString("zuri.config-version") === ConstantPath::CONSTANT_VERSION, "Current constant resources must retain their configuration");
$expect($constants->exportNumbers()[ConstantPath::SPEED_THRESHOLD] === 0.1, "Worker constants must use the flat dotted keys consumed by checks");
$language = new Language(dirname(__DIR__) . "/resources/lang/en_US.yml");
$expect($language->translate(LanguagePath::KICK_MESSAGE) !== LanguagePath::KICK_MESSAGE, "Punishment translations must resolve");

$payload = ["type" => "PlayerAuthInputPacket", "playerData" => ["name" => "Test Player"], "constantData" => []];
$job = CheckJob::unserialize((new CheckJob(SpeedA::class, $payload))->serialize());
$expect($job->getCheck() === SpeedA::class && $job->getData() === $payload, "Check jobs must round-trip without PHP objects");
foreach (["broken", serialize(["check" => stdClass::class, "data" => []]), serialize(["check" => SpeedA::class, "data" => [0 => "invalid"]])] as $serialized) {
	try {
		CheckJob::unserialize($serialized);
		$expect(false, "Invalid check jobs must be rejected");
	} catch (UnexpectedValueException|ErrorException) {
		$expect(true, "Invalid check job rejected");
	}
}
$results = new CheckResults();
$worker = new CheckWorker($results, 1);
$expect($results->isEmpty() && !$worker->processNext(), "Empty queues must be safe");
$expect($worker->enqueue($job->serialize()) && !$worker->enqueue($job->serialize()), "Worker capacity must be enforced");
$expect($worker->processNext(), "Worker must process a queued job");
$result = $results->getNextResult();
$expect($result !== null && $result["player"] === "Test Player" && $result["result"]["failed"] === false, "Worker results must retain the player and check result");
$worker->enqueue(serialize(["check" => stdClass::class, "data" => []]));
$worker->processNext();
$expect(isset($results->getNextResult()["result"]["error"]["message"]), "Worker decoding failures must be returned as errors");
$expect($results->getNextResult() === null, "Result queue must drain");

$playerData = [
	"movement" => ["from" => ["x" => 0.0, "y" => 0.0, "z" => 0.0], "to" => ["x" => 0.0, "y" => 0.0, "z" => 0.0]],
	"isCurrentChunkLoaded" => true,
	"attackTicks" => 100.0, "projectileAttackTicks" => 100.0, "teleportTicks" => 100.0,
	"bowShotTicks" => 100.0, "hurtTicks" => 100.0, "teleportCommandTicks" => 100.0,
	"lastMoveTick" => 100.0, "isSurvival" => true
];
$speedPayload = ["type" => "PlayerAuthInputPacket", "playerData" => $playerData, "constantData" => $constants->exportNumbers()];
$expect(SpeedA::check($speedPayload)["failed"] === false, "Stationary movement must pass SpeedA without an undefined result flag");
$speedPayload["playerData"]["movement"]["to"]["x"] = 5.0;
$expect(SpeedA::check($speedPayload)["failed"] === true, "SpeedA must evaluate excessive survival movement using serialized vectors");
$speedPayload["playerData"]["isRecentlyCancelledEvent"] = true;
$expect(SpeedA::check($speedPayload)["failed"] === false, "Cancelled movement must receive its grace period");
$expect(SpeedB::check(["type" => "PlayerMoveEvent", "playerData" => []])["failed"] === false, "SpeedB must handle an empty movement snapshot");
$expect(SpeedB::check(["type" => "PlayerMoveEvent", "playerData" => ["currentState" => PlayerZuri::STATE_GRACE]])["externalData"]["speedBBuffer"] === 0.0, "Grace state must reset the speed buffer");
$expect(count(CheckRegistry::loadChecks()->getChecksByType(Check::TYPE_PACKET)) === 1, "Packet checks must retain their registration");
$metrics = new MetricsData();
$expect($metrics->getMaxPlayerCount() === 0, "Metrics must initialize maximum player count");
$metrics->setMaxPlayerCount(20);
$expect($metrics->getMaxPlayerCount() === 20, "Metrics must retain maximum player count");

echo "Regression tests: " . $assertions . " assertions passed\n";
