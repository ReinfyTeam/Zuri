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

namespace ReinfyTeam\Zuri\utils;

use pocketmine\Server;
use ReinfyTeam\Zuri\lang\Lang;
use Throwable;
use function get_class;
use function microtime;

/**
 * Provides panic-safe exception boundaries for critical code paths.
 * Catches and logs exceptions without crashing the server.
 */
final class ExceptionHandler {
	private static int $errorCount = 0;
	private static float $lastErrorTime = 0.0;
	/** @var array<string, int> */
	private static array $errorCounts = [];

	private const MAX_ERRORS_PER_MINUTE = 100;
	private const ERROR_WINDOW = 60.0;

	/**
	 * Wraps a callable in an exception boundary.
	 * If the callable throws, the exception is logged and the fallback value is returned.
	 *
	 * @template T
	 * @param callable(): T $callback The code to execute safely
	 * @param T $fallback Value to return if an exception occurs
	 * @param string $context Description of what operation was being performed
	 * @return T
	 */
	public static function wrap(callable $callback, mixed $fallback = null, string $context = "unknown operation") : mixed {
		try {
			return $callback();
		} catch (Throwable $e) {
			self::handleException($e, $context);
			return $fallback;
		}
	}

	/**
	 * Wraps a void callable in an exception boundary.
	 * If the callable throws, the exception is logged silently.
	 *
	 * @param callable(): void $callback The code to execute safely
	 * @param string $context Description of what operation was being performed
	 */
	public static function wrapVoid(callable $callback, string $context = "unknown operation") : void {
		try {
			$callback();
		} catch (Throwable $e) {
			self::handleException($e, $context);
		}
	}

	/**
	 * Handles an exception by logging it and tracking error rates.
	 */
	private static function handleException(Throwable $e, string $context) : void {
		$now = microtime(true);

		// Reset error count if we're outside the window
		if ($now - self::$lastErrorTime > self::ERROR_WINDOW) {
			self::$errorCount = 0;
			self::$errorCounts = [];
		}

		self::$errorCount++;
		self::$lastErrorTime = $now;

		// Track per-context error counts
		$key = get_class($e) . "@" . $context;
		self::$errorCounts[$key] = (self::$errorCounts[$key] ?? 0) + 1;

		// Rate limit logging to prevent log spam
		if (self::$errorCount <= self::MAX_ERRORS_PER_MINUTE) {
			$logger = Server::getInstance()->getLogger();
			AuditLogger::anticheat("Safe-crash boundary: context={$context}, type=" . get_class($e) . ", message=" . $e->getMessage());
			$logger->error(Lang::get("messages.debug.system.exception-error", [
				"context" => $context,
				"type" => get_class($e),
				"message" => $e->getMessage(),
			], "{prefix} Exception in {context}: {type}: {message}"));
			$logger->debug(Lang::get("messages.debug.system.exception-stack-trace", [
				"trace" => $e->getTraceAsString(),
			], "{prefix} Stack trace: {trace}"));
			AuditLogger::crashThrowable("Safe-crash boundary context={$context}", $e);
		} elseif (self::$errorCount === self::MAX_ERRORS_PER_MINUTE + 1) {
			$logger = Server::getInstance()->getLogger();
			$logger->error(Lang::get("messages.debug.system.exception-suppressed", [], "{prefix} Too many exceptions, suppressing further logging for 60 seconds"));
		}
	}

	/**
	 * Returns the current error count within the tracking window.
	 */
	public static function getErrorCount() : int {
		$now = microtime(true);
		if ($now - self::$lastErrorTime > self::ERROR_WINDOW) {
			return 0;
		}
		return self::$errorCount;
	}

	/**
	 * Returns error counts grouped by exception type and context.
	 * @return array<string, int>
	 */
	public static function getErrorBreakdown() : array {
		$now = microtime(true);
		if ($now - self::$lastErrorTime > self::ERROR_WINDOW) {
			return [];
		}
		return self::$errorCounts;
	}

	/**
	 * Checks if the error rate indicates the system is degraded.
	 */
	public static function isSystemDegraded() : bool {
		return self::getErrorCount() > self::MAX_ERRORS_PER_MINUTE / 2;
	}

	/**
	 * Resets error tracking state. Useful for testing.
	 */
	public static function reset() : void {
		self::$errorCount = 0;
		self::$lastErrorTime = 0.0;
		self::$errorCounts = [];
	}
}
