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
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use ReinfyTeam\Zuri\config\ConfigManager;
use ReinfyTeam\Zuri\events\api\CheckFailedEvent;
use ReinfyTeam\Zuri\events\BanEvent;
use ReinfyTeam\Zuri\events\KickEvent;
use ReinfyTeam\Zuri\events\ServerLagEvent;
use ReinfyTeam\Zuri\lang\Lang;
use ReinfyTeam\Zuri\lang\LangKeys;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\task\CheckAsyncTask;
use ReinfyTeam\Zuri\task\ServerTickTask;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use ReinfyTeam\Zuri\utils\ReplaceText;
use ReinfyTeam\Zuri\ZuriAC;
use function implode;
use function in_array;
use function max;
use function microtime;
use function min;
use function round;
use function strtolower;

abstract class Check extends ConfigManager {
	/** @var array<string, float> */
	private static array $asyncThrottle = [];
	private static float $lastAsyncThrottleCleanup = 0.0;

	private ?bool $enabledOverride = null;
	private ?bool $enabledCache = null;
	private ?string $punishmentCache = null;
	private ?int $maxViolationsCache = null;
	private array $constantCache = [];

	public abstract function getName() : string;

	public abstract function getSubType() : string;

	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
	}

	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
	}

	public function checkJustEvent(Event $event) : void {
	}

	public static function evaluateAsync(array $payload) : array {
		if (($payload["_type"] ?? null) === "__decision") {
			return (array) ($payload["result"] ?? []);
		}

		return [];
	}

	protected function dispatchAsyncCheck(string $playerName, array $payload) : void {
		$player = Server::getInstance()->getPlayerExact($playerName);
		if ($player === null || !$player->isOnline() || !$player->isConnected()) {
			return;
		}

		CheckAsyncTask::configure(
			(int) self::getData("zuri.async.max-concurrent-workers", 4),
			(int) self::getData("zuri.async.max-queue-size", 2048),
			(float) self::getData("zuri.async.worker-timeout-seconds", 3.0),
			(float) self::getData("zuri.async.degraded-cooldown-seconds", 6.0)
		);

		$now = microtime(true);
		if ((self::$lastAsyncThrottleCleanup === 0.0) || (($now - self::$lastAsyncThrottleCleanup) >= 60.0)) {
			self::$lastAsyncThrottleCleanup = $now;
			foreach (self::$asyncThrottle as $throttleKey => $expiresAt) {
				if ($expiresAt <= $now) {
					unset(self::$asyncThrottle[$throttleKey]);
				}
			}
		}

		$key = $playerName . ":" . static::class;
		$minInterval = (float) ($payload["_minInterval"] ?? self::getData("zuri.async.default-min-interval", 0.02));
		if (isset($payload["_minInterval"])) {
			unset($payload["_minInterval"]);
		}

		if ((self::$asyncThrottle[$key] ?? 0.0) > $now) {
			return;
		}

		$playerAPI = PlayerAPI::getAPIPlayer($player);
		$sequence = $playerAPI->nextAsyncSequence(static::class);
		$payload["_sequence"] = $sequence;
		$payload["_checkClass"] = static::class;

		self::$asyncThrottle[$key] = $now + $minInterval;
		CheckAsyncTask::dispatch(static::class, $playerName, $payload, $sequence);
	}

	protected function dispatchAsyncDecision(
		PlayerAPI $playerAPI,
		bool $failed = false,
		string $debug = "",
		array $set = [],
		array $unset = [],
		float $minInterval = 0.0
	) : void {
		$result = [];
		if ($set !== []) {
			$result["set"] = $set;
		}
		if ($unset !== []) {
			$result["unset"] = $unset;
		}
		if ($debug !== "") {
			$result["debug"] = $debug;
		}
		if ($failed) {
			$result["failed"] = true;
		}

		$payload = [
			"_type" => "__decision",
			"result" => $result,
		];
		if ($minInterval > 0.0) {
			$payload["_minInterval"] = $minInterval;
		}

		$this->dispatchAsyncCheck($playerAPI->getPlayer()->getName(), $payload);
	}

	public function replaceText(PlayerAPI $player, string $text, string $reason = "", string $subType = "") : string {
		return ReplaceText::replace($player, $text, $reason, $subType);
	}

	public function getPunishment() : string {
		return $this->punishmentCache ??= strtolower(self::getData(self::CHECK . "." . strtolower($this->getName()) . ".punishment", "FLAG"));
	}

	public function setEnabledOverride(?bool $enabled) : void {
		$this->enabledOverride = $enabled;
		$this->enabledCache = null;
	}

	public function getEnabledOverride() : ?bool {
		return $this->enabledOverride;
	}

	public function resetCaches() : void {
		$this->enabledCache = null;
		$this->punishmentCache = null;
		$this->maxViolationsCache = null;
		$this->constantCache = [];
	}

	public function enable() : bool {
		if ($this->enabledOverride !== null) {
			return $this->enabledOverride;
		}
		return $this->enabledCache ??= self::getData(self::CHECK . "." . strtolower($this->getName()) . ".enable", false);
	}

	public function maxViolations() : int {
		return $this->maxViolationsCache ??= self::getData(self::CHECK . "." . strtolower($this->getName()) . ".pre-vl." . strtolower($this->getSubType()), false);
	}

	public function getConstant(string $name) : mixed {
		if (isset($this->constantCache[$name])) {
			return $this->constantCache[$name];
		}
		return $this->constantCache[$name] = self::getData(self::CHECK . "." . strtolower($this->getName()) . ".constants." . $name);
	}

	public function getAllSubTypes() : string {
		$list = [];
		foreach (ZuriAC::Checks() as $check) {
			if ($check->getName() === $this->getName() && !in_array($check->getSubType(), $list, true)) {
				$list[] = $check->getSubType();
			}
		}
		return implode(", ", $list);
	}

	/**
	 * When multiple attempts of violations is within limit of < 0.5s.
	 *
	 * @throws DiscordWebhookException
	 * @internal
	 */
	public function failed(PlayerAPI $playerAPI) : bool {
		if (!$this->enable()) {
			return false;
		}

		// FP Cooldown: Skip checks during lag, teleport, or world transfer windows
		if ($playerAPI->isInFPCooldown()) {
			return false;
		}

		if (ServerTickTask::getInstance()?->isLagging(microtime(true)) === true) {
			// Set lag cooldown for all online players
			foreach (ZuriAC::getInstance()->getServer()->getOnlinePlayers() as $onlinePlayer) {
				$api = PlayerAPI::getAPIPlayer($onlinePlayer);
				$api->setLastLagSpike(microtime(true));
			}
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
			ZuriAC::getInstance()->getServer()->getLogger()->info(ReplaceText::replace($playerAPI, Lang::raw(LangKeys::ALERTS_MESSAGE), $this->getName(), $this->getSubType()));
			foreach (ZuriAC::getInstance()->getServer()->getOnlinePlayers() as $p) {
				if ($p->hasPermission("zuri.admin")) {
					$p->sendMessage(ReplaceText::replace($playerAPI, Lang::raw(LangKeys::ALERTS_MESSAGE), $this->getName(), $this->getSubType()));
				}
			}
		} else {
			if ($detectionsAllowedToSend) {
				ZuriAC::getInstance()->getServer()->getLogger()->info(ReplaceText::replace($playerAPI, Lang::raw(LangKeys::DETECTION_MESSAGE), $this->getName(), $this->getSubType()));
				foreach (ZuriAC::getInstance()->getServer()->getOnlinePlayers() as $p) {
					if ($p->hasPermission("zuri.admin")) {
						$p->sendMessage(ReplaceText::replace($playerAPI, Lang::raw(LangKeys::DETECTION_MESSAGE), $this->getName(), $this->getSubType()));
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
			ZuriAC::getInstance()->getServer()->getLogger()->notice(ReplaceText::replace($playerAPI, Lang::raw(LangKeys::BAN_MESSAGE), $this->getName(), $this->getSubType()));
			foreach (ZuriAC::getInstance()->getServer()->getOnlinePlayers() as $p) {
				if ($p->hasPermission("zuri.admin")) {
					$p->sendMessage(ReplaceText::replace($playerAPI, Lang::raw(LangKeys::BAN_MESSAGE), $this->getName(), $this->getSubType()));
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
			ZuriAC::getInstance()->getServer()->getLogger()->notice(ReplaceText::replace($playerAPI, Lang::raw(LangKeys::KICK_MESSAGE), $this->getName(), $this->getSubType()));
			if (self::getData(self::KICK_COMMANDS_ENABLED) === true) {
				$playerAPI->resetViolation($this->getName());
				$playerAPI->resetRealViolation($this->getName());
				foreach (ZuriAC::getInstance()->getServer()->getOnlinePlayers() as $p) {
					if ($p->hasPermission("zuri.admin")) {
						$p->sendMessage(ReplaceText::replace($playerAPI, Lang::raw(LangKeys::KICK_MESSAGE), $this->getName(), $this->getSubType()));
					}
				}
				foreach (self::getData(self::KICK_COMMANDS) as $command) {
					$server->dispatchCommand(new ConsoleCommandSender($server, $server->getLanguage()), ReplaceText::replace($playerAPI, $command, $this->getName(), $this->getSubType()));
				}
			} else {
				foreach (ZuriAC::getInstance()->getServer()->getOnlinePlayers() as $p) {
					if ($p->hasPermission("zuri.admin")) {
						$p->sendMessage(ReplaceText::replace($playerAPI, Lang::raw(LangKeys::KICK_MESSAGE), $this->getName(), $this->getSubType()));
					}
				}
				$playerAPI->resetViolation($this->getName());
				$playerAPI->resetRealViolation($this->getName());
				$player->kick(Lang::get(LangKeys::KICK_DISCONNECT_REASON), null, ReplaceText::replace($playerAPI, Lang::raw(LangKeys::KICK_UI_MESSAGE), $this->getName(), $this->getSubType()));
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
	 * Report a violation with confidence scoring instead of binary pass/fail.
	 *
	 * This method allows checks to report violations with a confidence level,
	 * enabling more nuanced detection that reduces false positives.
	 *
	 * @param PlayerAPI $playerAPI The player being checked
	 * @param float $baseConfidence Base confidence (0.0-1.0) from detection logic
	 * @param string $debugInfo Optional debug information
	 * @return bool Whether the violation was processed (true) or suppressed (false)
	 * @throws DiscordWebhookException
	 */
	public function failedWithConfidence(PlayerAPI $playerAPI, float $baseConfidence = 0.6, string $debugInfo = "") : bool {
		// Clamp confidence to valid range
		$baseConfidence = max(0.0, min(1.0, $baseConfidence));

		// Create violation result with confidence scoring
		$result = new ViolationResult($this->getName(), $this->getSubType(), $baseConfidence, $debugInfo);

		// Apply contextual factors
		$player = $playerAPI->getPlayer();
		$result->applyPingFactor($player->getNetworkSession()->getPing() ?? 0);
		$result->applyOnlineTimeFactor($playerAPI->getOnlineTime());
		$result->applyRepeatFactor($playerAPI->getViolation($this->getName()));
		$result->applyEnvironmentFactor(
			$playerAPI->isCurrentChunkIsLoaded(),
			$playerAPI->getTeleportTicks() < 60,
			$playerAPI->getHurtTicks() < 20,
			ServerTickTask::getInstance()->isLagging(microtime(true))
		);

		// Get threshold from config (default 0.5 = medium confidence required)
		$threshold = (float) self::getData("zuri.confidence.threshold", 0.5);
		$finalConfidence = $result->getConfidence();

		// Track confidence score for trending analysis
		$playerAPI->addConfidenceScore($this->getName(), $finalConfidence);

		// Debug output if enabled
		if ($playerAPI->isDebug()) {
			$this->debug($playerAPI, "Confidence: " . $result->getConfidencePercent() . "% (threshold: " . round($threshold * 100) . "%) " . $debugInfo);
			return false;
		}

		// If confidence doesn't meet threshold, suppress the violation
		if ($finalConfidence < $threshold) {
			// Low confidence - log for analysis but don't trigger violation
			if (self::getData(self::DETECTION_ENABLE) === true && $finalConfidence >= 0.3) {
				// Only show detections for borderline cases (30-50% confidence)
				$message = ReplaceText::replace($playerAPI, Lang::raw(LangKeys::DETECTION_MESSAGE), $this->getName(), $this->getSubType());
				$message .= TextFormat::GRAY . " [" . $result->getConfidencePercent() . "% confidence]";
				foreach (ZuriAC::getInstance()->getServer()->getOnlinePlayers() as $p) {
					if ($p->hasPermission("zuri.admin")) {
						$p->sendMessage($message);
					}
				}
			}
			return false;
		}

		// High confidence - proceed with normal violation handling
		return $this->failed($playerAPI);
	}

	/**
	 * Calculate confidence based on how far a value exceeds a threshold.
	 * Useful for checks that measure continuous values (speed, reach, etc.)
	 *
	 * @param float $actual The actual measured value
	 * @param float $threshold The threshold that should not be exceeded
	 * @param float $maxExcess The excess at which confidence is 100%
	 * @return float Confidence score (0.0-1.0)
	 */
	protected function calculateExcessConfidence(float $actual, float $threshold, float $maxExcess = 1.0) : float {
		if ($actual <= $threshold) {
			return 0.0;
		}

		$excess = $actual - $threshold;
		$normalized = min(1.0, $excess / $maxExcess);

		// Use sigmoid-like curve for smoother confidence scaling
		return 0.4 + (0.6 * $normalized);
	}

	/**
	 * Get a dynamically adjusted threshold based on server/player conditions.
	 *
	 * @param float $baseThreshold The base threshold from config
	 * @param PlayerAPI $playerAPI The player being checked
	 * @return float Adjusted threshold (always >= base)
	 */
	protected function getDynamicThreshold(float $baseThreshold, PlayerAPI $playerAPI) : float {
		if (!(bool) self::getData("zuri.dynamic-thresholds.enable", true)) {
			return $baseThreshold;
		}

		return DynamicThreshold::adjust($baseThreshold, $playerAPI, $this->getName());
	}

	/**
	 * Check if a value exceeds the dynamic threshold.
	 *
	 * @param float $value The value to check
	 * @param float $baseThreshold The base threshold from config
	 * @param PlayerAPI $playerAPI The player being checked
	 * @return bool True if value exceeds the adjusted threshold
	 */
	protected function exceedsDynamicThreshold(float $value, float $baseThreshold, PlayerAPI $playerAPI) : bool {
		return $value > $this->getDynamicThreshold($baseThreshold, $playerAPI);
	}

	/**
	 * For Login purposes warning system only!
	 * @internal
	 */
	public function warn(string $username) : void {
		if (!self::getData(self::WARNING_ENABLE)) {
			return;
		}

		ZuriAC::getInstance()->getServer()->getLogger()->info(ReplaceText::replace($username, Lang::raw(LangKeys::WARNING_MESSAGE), $this->getName(), $this->getSubType()));
		foreach (ZuriAC::getInstance()->getServer()->getOnlinePlayers() as $p) {
			if ($p->hasPermission("zuri.admin")) {
				$p->sendMessage(ReplaceText::replace($username, Lang::raw(LangKeys::WARNING_MESSAGE), $this->getName(), $this->getSubType()));
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
