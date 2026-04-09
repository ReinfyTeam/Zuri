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
use function is_array;
use function is_bool;
use function is_string;
use function str_replace;

class Discord extends ConfigManager {
	public const int BAN = 0;
	public const int KICK = 1;
	public const int JOIN = 2;
	public const int LEAVE = 3;
	public const int LAGGING = 4;

	public static ?Config $config = null;

	private static function nestedString(Config $config, string $path, string $default = "") : string {
		$value = $config->getNested($path, $default);
		return is_string($value) ? $value : $default;
	}

	private static function nestedBool(Config $config, string $path, bool $default = false) : bool {
		$value = $config->getNested($path, $default);
		return is_bool($value) ? $value : $default;
	}

	/** @return array<string,mixed> */
	private static function nestedArray(Config $config, string $path) : array {
		$value = $config->getNested($path, []);
		return is_array($value) ? $value : [];
	}

	/**
	 * @throws DiscordWebhookException
	 */
	/** @param array{name:string,subType:string}|null $moduleInfo */
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
		$webhook = new Webhook(self::nestedString($webhookConfig, "discord.webhook_url"));

		if (!$webhook->isValid()) {
			throw new DiscordWebhookException("Discord Webhook URL is not valid an url. Please refer to the instruction on the github wiki!");
		}
		$message = new Message();
		$moduleName = $moduleInfo["name"] ?? "";
		$moduleSubType = $moduleInfo["subType"] ?? "";
		if (self::nestedBool($webhookConfig, "$sendType.enable", false)) {
			$message->setContent(self::nestedString($webhookConfig, "$sendType.message", "`Empty message in the configuration!`"));

			$username = self::nestedString($webhookConfig, "discord.username", "");
			if ($username !== "") {
				$message->setUsername($username);
			}

			if (self::nestedBool($webhookConfig, "discord.icon.enable", false)) {
				$message->setAvatarURL(self::nestedString($webhookConfig, "discord.icon.url", ""));
			}

			if (self::nestedBool($webhookConfig, "$sendType.embed.enable", false)) {
				$embed = new Embed();

				if (self::nestedBool($webhookConfig, "$sendType.embed.author.enable", false)) {
					$authorUrl = self::nestedString($webhookConfig, "$sendType.embed.author.url", "");
					$authorIconUrl = self::nestedString($webhookConfig, "$sendType.embed.author.iconUrl", "");
					$embed->setAuthor(
						ReplaceText::replace($playerAPI, self::nestedString($webhookConfig, "$sendType.embed.author.value", "ReinfyTeam"), $moduleName, $moduleSubType),
						$authorUrl === "" ? null : $authorUrl,
						$authorIconUrl === "" ? null : $authorIconUrl
					);
				}

				if (self::nestedBool($webhookConfig, "$sendType.embed.title.enable", false)) {
					$embed->setTitle(ReplaceText::replace($playerAPI, self::nestedString($webhookConfig, "$sendType.embed.title.value", "`Embed Title is empty in the webhook.yml!`"), $moduleName, $moduleSubType));
				}

				if (self::nestedBool($webhookConfig, "$sendType.embed.description.enable", false)) {
					$embed->setDescription(ReplaceText::replace($playerAPI, self::nestedString($webhookConfig, "$sendType.embed.description.value", "`Embed Description is empty in the webhook.yml!`"), $moduleName, $moduleSubType));
				}

				if (self::nestedBool($webhookConfig, "$sendType.embed.footer.enable", false)) {
					$footerIconUrl = self::nestedString($webhookConfig, "$sendType.embed.footer.iconUrl", "");
					$embed->setFooter(
						ReplaceText::replace($playerAPI, self::nestedString($webhookConfig, "$sendType.embed.footer.value", "`Embed Footer is empty in the webhook.yml!`"), $moduleName, $moduleSubType),
						$footerIconUrl === "" ? null : $footerIconUrl
					);
				}

				if (self::nestedBool($webhookConfig, "$sendType.embed.thumbnail.enable", false)) {
					$embed->setThumbnail(self::nestedString($webhookConfig, "$sendType.embed.thumbnail.value", ""));
				}

				if (self::nestedBool($webhookConfig, "$sendType.embed.image.enable", false)) {
					$embed->setImage(self::nestedString($webhookConfig, "$sendType.embed.image.value", ""));
				}

				if (self::nestedBool($webhookConfig, "$sendType.embed.timestamp", false)) {
					$embed->setTimestamp(new DateTime("NOW"));
				}

				$embed->setColor((int) self::textToHex(self::nestedString($webhookConfig, "$sendType.embed.color", "#000000"))); // hex decimal color

				// Fields

				if (self::nestedBool($webhookConfig, "$sendType.embed.fields.enable", false)) {
					foreach (self::nestedArray($webhookConfig, "$sendType.embed.fields.value") as $field_name => $fieldInfo) {
						if (!is_string($field_name) || !is_array($fieldInfo)) {
							continue;
						}
						$fieldTitle = $fieldInfo["title"] ?? null;
						$fieldValue = $fieldInfo["value"] ?? null;
						$fieldInline = $fieldInfo["inline"] ?? null;
						if (is_string($fieldTitle) && $fieldTitle !== "" && is_string($fieldValue) && $fieldValue !== "" && is_bool($fieldInline)) {
							$embed->addField(ReplaceText::replace($playerAPI, $fieldTitle, $moduleName, $moduleSubType), ReplaceText::replace($playerAPI, $fieldValue, $moduleName, $moduleSubType), $fieldInline);
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

	public static function textToHex(string $hex) : int|float {
		// why this??
		$hex = str_replace("#", "", $hex);

		return hexdec($hex); // ty php
	}
}
