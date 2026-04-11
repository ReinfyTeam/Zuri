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

namespace ReinfyTeam\Zuri\command;

use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\BaseSubCommand;
use pocketmine\command\CommandSender;
use pocketmine\lang\Translatable;
use pocketmine\plugin\PluginBase;
use ReinfyTeam\Zuri\command\subcommand\AboutSubCommand;
use ReinfyTeam\Zuri\command\subcommand\BanModeSubCommand;
use ReinfyTeam\Zuri\command\subcommand\BypassSubCommand;
use ReinfyTeam\Zuri\command\subcommand\CaptchaSubCommand;
use ReinfyTeam\Zuri\command\subcommand\DebugSubCommand;
use ReinfyTeam\Zuri\command\subcommand\HelpSubCommand;
use ReinfyTeam\Zuri\command\subcommand\LanguageSubCommand;
use ReinfyTeam\Zuri\command\subcommand\ListSubCommand;
use ReinfyTeam\Zuri\command\subcommand\NotifySubCommand;
use ReinfyTeam\Zuri\command\subcommand\ReportSubCommand;
use ReinfyTeam\Zuri\command\subcommand\StatusSubCommand;
use ReinfyTeam\Zuri\command\subcommand\UiSubCommand;
use ReinfyTeam\Zuri\lang\Lang;
use ReinfyTeam\Zuri\lang\LangKeys;
use ReinfyTeam\Zuri\ZuriAC;
use function explode;
use function implode;
use function preg_replace;
use function trim;

class ZuriCommand extends BaseCommand {
	/** @var list<array{name:string,usage:string,description:string}> */
	private static array $helpEntries = [];

	public function __construct(ZuriAC $plugin) {
		parent::__construct($plugin, "zuri", "Zuri Anticheat", ["anticheat", "ac"]);
	}

	protected function prepare() : void {
		$plugin = $this->plugin;
		if (!$plugin instanceof PluginBase) {
			return;
		}
		$this->setPermission("zuri.command");
		self::$helpEntries = [];
		$this->registerAndCollectSubCommand(new AboutSubCommand($plugin));
		$this->registerAndCollectSubCommand(new NotifySubCommand($plugin));
		$this->registerAndCollectSubCommand(new BanModeSubCommand($plugin));
		$this->registerAndCollectSubCommand(new CaptchaSubCommand($plugin));
		$this->registerAndCollectSubCommand(new BypassSubCommand($plugin));
		$this->registerAndCollectSubCommand(new DebugSubCommand($plugin));
		$this->registerAndCollectSubCommand(new StatusSubCommand($plugin));
		$this->registerAndCollectSubCommand(new ReportSubCommand($plugin));
		$this->registerAndCollectSubCommand(new ListSubCommand($plugin));
		$this->registerAndCollectSubCommand(new UiSubCommand($plugin));
		$this->registerAndCollectSubCommand(new LanguageSubCommand($plugin));
		$this->registerAndCollectSubCommand(new HelpSubCommand($plugin));
	}

	/** @param array<string,mixed> $args */
	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void {
		$sender->sendMessage(self::buildHelpMessage($this->getName()));
	}

	public static function buildHelpMessage(string $namecmd = "zuri") : string {
		$version = ZuriAC::getInstance()->getDescription()->getVersion();
		$author = ZuriAC::getInstance()->getDescription()->getAuthors()[0] ?? 'Unknown';
		$lines = [
			Lang::get(LangKeys::CMD_HELP_HEADER),
			Lang::get(LangKeys::CMD_HELP_BUILD_AUTHOR, ["version" => $version, "author" => $author]),
			"",
		];
		foreach (self::$helpEntries as $entry) {
			$usageText = " §8(§7usage: {$entry["usage"]}§8)";
			$lines[] = "§c/{$namecmd} §r{$entry["name"]}{$usageText}§7: {$entry["description"]}";
		}
		$lines[] = Lang::get(LangKeys::CMD_HELP_FOOTER);
		return implode("\n", $lines);
	}

	private function registerAndCollectSubCommand(BaseSubCommand $subCommand) : void {
		$this->registerSubCommand($subCommand);
		$rawDescription = $subCommand->getDescription();
		$description = trim($rawDescription instanceof Translatable ? $rawDescription->getText() : $rawDescription);
		$rawUsage = $subCommand->getUsage();
		$usage = trim($rawUsage);
		if ($usage !== "") {
			$usage = trim((string) preg_replace('/^\s*usage:\s*/i', '', $usage));
			$usage = trim(explode(":", $usage, 2)[0] ?? $usage);
		}
		if ($usage === "") {
			$usage = "/zuri " . $subCommand->getName();
		}
		if ($usage[0] !== "/") {
			$usage = "/zuri " . $subCommand->getName();
		}
		self::$helpEntries[] = [
			"name" => $subCommand->getName(),
			"usage" => $usage,
			"description" => $description !== "" ? $description : "No description provided.",
		];
	}
}
