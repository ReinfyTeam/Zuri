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
use ReinfyTeam\Zuri\utils\AuditLogger;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use ReinfyTeam\Zuri\utils\HotPathProfiler;
use ReinfyTeam\Zuri\utils\ReplaceText;
use ReinfyTeam\Zuri\ZuriAC;
use function count;
use function explode;
use function implode;
use function in_array;
use function is_array;
use function is_numeric;
use function is_string;
use function max;
use function microtime;
use function min;
use function round;
use function str_replace;
use function strpos;
use function strtolower;
use function substr;
use function trim;

abstract class Check extends ConfigManager {
	/** @var array<string, float> */
	private static array $asyncThrottle = [];
	private static float $lastAsyncThrottleCleanup = 0.0;
	private static int $maxAsyncThrottleEntries = 4096;
	/** @var array<string, float> */
	private static array $messageThrottle = [];

	private ?bool $enabledOverride = null;
	private ?bool $enabledCache = null;
	private ?string $punishmentCache = null;
	private ?int $maxViolationsCache = null;
	/** @var array<string, mixed> */
	private array $constantCache = [];

	public abstract function getName() : string;

	public abstract function getSubType() : string;

	/**
	 * Declare which correlation domain this check belongs to.
	 * Used for cross-check correlation before punishment escalation.
	 *
	 * @return string|null One of CrossCheckCorrelation::GROUP_* constants, or null if not part of correlation.
	 */
	public function getCorrelationGroup() : ?string {
		return null;
	}

	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
	}

	public function checkEvent(Event $event, PlayerAPI $playerAPI) : void {
	}

	public function checkJustEvent(Event $event) : void {
	}

	/**
	 * @param array<string,mixed> $payload
	 * @return array<string,mixed>
	 */
	public static function evaluateAsync(array $payload) : array {
		if (($payload["_type"] ?? null) === "__decision") {
			return is_array($payload["result"] ?? null) ? $payload["result"] : [];
		}

		return [];
	}

	/** @param array<string,mixed> $payload */
	protected function dispatchAsyncCheck(string $playerName, array $payload) : void {
		$player = Server::getInstance()->getPlayerExact($playerName);
		if ($player === null || !$player->isOnline() || !$player->isConnected()) {
			return;
		}

		CheckAsyncTask::configure(
			self::toInt(self::getData("zuri.async.max-concurrent-workers", 4), 4),
			self::toInt(self::getData("zuri.async.max-queue-size", 2048), 2048),
			self::toFloat(self::getData("zuri.async.worker-timeout-seconds", 3.0), 3.0),
			self::toFloat(self::getData("zuri.async.degraded-cooldown-seconds", 6.0), 6.0)
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
		if (count(self::$asyncThrottle) > self::$maxAsyncThrottleEntries) {
			foreach (self::$asyncThrottle as $throttleKey => $expiresAt) {
				if ($expiresAt <= $now) {
					unset(self::$asyncThrottle[$throttleKey]);
				}
			}
			if (count(self::$asyncThrottle) > self::$maxAsyncThrottleEntries) {
				self::$asyncThrottle = [];
			}
		}

		$key = $playerName . ":" . static::class;
		$minIntervalRaw = $payload["_minInterval"] ?? self::getData("zuri.async.default-min-interval", 0.02);
		$minInterval = self::toFloat($minIntervalRaw, 0.02);
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

	/**
	 * @param array<string,mixed> $set
	 * @param list<string> $unset
	 */
	protected function dispatchAsyncDecision(
		PlayerAPI $playerAPI,
		bool $failed = false,
		string $debug = "",
		array $set = [],
		array $unset = [],
		float $minInterval = 0.0
	) : void {
		/** @var array<string,mixed> $result */
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
		$raw = self::getData(self::CHECK . "." . strtolower($this->getName()) . ".punishment", "FLAG");
		return $this->punishmentCache ??= strtolower(self::toString($raw, "FLAG"));
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
		$raw = self::getData(self::CHECK . "." . strtolower($this->getName()) . ".enable", false);
		return $this->enabledCache ??= ($raw === true);
	}

	public function maxViolations() : int {
		$raw = self::getData(self::CHECK . "." . strtolower($this->getName()) . ".pre-vl." . strtolower($this->getSubType()), 0);
		return $this->maxViolationsCache ??= self::toInt($raw, 0);
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
		$profileStartedAt = microtime(true);
		try {
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
			if (self::getData(self::CHECK_SCOPE_WORLD_ENABLE, false) === true) {
				$worldMode = strtolower(self::toString(self::getData(self::CHECK_SCOPE_WORLD_MODE, "blacklist"), "blacklist"));
				$worldList = self::toStringList(self::getData(self::CHECK_SCOPE_WORLD_LIST, []));
				$worldName = $player->getWorld()->getFolderName();
				$listed = in_array($worldName, $worldList, true);
				if (($worldMode === "blacklist" && $listed) || ($worldMode !== "blacklist" && !$listed)) {
					return false;
				}
			}
			if (self::getData(self::CHECK_SCOPE_GAMEMODE_ENABLE, false) === true) {
				$allowedModes = self::toStringList(self::getData(self::CHECK_SCOPE_GAMEMODE_LIST, ["survival"]));
				$currentMode = match (true) {
					$player->isCreative() => "creative",
					$player->isSpectator() => "spectator",
					$player->isSurvival() => "survival",
					default => "adventure",
				};
				if (!in_array($currentMode, $allowedModes, true)) {
					return false;
				}
			}

			$notify = self::getData(self::ALERTS_ENABLE) === true;
			$detectionsAllowedToSend = self::getData(self::DETECTION_ENABLE) === true;
			$bypassPermissionRaw = self::getData(self::PERMISSION_BYPASS_PERMISSION);
			$bypassPermission = self::toString($bypassPermissionRaw, "zuri.bypass");
			$bypass = self::getData(self::PERMISSION_BYPASS_ENABLE) === true && $player->hasPermission($bypassPermission);
			$maxPreViolations = $this->maxViolations();
			$maxViolationsRaw = self::getData(self::CHECK . "." . strtolower($this->getName()) . ".maxvl", 0);
			$maxViolations = self::toInt($maxViolationsRaw, 0);
			$playerAPI->addViolation($this->getName());
			$currentViolations = $playerAPI->getViolation($this->getName());
			$reachedMaxViolations = $maxPreViolations <= 0 || $currentViolations >= $maxPreViolations;
			if ($reachedMaxViolations) {
				$playerAPI->addRealViolation($this->getName());
			}
			$currentRealViolations = $playerAPI->getRealViolation($this->getName());
			$reachedMaxRealViolations = $maxViolations <= 0 || $currentRealViolations >= $maxViolations;
			$server = ZuriAC::getInstance()->getServer();

			$correlationEnabled = self::getData("zuri.correlation.enable", true) !== false;
			$correlationWindowSeconds = self::toFloat(self::getData("zuri.correlation.window-seconds", 10.0), 10.0);
			$requiredGroupsRaw = self::toInt(self::getData("zuri.correlation.required-groups", 3), 3);
			$requiredGroups = CrossCheckCorrelation::normalizeRequiredGroups($requiredGroupsRaw);
			$storedGroupHits = $playerAPI->getExternalData("correlation.groupHits", []);
			$typedGroupHits = is_array($storedGroupHits) ? $storedGroupHits : [];
			[$correlatedGroups, $groupHits] = CrossCheckCorrelation::recordAndCount(
				$typedGroupHits,
				$this->getName(),
				microtime(true),
				$correlationWindowSeconds
			);
			$playerAPI->setExternalData("correlation.groupHits", $groupHits);

			if (self::getData(self::WORLD_BYPASS_ENABLE) === true) {
				$worldBypassModeRaw = self::getData(self::WORLD_BYPASS_MODE);
				$worldBypassMode = strtolower(self::toString($worldBypassModeRaw, "blacklist"));
				$worldBypassList = self::toStringList(self::getData(self::WORLD_BYPASS_LIST, []));
				if ($worldBypassMode === "blacklist") {
					if (in_array($player->getWorld()->getFolderName(), $worldBypassList, true)) {
						return false;
					}
				} else {
					if (!in_array($player->getWorld()->getFolderName(), $worldBypassList, true)) {
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
				$alertText = ReplaceText::replace($playerAPI, Lang::raw(LangKeys::ALERTS_MESSAGE), $this->getName(), $this->getSubType());
				$alertKey = "alert:" . $player->getName() . ":" . strtolower($this->getName()) . ":" . strtolower($this->getSubType());
				if (self::canEmitThrottled($alertKey, 0.5)) {
					ZuriAC::getInstance()->getServer()->getLogger()->info($alertText);
					foreach (ZuriAC::getInstance()->getServer()->getOnlinePlayers() as $p) {
						if ($p->hasPermission("zuri.admin")) {
							$p->sendMessage($alertText);
						}
					}
				}
			} else {
				if ($detectionsAllowedToSend) {
					$detectionText = ReplaceText::replace($playerAPI, Lang::raw(LangKeys::DETECTION_MESSAGE), $this->getName(), $this->getSubType());
					$detectionKey = "detection:" . $player->getName() . ":" . strtolower($this->getName()) . ":" . strtolower($this->getSubType());
					if (self::canEmitThrottled($detectionKey, 0.4)) {
						ZuriAC::getInstance()->getServer()->getLogger()->info($detectionText);
						foreach (ZuriAC::getInstance()->getServer()->getOnlinePlayers() as $p) {
							if ($p->hasPermission("zuri.admin")) {
								$p->sendMessage($detectionText);
							}
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

			$requiresEscalationCorrelation = $correlationEnabled
				&& $reachedMaxRealViolations
				&& $reachedMaxViolations
				&& in_array($this->getPunishment(), ["ban", "kick", "captcha"], true);
			if ($requiresEscalationCorrelation && $correlatedGroups < $requiredGroups) {
				return false;
			}

			if ($reachedMaxRealViolations && $reachedMaxViolations && $this->getPunishment() === "ban" && self::getData(self::BAN_ENABLE) === true) {
				(new BanEvent($playerAPI, $this->getName(), $this->getSubType()))->call();
				AuditLogger::punishment("ban", $player->getName(), $this->getName(), $this->getSubType(), [
					"violations" => $currentViolations,
					"realViolations" => $currentRealViolations,
				]);
				$banText = ReplaceText::replace($playerAPI, Lang::raw(LangKeys::BAN_MESSAGE), $this->getName(), $this->getSubType());
				ZuriAC::getInstance()->getServer()->getLogger()->notice($banText);
				foreach (ZuriAC::getInstance()->getServer()->getOnlinePlayers() as $p) {
					if ($p->hasPermission("zuri.admin")) {
						$p->sendMessage($banText);
					}
				}
				foreach (self::toStringList(self::getData(self::BAN_COMMANDS, [])) as $command) {
					$server->dispatchCommand(new ConsoleCommandSender($server, $server->getLanguage()), ReplaceText::replace($playerAPI, $command, $this->getName(), $this->getSubType()));
				}

				$playerAPI->resetViolation($this->getName());
				$playerAPI->resetRealViolation($this->getName());
				return true;
			}

			if ($reachedMaxRealViolations && $reachedMaxViolations && $this->getPunishment() === "kick" && self::getData(self::KICK_ENABLE) === true) {
				(new KickEvent($playerAPI, $this->getName(), $this->getSubType()))->call();
				AuditLogger::punishment("kick", $player->getName(), $this->getName(), $this->getSubType(), [
					"violations" => $currentViolations,
					"realViolations" => $currentRealViolations,
				]);
				$kickText = ReplaceText::replace($playerAPI, Lang::raw(LangKeys::KICK_MESSAGE), $this->getName(), $this->getSubType());
				ZuriAC::getInstance()->getServer()->getLogger()->notice($kickText);
				if (self::getData(self::KICK_COMMANDS_ENABLED) === true) {
					$playerAPI->resetViolation($this->getName());
					$playerAPI->resetRealViolation($this->getName());
					foreach (ZuriAC::getInstance()->getServer()->getOnlinePlayers() as $p) {
						if ($p->hasPermission("zuri.admin")) {
							$p->sendMessage($kickText);
						}
					}
					foreach (self::toStringList(self::getData(self::KICK_COMMANDS, [])) as $command) {
						$server->dispatchCommand(new ConsoleCommandSender($server, $server->getLanguage()), ReplaceText::replace($playerAPI, $command, $this->getName(), $this->getSubType()));
					}
				} else {
					foreach (ZuriAC::getInstance()->getServer()->getOnlinePlayers() as $p) {
						if ($p->hasPermission("zuri.admin")) {
							$p->sendMessage($kickText);
						}
					}
					$playerAPI->resetViolation($this->getName());
					$playerAPI->resetRealViolation($this->getName());
					$player->kick(Lang::get(LangKeys::KICK_DISCONNECT_REASON), null, ReplaceText::replace($playerAPI, Lang::raw(LangKeys::KICK_UI_MESSAGE), $this->getName(), $this->getSubType()));
				}
				return true;
			}

			if ($reachedMaxRealViolations && $reachedMaxViolations && $this->getPunishment() === "captcha" && self::getData(self::CAPTCHA_ENABLE) === true) {
				AuditLogger::punishment("captcha", $player->getName(), $this->getName(), $this->getSubType(), [
					"violations" => $currentViolations,
					"realViolations" => $currentRealViolations,
				]);
				$playerAPI->setCaptcha(true);
				return true;
			}

			return false;
		} finally {
			HotPathProfiler::record("violation.processing." . $this->getName() . "." . $this->getSubType(), microtime(true) - $profileStartedAt);
		}
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
			ServerTickTask::getInstance()?->isLagging(microtime(true)) ?? false
		);

		// Get threshold from config (default 0.5 = medium confidence required)
		$threshold = self::toFloat(self::getData("zuri.confidence.threshold", 0.5), 0.5);
		$finalConfidence = $result->getConfidence();

		// Track confidence score for trending analysis
		$playerAPI->addConfidenceScore($this->getName(), $finalConfidence);

		// Debug output if enabled
		if ($playerAPI->isDebug()) {
			$this->debug($playerAPI, Lang::get("messages.debug.confidence", [
				"confidence" => (string) $result->getConfidencePercent(),
				"threshold" => (string) round($threshold * 100),
				"details" => $debugInfo,
			]));
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
				$localizedText = $this->localizeDebugText($text);
				$player->sendMessage(Lang::get("messages.debug.output.self", [
					"check" => $this->getName(),
					"subtype" => $this->getSubType(),
					"details" => $localizedText,
				], "{prefix} §7[DEBUG] §c{check}§7 (§e{subtype}§7) §b{details}"));

				if (self::getData(self::DEBUG_LOG_SERVER)) {
					ZuriAC::getInstance()->getServer()->getLogger()->notice(Lang::get("messages.debug.output.server", [
						"player" => $playerAPI->getPlayer()->getName(),
						"check" => $this->getName(),
						"subtype" => $this->getSubType(),
						"details" => $localizedText,
					], "{prefix} §7[DEBUG] §e{player}: §c{check}§7 (§e{subtype}§7) §b{details}"));
				}

				if (self::getData(self::DEBUG_LOG_ADMIN)) {
					foreach (ZuriAC::getInstance()->getServer()->getOnlinePlayers() as $p) {
						if ($p->getName() === $playerAPI->getPlayer()->getName()) {
							continue;
						} // Skip same player. Prevent spam in the chat history.
						if ($p->hasPermission("zuri.admin")) {
							$p->sendMessage(Lang::get("messages.debug.output.admin", [
								"player" => $playerAPI->getPlayer()->getName(),
								"check" => $this->getName(),
								"subtype" => $this->getSubType(),
								"details" => $localizedText,
							], "{prefix} §7[DEBUG] §e{player}: §c{check}§7 (§e{subtype}§7) §b{details}"));
						}
					}
				}
			}
		}
	}

	private function localizeDebugText(string $text) : string {
		$trimmed = trim($text);
		if ($trimmed === "") {
			return $text;
		}
		if (strpos($trimmed, "=") === false) {
			return Lang::get("messages.debug.free-text", ["details" => $trimmed], "{details}");
		}

		$segments = explode(",", $trimmed);
		$pairs = [];
		foreach ($segments as $segment) {
			$segment = trim($segment);
			if ($segment === "") {
				continue;
			}
			$separatorAt = strpos($segment, "=");
			if ($separatorAt === false) {
				$pairs[] = $segment;
				continue;
			}
			$key = trim(substr($segment, 0, $separatorAt));
			$value = trim(substr($segment, $separatorAt + 1));
			$labelKey = "messages.debug.labels." . strtolower(str_replace([" ", "-", "."], "_", $key));
			$translatedLabel = Lang::raw($labelKey, $key);
			$pairs[] = Lang::get("messages.debug.pair", [
				"key" => $translatedLabel,
				"value" => $value,
			], "{key}={value}");
		}

		return implode(Lang::get("messages.debug.separator", [], ", "), $pairs);
	}

	private static function toInt(mixed $value, int $default) : int {
		return is_numeric($value) ? (int) $value : $default;
	}

	private static function toFloat(mixed $value, float $default) : float {
		return is_numeric($value) ? (float) $value : $default;
	}

	private static function toString(mixed $value, string $default) : string {
		return is_string($value) ? $value : $default;
	}

	private static function canEmitThrottled(string $key, float $minIntervalSeconds) : bool {
		$now = microtime(true);
		$nextAllowedAt = self::$messageThrottle[$key] ?? 0.0;
		if ($nextAllowedAt > $now) {
			return false;
		}

		self::$messageThrottle[$key] = $now + max(0.05, $minIntervalSeconds);
		if (count(self::$messageThrottle) > 8192) {
			foreach (self::$messageThrottle as $throttleKey => $expiresAt) {
				if ($expiresAt <= $now) {
					unset(self::$messageThrottle[$throttleKey]);
				}
			}
			if (count(self::$messageThrottle) > 8192) {
				self::$messageThrottle = [];
			}
		}

		return true;
	}

	/** @return list<string> */
	private static function toStringList(mixed $value) : array {
		if (!is_array($value)) {
			return [];
		}
		$list = [];
		foreach ($value as $item) {
			if (is_string($item)) {
				$list[] = $item;
			}
		}
		return $list;
	}
}
