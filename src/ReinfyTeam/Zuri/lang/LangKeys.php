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
	public const string ALERTS_MESSAGE = "messages.alerts.message";
	public const string DETECTION_MESSAGE = "messages.detection.message";
	public const string BAN_MESSAGE = "messages.ban.message";
	public const string KICK_MESSAGE = "messages.kick.message";
	public const string KICK_UI_MESSAGE = "messages.kick.ui";
	public const string KICK_DISCONNECT_REASON = "messages.kick.disconnect-reason";
	public const string WARNING_MESSAGE = "messages.warning.message";
	public const string CAPTCHA_TEXT = "messages.captcha.text";
	public const string SERVER_LAGGING_MESSAGE = "messages.server.lagging";
	public const string ANTIBOT_MESSAGE = "messages.antibot.message";
	public const string EDITIONFAKER_MESSAGE = "messages.editionfaker.message";
	public const string NETWORK_LIMIT_MESSAGE = "messages.network.limit";
	public const string CHAT_SPAM_TEXT = "messages.chat.spam";
	public const string CHAT_REPEAT_TEXT = "messages.chat.repeat";

	public const string CMD_HELP_HEADER = "commands.help.header";
	public const string CMD_HELP_BUILD_AUTHOR = "commands.help.build-author";
	public const string CMD_HELP_ABOUT = "commands.help.about";
	public const string CMD_HELP_NOTIFY = "commands.help.notify";
	public const string CMD_HELP_BANMODE = "commands.help.banmode";
	public const string CMD_HELP_CAPTCHA = "commands.help.captcha";
	public const string CMD_HELP_BYPASS = "commands.help.bypass";
	public const string CMD_HELP_DEBUG = "commands.help.debug";
	public const string CMD_HELP_LIST = "commands.help.list";
	public const string CMD_HELP_UI = "commands.help.ui";
	public const string CMD_HELP_LANGUAGE = "commands.help.language";
	public const string CMD_HELP_FOOTER = "commands.help.footer";

	public const string CMD_BANMODE_USAGE = "commands.banmode.usage";
	public const string CMD_BANMODE_STATUS = "commands.banmode.status";
	public const string CMD_BYPASS_STATUS = "commands.bypass.status";
	public const string CMD_NOTIFY_USAGE = "commands.notify.usage";
	public const string CMD_NOTIFY_STATUS = "commands.notify.status";
	public const string CMD_UI_IN_GAME_ONLY = "commands.common.in-game-only";
	public const string CMD_DEBUG_STATUS = "commands.debug.status";
	public const string CMD_CAPTCHA_USAGE = "commands.captcha.usage";
	public const string CMD_CAPTCHA_RANDOMIZE_ON = "commands.captcha.randomize-on";
	public const string CMD_CAPTCHA_INVALID_LENGTH = "commands.captcha.invalid-length";
	public const string CMD_CAPTCHA_LENGTH_UPDATED = "commands.captcha.length-updated";
	public const string CMD_GENERIC_TOGGLE_STATUS = "commands.common.toggle-status";
	public const string CMD_LANGUAGE_USAGE = "commands.language.usage";
	public const string CMD_LANGUAGE_CURRENT = "commands.language.current";
	public const string CMD_LANGUAGE_AVAILABLE = "commands.language.available";
	public const string CMD_LANGUAGE_SWITCHED = "commands.language.switched";
	public const string CMD_LANGUAGE_UNSUPPORTED = "commands.language.unsupported";

	public const string CMD_LIST_HEADER = "commands.list.header";
	public const string CMD_LIST_TITLE = "commands.list.title";
	public const string CMD_LIST_ENTRY = "commands.list.entry";
	public const string CMD_LIST_FOOTER = "commands.list.footer";
	public const string CMD_ABOUT_BUILD_AUTHOR = "commands.about.build-author";
	public const string CMD_ABOUT_ENABLED = "commands.about.enabled";
	public const string CMD_ABOUT_DISABLED = "commands.about.disabled";
	public const string CMD_ABOUT_ALL = "commands.about.all";

	public const string ASYNC_STATUS_HEADER = "commands.async.header";
	public const string ASYNC_STATUS_QUEUE = "commands.async.queue";
	public const string ASYNC_STATUS_WORKERS = "commands.async.workers";
	public const string ASYNC_STATUS_TOTALS = "commands.async.totals";
	public const string ASYNC_STATUS_AVG = "commands.async.avg";
	public const string ASYNC_STATUS_HEALTH = "commands.async.health";
	public const string ASYNC_STATUS_FALLBACK = "commands.async.fallback";
	public const string ASYNC_STATUS_LATENCY = "commands.async.latency";

	public const string STARTUP_SOURCE_WARNING = "startup.source-warning";
	public const string STARTUP_PROXY_STOPPING = "startup.proxy-stopping";
	public const string STARTUP_PHP_TOO_OLD = "startup.php-too-old";
	public const string STARTUP_VAPM_MISSING = "startup.vapm-missing";
	public const string UPDATE_ERROR = "startup.update.error";
	public const string UPDATE_DECODE_FAILED = "startup.update.decode-failed";
	public const string UPDATE_NONE = "startup.update.none";
	public const string UPDATE_AVAILABLE = "startup.update.available";
	public const string UPDATE_CURRENT = "startup.update.current";
	public const string UPDATE_LATEST = "startup.update.latest";
	public const string UPDATE_DOWNLOADS = "startup.update.downloads";
	public const string UPDATE_DOWNLOAD_URL = "startup.update.download-url";

	public const string PROXY_CREATE_FAILED = "proxy.create-failed";
	public const string PROXY_BANNER_LINE = "proxy.banner.line";
	public const string PROXY_BANNER_TITLE = "proxy.banner.title";
	public const string PROXY_BANNER_TESTING = "proxy.banner.testing";
	public const string PROXY_BANNER_REPORT = "proxy.banner.report";
	public const string PROXY_BANNER_PERFORMANCE = "proxy.banner.performance";
	public const string PROXY_BANNER_RISK = "proxy.banner.risk";
	public const string PROXY_BANNER_DISABLE_HINT = "proxy.banner.disable-hint";
	public const string PROXY_BOUND = "proxy.bound";
	public const string PROXY_READY = "proxy.ready";
	public const string PROXY_BIND_FAILED = "proxy.bind-failed";

	public const string LANG_VALIDATE_MISSING = "language.validation.missing";

	public const string UI_ADVANCE_TOOLS_TITLE = "ui.advance-tools.title";
	public const string UI_ADVANCE_TOOLS_UPDATED = "ui.advance-tools.updated";
	public const string UI_ADVANCE_TOOLS_UPDATED_LANGUAGE = "ui.advance-tools.updated-language";
	public const string UI_ADVANCE_TOOLS_CHOOSE = "ui.advance-tools.choose";
	public const string UI_ADVANCE_TOOLS_LANGUAGE_LABEL = "ui.advance-tools.language-label";
}
