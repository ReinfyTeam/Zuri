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

namespace ReinfyTeam\Zuri\config;

abstract class ConfigPaths {
	public const string PREFIX = "zuri.prefix";

	public const string ANTIBOT_MESSAGE = "zuri.antibot.message";
	public const string EDITIONFAKER_MESSAGE = "zuri.editionfaker.message";

	public const string NETWORK_LIMIT = "zuri.network.limit";
	public const string NETWORK_LIMIT_ENABLE = "zuri.network.enable";
	public const string NETWORK_MESSAGE = "zuri.network.message";

	public const string PING_NORMAL = "zuri.ping.normal";
	public const string PING_LAGGING = "zuri.ping.lagging";

	public const string PROXY_ENABLE = "zuri.proxy.enable";
	public const string PROXY_IP = "zuri.proxy.ip";
	public const string PROXY_PORT = "zuri.proxy.port";

	public const string VERSION = "zuri.version";

	public const string PROCESS_AUTO = "zuri.process.auto";

	public const string XRAY_ENABLE = "zuri.xray.enable"; // TODO
	public const string XRAY_DISTANCE = "zuri.xray.distance"; // TODO

	public const string ALERTS_MESSAGE = "zuri.alerts.message";
	public const string ALERTS_ENABLE = "zuri.alerts.enable";
	public const string ALERTS_PERMISSION = "zuri.alerts.permission";
	public const string ALERTS_ADMIN = "zuri.alerts.admin";

	public const string BAN_COMMANDS = "zuri.ban.commands";
	public const string BAN_MESSAGE = "zuri.ban.message";
	public const string BAN_ENABLE = "zuri.ban.enable";

	public const string KICK_ENABLE = "zuri.kick.enable";
	public const string KICK_MESSAGE = "zuri.kick.message";
	public const string KICK_COMMANDS_ENABLED = "zuri.kick.commands.enable";
	public const string KICK_COMMANDS = "zuri.kick.commands.list";
	public const string KICK_MESSAGE_UI = "zuri.kick.kickmessage";

	public const string PERMISSION_BYPASS_ENABLE = "zuri.permissions.bypass.enable";
	public const string PERMISSION_BYPASS_PERMISSION = "zuri.permissions.bypass.permission";
	public const string WORLD_BYPASS_ENABLE = "zuri.world_bypass.enable";
	public const string WORLD_BYPASS_MODE = "zuri.world_bypass.mode";
	public const string WORLD_BYPASS_LIST = "zuri.world_bypass.list";

	public const string DISCORD_ENABLE = "zuri.discord.enable";

	public const string CAPTCHA_ENABLE = "zuri.captcha.enable";
	public const string CAPTCHA_TEXT = "zuri.captcha.text";
	public const string CAPTCHA_MESSAGE = "zuri.captcha.message";
	public const string CAPTCHA_TIP = "zuri.captcha.tip";
	public const string CAPTCHA_TITLE = "zuri.captcha.title";
	public const string CAPTCHA_RANDOMIZE = "zuri.captcha.randomize";
	public const string CAPTCHA_CODE_LENGTH = "zuri.captcha.code.length";

	public const string CHAT_SPAM_TEXT = "zuri.chat.spam.text";
	public const string CHAT_SPAM_DELAY = "zuri.chat.spam.delay";
	public const string CHAT_COMMAND_SPAM_TEXT = "zuri.chat.command.text";
	public const string CHAT_COMMAND_SPAM_DELAY = "zuri.chat.command.delay";
	public const string CHAT_COMMAND_SPAM_COMMANDS = "zuri.chat.command.commands";
	public const string CHAT_REPEAT_TEXT = "zuri.chat.repeat.text";

	public const string CHECK = "zuri.check";

	public const string DETECTION_ENABLE = "zuri.detection.enable";
	public const string DETECTION_MESSAGE = "zuri.detection.message";

	public const string WARNING_ENABLE = "zuri.warning.enable";
	public const string WARNING_MESSAGE = "zuri.warning.message";

	public const string DEBUG_ENABLE = "zuri.debug.enable";
	public const string DEBUG_LOG_ADMIN = "zuri.debug.log-admin";
	public const string DEBUG_LOG_SERVER = "zuri.debug.log-server";

	public const string SERVER_LAGGING_MESSAGE = "zuri.lagging.message";
}