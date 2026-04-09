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

use JsonSerializable;
use function is_array;
use function is_string;

class Message implements JsonSerializable {
	/** @var array<string,mixed> */
	protected array $data = [];

	/** @param list<Embed>|null $embeds */
	public function __construct(?array $embeds = null) {
		if ($embeds !== null) {
			foreach ($embeds as $embed) {
				$this->addEmbed($embed);
			}
		}
	}

	public function setContent(string $content) : self {
		$this->data["content"] = $content;
		return $this;
	}

	public function getContent() : ?string {
		$content = $this->data["content"] ?? null;
		return is_string($content) ? $content : null;
	}

	public function getUsername() : ?string {
		$username = $this->data["username"] ?? null;
		return is_string($username) ? $username : null;
	}

	public function setUsername(string $username) : self {
		$this->data["username"] = $username;
		return $this;
	}

	public function getAvatarURL() : ?string {
		$avatarUrl = $this->data["avatar_url"] ?? null;
		return is_string($avatarUrl) ? $avatarUrl : null;
	}

	public function setAvatarURL(string $avatarURL) : self {
		$this->data["avatar_url"] = $avatarURL;
		return $this;
	}

	public function addEmbed(Embed $embed) : ?self {
		if (!empty(($arr = $embed->asArray()))) {
			$embeds = $this->data["embeds"] ?? [];
			if (!is_array($embeds)) {
				$embeds = [];
			}
			$embeds[] = $arr;
			$this->data["embeds"] = $embeds;
			return $this;
		}
		return null;
	}

	public function setTextToSpeech(bool $ttsEnabled) : self {
		$this->data["tts"] = $ttsEnabled;
		return $this;
	}

	/** @return array<string,mixed> */
	public function jsonSerialize() : array {
		return $this->data;
	}

	/** @param list<Embed>|null $embeds */
	public static function create(?array $embeds = null) : Message {
		return new Message($embeds);
	}
}
