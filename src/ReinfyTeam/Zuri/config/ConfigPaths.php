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
 * @author ReinfyTeam
 * @link https://github.com/ReinfyTeam/
 *
 *
 */

declare(strict_types=1);

namespace ReinfyTeam\Zuri\config;

abstract class ConfigPaths {
	public const PREFIX = "zuri.prefix";

	public const ANTIBOT_MESSAGE = "zuri.antibot.message";
	public const EDITIONFAKER_MESSAGE = "zuri.editionfaker.message";

	public const NETWORK_LIMIT = "zuri.network.limit";
	public const NETWORK_MESSAGE = "zuri.network.message";

	public const PING_NORMAL = "zuri.ping.normal";
	public const PING_LAGGING = "zuri.ping.lagging";

	public const PROXY_ENABLE = "zuri.proxy.enable";
	public const PROXY_IP = "zuri.proxy.ip";
	public const PROXY_PORT = "zuri.proxy.port";

	public const VERSION = "zuri.version";

	public const PROCESS_AUTO = "zuri.process.auto";

	public const XRAY_ENABLE = "zuri.xray.enable";
	public const XRAY_DISTANCE = "zuri.xray.distance";

	public const ALERTS_MESSAGE = "zuri.alerts.message";
	public const ALERTS_ENABLE = "zuri.alerts.enable";
	public const ALERTS_PERMISSION = "zuri.alerts.permission";
	public const ALERTS_ADMIN = "zuri.alerts.admin";

	public const BAN_COMMANDS = "zuri.ban.commands";
	public const BAN_MESSAGE = "zuri.ban.message";
	public const BAN_ENABLE = "zuri.ban.enable";
	public const BAN_RECENT_LOGS_MESSAGE = "zuri.ban.recentlogs.message";

	public const KICK_ENABLE = "zuri.kick.enable";
	public const KICK_MESSAGE = "zuri.kick.message";
	public const KICK_COMMANDS_ENABLED = "zuri.kick.commands.enabled";
	public const KICK_COMMANDS = "zuri.kick.commands.list";
	public const KICK_MESSAGE_UI = "zuri.kick.kickmessage";
	public const KICK_RECENT_LOGS_MESSAGE = "zuri.kick.recentlogs.message";

	public const PERMISSION_BYPASS_ENABLE = "zuri.permissions.bypass.enable";
	public const PERMISSION_BYPASS_PERMISSION = "zuri.permissions.bypass.permission";
	public const WORLD_BYPASS_ENABLE = "zuri.world_bypass.enable";
	public const WORLD_BYPASS_MODE = "zuri.world_bypass.mode";
	public const WORLD_BYPASS_LIST = "zuri.world_bypass.list";

	public const DISCORD_ENABLE = "zuri.discord.enable";
	public const DISCORD_WEBHOOK = "zuri.discord.webhook";
	public const DISCORD_PLAYER_JOIN_ENABLE = "zuri.discord.player.joined.enable";
	public const DISCORD_PLAYER_JOIN_TEXT = "zuri.discord.player.joined.text";
	public const DISCORD_PLAYER_LEFT_ENABLE = "zuri.discord.player.left.enable";
	public const DISCORD_PLAYER_LEFT_TEXT = "zuri.discord.player.left.text";
	public const DISCORD_PLAYER_KICK_ENABLE = "zuri.discord.player.kick.enable";
	public const DISCORD_PLAYER_KICK_TEXT = "zuri.discord.player.kick.text";
	public const DISCORD_PLAYER_BAN_ENABLE = "zuri.discord.player.ban.enable";
	public const DISCORD_PLAYER_BAN_TEXT = "zuri.discord.player.ban.text";
	public const DISCORD_SERVER_LAGGING_ENABLE = "zuri.discord.server.lagging.enable";
	public const DISCORD_SERVER_LAGGING_TEXT = "zuri.discord.server.lagging.text";

	public const CAPTCHA_ENABLE = "zuri.captcha.enable";
	public const CAPTCHA_TEXT = "zuri.captcha.text";
	public const CAPTCHA_MESSAGE = "zuri.captcha.message";
	public const CAPTCHA_TIP = "zuri.captcha.tip";
	public const CAPTCHA_TITLE = "zuri.captcha.title";
	public const CAPTCHA_RANDOMIZE = "zuri.captcha.randomize";
	public const CAPTCHA_CODE_LENGTH = "zuri.captcha.code.length";

	public const CHAT_SPAM_TEXT = "zuri.chat.spam.text";
	public const CHAT_SPAM_DELAY = "zuri.chat.spam.delay";
	public const CHAT_COMMAND_SPAM_TEXT = "zuri.chat.command.text";
	public const CHAT_COMMAND_SPAM_DELAY = "zuri.chat.command.delay";
	public const CHAT_COMMAND_SPAM_COMMANDS = "zuri.chat.command.commands";
	public const CHAT_REPEAT_TEXT = "zuri.chat.repeat.text";

	public const CHECK = "zuri.check";

	public const DETECTION_ENABLE = "zuri.detection.enable";
	public const DETECTION_MESSAGE = "zuri.detection.message";

	public const WARNING_ENABLE = "zuri.warning.enable";
	public const WARNING_MESSAGE = "zuri.warning.message";
}