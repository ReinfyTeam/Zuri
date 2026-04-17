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

namespace ReinfyTeam\Zuri\check;

use ReinfyTeam\Zuri\config\ConfigPath;
use ReinfyTeam\Zuri\ZuriAC;
use function strtolower;


/**
 * Abstract base class for all anti-cheat checks.
 */
/**
 * Abstract base class for all anti-cheat checks.
 */
abstract class Check {
	/**
	 * Check type: triggered by packet data.
	 */
	public const TYPE_PACKET = 1;
	/**
	 * Check type: triggered by player state.
	 */
	public const TYPE_PLAYER = 2;
	/**
	 * Check type: triggered by events.
	 */
	public const TYPE_EVENT = 3;


	/**
	 * Returns the name of the check.
	 *
	 * @return string Human-readable check name used as a config key.
	 */
	abstract public function getName() : string;

	/**
	 * Returns the subtype of the check.
	 *
	 * Subtypes are used to group related checks (for example, different
	 * variations of a speed check) under a single check name in config.
	 */
	abstract public function getSubType() : string;

	/**
	 * Returns the type of the check (see TYPE_* constants).
	 *
	 * @return int One of `TYPE_PACKET`, `TYPE_PLAYER` or `TYPE_EVENT`.
	 */
	abstract public function getType() : int;

	/**
	 * Runs the check logic on the provided data.
	 *
	 * Implementations must accept the worker-provided payload and return
	 * an associative array describing the result. Use `buildResult` to
	 * build a consistent return shape.
	 *
	 * @param array $data Worker payload (player data, packet, external data)
	 * @return array{failed:bool,debug:array}
	 */
	abstract public static function check(array $data) : array;


	/**
	 * Builds a result array for the check module.
	 *
	 * @param bool $failed Whether the check failed.
	 * @param array $debug Optional debug data.
	 * @return array{failed:bool,debug:array}
	 */
	public static function buildResult(bool $failed, array $debug = []) : array {
		return ["failed" => $failed, "debug" => $debug];
	}


	/**
	 * Returns the punishment type for this check.
	 *
	 * The punishment value is read from the plugin config under
	 * `checks.{checkName}.{subType}.maxvl`.
	 */
	public function getPunishment() : string {
		return ZuriAC::getConfigManager()->getData(ConfigPath::CHECKS . "." . strtolower($this->getName()) . "." . strtolower($this->getSubType()) . ".maxvl");
	}


	/**
	 * Returns whether this check is enabled.
	 *
	 * Reads from `checks.{checkName}.enable` and defaults to false.
	 */
	public function isEnabled() : bool {
		return ZuriAC::getConfigManager()->getData(ConfigPath::CHECKS . "." . strtolower($this->getName()) . ".enable", false);
	}


	/**
	 * Returns the maximum number of pre-violations allowed for this check.
	 *
	 * Read from `checks.{checkName}.pre-vl.{subType}` with a default of 1.
	 */
	public function getMaxPreViolation() : int {
		return ZuriAC::getConfigManager()->getData(ConfigPath::CHECKS . "." . strtolower($this->getName()) . ".pre-vl." . strtolower($this->getSubType()), 1);
	}


	/**
	 * Returns the maximum number of violations allowed for this check.
	 *
	 * Read from `checks.{checkName}.maxvl` with a default of 1.
	 */
	public function getMaxViolation() : int {
		return ZuriAC::getConfigManager()->getData(ConfigPath::CHECKS . "." . strtolower($this->getName()) . ".maxvl", 1);
	}
}