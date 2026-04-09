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

namespace ReinfyTeam\Zuri\lang;

final class LangKeys {
	public const ALERTS_MESSAGE = "messages.alerts.message";
	public const DETECTION_MESSAGE = "messages.detection.message";
	public const BAN_MESSAGE = "messages.ban.message";
	public const KICK_MESSAGE = "messages.kick.message";
	public const KICK_UI_MESSAGE = "messages.kick.ui";
	public const KICK_DISCONNECT_REASON = "messages.kick.disconnect-reason";
	public const WARNING_MESSAGE = "messages.warning.message";
	public const CAPTCHA_TEXT = "messages.captcha.text";
	public const SERVER_LAGGING_MESSAGE = "messages.server.lagging";
	public const ANTIBOT_MESSAGE = "messages.antibot.message";
	public const EDITIONFAKER_MESSAGE = "messages.editionfaker.message";
	public const NETWORK_LIMIT_MESSAGE = "messages.network.limit";
	public const CHAT_SPAM_TEXT = "messages.chat.spam";
	public const CHAT_COMMAND_TEXT = "messages.chat.command";
	public const CHAT_REPEAT_TEXT = "messages.chat.repeat";

	public const CMD_HELP_HEADER = "commands.help.header";
	public const CMD_HELP_BUILD_AUTHOR = "commands.help.build-author";
	public const CMD_HELP_ABOUT = "commands.help.about";
	public const CMD_HELP_NOTIFY = "commands.help.notify";
	public const CMD_HELP_BANMODE = "commands.help.banmode";
	public const CMD_HELP_CAPTCHA = "commands.help.captcha";
	public const CMD_HELP_BYPASS = "commands.help.bypass";
	public const CMD_HELP_DEBUG = "commands.help.debug";
	public const CMD_HELP_LIST = "commands.help.list";
	public const CMD_HELP_UI = "commands.help.ui";
	public const CMD_HELP_LANGUAGE = "commands.help.language";
	public const CMD_HELP_FOOTER = "commands.help.footer";

	public const CMD_BANMODE_USAGE = "commands.banmode.usage";
	public const CMD_BANMODE_STATUS = "commands.banmode.status";
	public const CMD_BYPASS_STATUS = "commands.bypass.status";
	public const CMD_NOTIFY_USAGE = "commands.notify.usage";
	public const CMD_NOTIFY_STATUS = "commands.notify.status";
	public const CMD_UI_IN_GAME_ONLY = "commands.common.in-game-only";
	public const CMD_DEBUG_STATUS = "commands.debug.status";
	public const CMD_CAPTCHA_USAGE = "commands.captcha.usage";
	public const CMD_CAPTCHA_RANDOMIZE_ON = "commands.captcha.randomize-on";
	public const CMD_CAPTCHA_INVALID_LENGTH = "commands.captcha.invalid-length";
	public const CMD_CAPTCHA_LENGTH_UPDATED = "commands.captcha.length-updated";
	public const CMD_GENERIC_TOGGLE_STATUS = "commands.common.toggle-status";
	public const CMD_LANGUAGE_USAGE = "commands.language.usage";
	public const CMD_LANGUAGE_CURRENT = "commands.language.current";
	public const CMD_LANGUAGE_AVAILABLE = "commands.language.available";
	public const CMD_LANGUAGE_SWITCHED = "commands.language.switched";
	public const CMD_LANGUAGE_UNSUPPORTED = "commands.language.unsupported";

	public const CMD_LIST_HEADER = "commands.list.header";
	public const CMD_LIST_TITLE = "commands.list.title";
	public const CMD_LIST_ENTRY = "commands.list.entry";
	public const CMD_LIST_FOOTER = "commands.list.footer";
	public const CMD_ABOUT_BUILD_AUTHOR = "commands.about.build-author";
	public const CMD_ABOUT_ENABLED = "commands.about.enabled";
	public const CMD_ABOUT_DISABLED = "commands.about.disabled";
	public const CMD_ABOUT_ALL = "commands.about.all";

	public const ASYNC_STATUS_HEADER = "commands.async.header";
	public const ASYNC_STATUS_QUEUE = "commands.async.queue";
	public const ASYNC_STATUS_WORKERS = "commands.async.workers";
	public const ASYNC_STATUS_TOTALS = "commands.async.totals";
	public const ASYNC_STATUS_AVG = "commands.async.avg";
	public const ASYNC_STATUS_HEALTH = "commands.async.health";
	public const ASYNC_STATUS_FALLBACK = "commands.async.fallback";
	public const ASYNC_STATUS_LATENCY = "commands.async.latency";

	public const STARTUP_SOURCE_WARNING = "startup.source-warning";
	public const STARTUP_PROXY_STOPPING = "startup.proxy-stopping";
	public const STARTUP_PHP_TOO_OLD = "startup.php-too-old";
	public const STARTUP_VAPM_MISSING = "startup.vapm-missing";
	public const UPDATE_ERROR = "startup.update.error";
	public const UPDATE_DECODE_FAILED = "startup.update.decode-failed";
	public const UPDATE_NONE = "startup.update.none";
	public const UPDATE_AVAILABLE = "startup.update.available";
	public const UPDATE_CURRENT = "startup.update.current";
	public const UPDATE_LATEST = "startup.update.latest";
	public const UPDATE_DOWNLOADS = "startup.update.downloads";
	public const UPDATE_DOWNLOAD_URL = "startup.update.download-url";

	public const PROXY_CREATE_FAILED = "proxy.create-failed";
	public const PROXY_BANNER_LINE = "proxy.banner.line";
	public const PROXY_BANNER_TITLE = "proxy.banner.title";
	public const PROXY_BANNER_TESTING = "proxy.banner.testing";
	public const PROXY_BANNER_REPORT = "proxy.banner.report";
	public const PROXY_BANNER_PERFORMANCE = "proxy.banner.performance";
	public const PROXY_BANNER_RISK = "proxy.banner.risk";
	public const PROXY_BANNER_DISABLE_HINT = "proxy.banner.disable-hint";
	public const PROXY_BOUND = "proxy.bound";
	public const PROXY_READY = "proxy.ready";
	public const PROXY_BIND_FAILED = "proxy.bind-failed";

	public const LANG_VALIDATE_MISSING = "language.validation.missing";

	public const UI_ADVANCE_TOOLS_TITLE = "ui.advance-tools.title";
	public const UI_ADVANCE_TOOLS_UPDATED = "ui.advance-tools.updated";
	public const UI_ADVANCE_TOOLS_UPDATED_LANGUAGE = "ui.advance-tools.updated-language";
	public const UI_ADVANCE_TOOLS_CHOOSE = "ui.advance-tools.choose";
	public const UI_ADVANCE_TOOLS_LANGUAGE_LABEL = "ui.advance-tools.language-label";
}
