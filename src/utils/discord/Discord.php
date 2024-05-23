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

namespace ReinfyTeam\Zuri\utils\discord;

use DateTime;
use pocketmine\utils\Config;
use ReinfyTeam\Zuri\config\ConfigManager;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\ReplaceText;
use ReinfyTeam\Zuri\ZuriAC;
use function hexdec;
use function str_replace;

class Discord extends ConfigManager {
	public const BAN = 0;
	public const KICK = 1;
	public const JOIN = 2;
	public const LEAVE = 3;
	public const LAGGING = 4;

	public static ?Config $config = null;

	public static function Send(PlayerAPI $playerAPI, int $type, ?array $moduleInfo = null) : void {
		$sendType = match ($type) {
			self::BAN => "ban",
			self::KICK => "kick",
			self::JOIN => "join",
			self::LEAVE => "leave",
			self::LAGGING => "lagging",
			default => null
		};

		if ($sendType === null) {
			return;
		}

		if (self::getData(self::DISCORD_ENABLE) === false) {
			return;
		}

		$webhookConfig = self::getWebhookConfig();
		$webhook = new Webhook($webhookConfig->getNested("discord.webhook_url"));

		if (!$webhook->isValid()) {
			throw new DiscordWebhookException("Discord Webhook URL is not valid an url. Please refer to the instruction on the github wiki!");
		}
		$message = new Message();
		if ($webhookConfig->getNested("$sendType.enable", false) === true) {
			$message->setContent($webhookConfig->getNested("$sendType.message", "`Empty message in the configuration!`"));

			if (!empty($webhookConfig->getNested("discord.username", ""))) {
				$message->setUsername($webhookConfig->getNested("discord.username"));
			}

			if ($webhookConfig->getNested("discord.icon.enable", false) === true) {
				$message->setAvatarURL($webhookConfig->getNested("discord.icon.url"));
			}

			if ($webhookConfig->getNested("$sendType.embed.enable", false) === true) {
				$embed = new Embed();

				if ($webhookConfig->getNested("$sendType.embed.author.enable", false) === true) {
					$embed->setAuthor(ReplaceText::replace($playerAPI, $webhookConfig->getNested("$sendType.embed.author.value", "ReinfyTeam"), ($moduleInfo !== null ? $moduleInfo["name"] : ""), ($moduleInfo !== null ? $moduleInfo["subType"] : "")), ($webhookConfig->getNested("$sendType.embed.author.url", "") === "" ? null : $webhookConfig->getNested("$sendType.embed.author.url", "")), ($webhookConfig->getNested("$sendType.embed.author.iconUrl", "") === "" ? null : $webhookConfig->getNested("$sendType.embed.author.iconUrl")));
				}

				if ($webhookConfig->getNested("$sendType.embed.title.enable", false) === true) {
					$embed->setTitle(ReplaceText::replace($playerAPI, $webhookConfig->getNested("$sendType.embed.title.value", "`Embed Title is empty in the webhook.yml!`"), ($moduleInfo !== null ? $moduleInfo["name"] : ""), ($moduleInfo !== null ? $moduleInfo["subType"] : "")));
				}

				if ($webhookConfig->getNested("$sendType.embed.description.enable", false) === true) {
					$embed->setDescription(ReplaceText::replace($playerAPI, $webhookConfig->getNested("$sendType.embed.description.value", "`Embed Description is empty in the webhook.yml!`"), ($moduleInfo !== null ? $moduleInfo["name"] : ""), ($moduleInfo !== null ? $moduleInfo["subType"] : "")));
				}

				if ($webhookConfig->getNested("$sendType.embed.footer.enable", false) === true) {
					$embed->setFooter(ReplaceText::replace($playerAPI, $webhookConfig->getNested("$sendType.embed.footer.value", "`Embed Footer is empty in the webhook.yml!`"), ($moduleInfo !== null ? $moduleInfo["name"] : ""), ($moduleInfo !== null ? $moduleInfo["subType"] : "")), ($webhookConfig->getNested("$sendType.embed.footer.iconUrl", "") === "" ? null : $webhookConfig->getNested("$sendType.embed.footer.iconUrl")));
				}

				if ($webhookConfig->getNested("$sendType.embed.thumbnail.enable", false) === true) {
					$embed->setThumbnail($webhookConfig->getNested("$sendType.embed.thumbnail.value"));
				}

				if ($webhookConfig->getNested("$sendType.embed.image.enable", false) === true) {
					$embed->setImage($webhookConfig->getNested("$sendType.embed.image.value"));
				}

				if ($webhookConfig->getNested("$sendType.embed.image.enable", false) === true) {
					$embed->setImage($webhookConfig->getNested("$sendType.embed.image.value"));
				}

				if ($webhookConfig->getNested("$sendType.embed.timestamp", false) === true) {
					$embed->setTimestamp(new DateTime("NOW"));
				}

				$embed->setColor(self::textToHex($webhookConfig->getNested("$sendType.embed.color", "#000000"))); // hex decimal color

				// Fields

				if ($webhookConfig->getNested("$sendType.embed.fields.enable", false) === true) {
					foreach ($webhookConfig->getNested("$sendType.embed.fields.value") as $field_name => $fieldInfo) {
						if (!empty($fieldInfo["title"]) && !empty($fieldInfo["value"]) && !empty($fieldInfo["inline"])) { // Assuming that these info's are not empty!
							$embed->addField(ReplaceText::replace($playerAPI, $fieldInfo["title"], ($moduleInfo !== null ? $moduleInfo["name"] : ""), ($moduleInfo !== null ? $moduleInfo["subType"] : "")), ReplaceText::replace($playerAPI, $fieldInfo["value"], ($moduleInfo !== null ? $moduleInfo["name"] : ""), ($moduleInfo !== null ? $moduleInfo["subType"] : "")), $fieldInfo["inline"]);
						} else {
							throw new DiscordWebhookException("Field \"$field_name\" has an empty required variables. Please fix them on webhook.yml!");
						}
					}
				}

				$message->addEmbed($embed);
			}

			$webhook->send($message);
		}
	}

	public static function getWebhookConfig() : Config {
		return self::$config ??= new Config(ZuriAC::getInstance()->getDataFolder() . "webhook.yml", Config::YAML);
	}

	public static function textToHex(string $hex) : mixed {
		// why this??
		$hex = str_replace("#", "", $hex);

		return hexdec($hex);
	}
}
