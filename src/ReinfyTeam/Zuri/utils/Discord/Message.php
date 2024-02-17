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

namespace ReinfyTeam\Zuri\utils\Discord;

class Message implements \JsonSerializable {
	protected $data = [];

	public function __construct(array $embeds = null) {
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
		return $this->data["content"];
	}

	public function getUsername() : ?string {
		return $this->data["username"];
	}

	public function setUsername(string $username) : self {
		$this->data["username"] = $username;
		return $this;
	}

	public function getAvatarURL() : ?string {
		return $this->data["avatar_url"];
	}

	public function setAvatarURL(string $avatarURL) : self {
		$this->data["avatar_url"] = $avatarURL;
		return $this;
	}

	public function addEmbed(Embed $embed) : ?self {
		if (!empty(($arr = $embed->asArray()))) {
			$this->data["embeds"][] = $arr;
			return $this;
		}
		return null;
	}

	public function setTextToSpeech(bool $ttsEnabled) : self {
		$this->data["tts"] = $ttsEnabled;
		return $this;
	}

	public function jsonSerialize() {
		return $this->data;
	}

	public static function create(array $embeds = null) : Message {
		return new Message($embeds);
	}
}
