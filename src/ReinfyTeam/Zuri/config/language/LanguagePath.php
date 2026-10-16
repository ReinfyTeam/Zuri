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

namespace ReinfyTeam\Zuri\config\language;

interface LanguagePath {
	public const PUNISHMENT_BAN_MESSAGE = "punishments.ban_message";
	public const KICK_MESSAGE = "punishments.kick_message";
	public const CHECK_ERROR = "results.check_error";
	public const CHECK_UNKNOWN_ERROR = "results.unknown_error";
	public const CHECK_DEBUG_MESSAGE = "results.debug_message";
	public const RUNNING_PHAR_WARNING = "console.phar_warning_source";
	public const ZURI_COMMAND_DESCRIPTION = "commands.description.zuri";
	public const DEBUG_SUBCOMMAND_DESCRIPTION = "commands.description.debug";
	public const DEBUG_COMMAND_TOGGLE = "commands.messages.debug_toggle";
	public const DEBUG_COMMAND_ENABLED_ON = "commands.messages.debug_enabled_on";
	public const DEBUG_COMMAND_ENABLED_OFF = "commands.messages.debug_enabled_off";
}