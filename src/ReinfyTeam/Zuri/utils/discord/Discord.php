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

use CortexPE\DiscordWebhookAPI\Embed;
use CortexPE\DiscordWebhookAPI\Message;
use CortexPE\DiscordWebhookAPI\Webhook;
use DateTime;
use pocketmine\utils\Config;
use ReinfyTeam\Zuri\config\ConfigManager;
use ReinfyTeam\Zuri\lang\Lang;
use ReinfyTeam\Zuri\lang\LangKeys;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\ReplaceText;
use ReinfyTeam\Zuri\ZuriAC;
use function count;
use function hexdec;
use function is_array;
use function is_bool;
use function is_string;
use function microtime;
use function str_replace;

/**
 * Sends anti-cheat and player lifecycle notifications to Discord webhooks.
 */
class Discord extends ConfigManager {
	public const BAN = 0;
	public const KICK = 1;
	public const JOIN = 2;
	public const LEAVE = 3;
	public const LAGGING = 4;

	public static ?Config $config = null;
	/** @var array<string,float> */
	private static array $sendThrottle = [];

	/**
	 * Reads a nested string setting from webhook config with fallback.
	 *
	 * @param Config $config Webhook configuration object.
	 * @param string $path Nested key path.
	 * @param string $default Default value.
	 */
	private static function nestedString(Config $config, string $path, string $default = "") : string {
		$value = $config->getNested($path, $default);
		return is_string($value) ? $value : $default;
	}

	/**
	 * Reads a nested boolean setting from webhook config with fallback.
	 *
	 * @param Config $config Webhook configuration object.
	 * @param string $path Nested key path.
	 * @param bool $default Default value.
	 */
	private static function nestedBool(Config $config, string $path, bool $default = false) : bool {
		$value = $config->getNested($path, $default);
		return is_bool($value) ? $value : $default;
	}

	/**
	 * Reads a nested array setting from webhook config.
	 *
	 * @param Config $config Webhook configuration object.
	 * @param string $path Nested key path.
	 * @return array<string,mixed>
	 */
	private static function nestedArray(Config $config, string $path) : array {
		$value = $config->getNested($path, []);
		return is_array($value) ? $value : [];
	}

	/**
	 * Sends a webhook message for a specific event type and player context.
	 *
	 * @param PlayerAPI $playerAPI Player context.
	 * @param int $type Webhook event type constant.
	 * @param array{name:string,subType:string}|null $moduleInfo Optional module metadata.
	 * @throws DiscordWebhookException
	 */
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
		$throttleKey = $sendType . ":" . $playerAPI->getPlayer()->getName();
		if (!self::canSendNow($throttleKey, 0.75)) {
			return;
		}

		$webhookConfig = self::getWebhookConfig();
		$webhook = new Webhook(self::nestedString($webhookConfig, "discord.webhook_url"));

		if (!$webhook->isValid()) {
			throw new DiscordWebhookException(Lang::get(LangKeys::DEBUG_DISCORD_INVALID_WEBHOOK_URL));
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
							throw new DiscordWebhookException(Lang::get(LangKeys::DEBUG_DISCORD_INVALID_FIELD_CONFIG, ["field" => $field_name]));
						}
					}
				}

				$message->addEmbed($embed);
			}

			$webhook->send($message);
		}
	}

	/**
	 * Applies lightweight cooldown throttling per message key.
	 *
	 * @param string $key Throttle key.
	 * @param float $cooldownSeconds Cooldown duration in seconds.
	 * @return bool True when sending is allowed now.
	 */
	private static function canSendNow(string $key, float $cooldownSeconds) : bool {
		$now = microtime(true);
		$nextAt = self::$sendThrottle[$key] ?? 0.0;
		if ($nextAt > $now) {
			return false;
		}
		self::$sendThrottle[$key] = $now + $cooldownSeconds;
		if (count(self::$sendThrottle) > 4096) {
			foreach (self::$sendThrottle as $throttleKey => $expiresAt) {
				if ($expiresAt <= $now) {
					unset(self::$sendThrottle[$throttleKey]);
				}
			}
			if (count(self::$sendThrottle) > 4096) {
				self::$sendThrottle = [];
			}
		}
		return true;
	}

	/**
	 * Returns the lazily initialized webhook configuration instance.
	 */
	public static function getWebhookConfig() : Config {
		return self::$config ??= new Config(ZuriAC::getInstance()->getDataFolder() . "webhook.yml", Config::YAML);
	}

	/**
	 * Converts hexadecimal color text to decimal color integer.
	 *
	 * @param string $hex Hex string with or without leading #.
	 */
	public static function textToHex(string $hex) : int|float {
		// why this??
		$hex = str_replace("#", "", $hex);

		return hexdec($hex); // ty php
	}
}
