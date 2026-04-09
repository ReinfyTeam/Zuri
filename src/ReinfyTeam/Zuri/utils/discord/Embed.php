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
use DateTimeZone;
use function is_array;

class Embed {
	/** @var array<string,mixed> */
	protected array $data = [];

	/** @return array<string,mixed> */
	public function asArray() : array {
		return $this->data;
	}

	public function setAuthor(string $name, ?string $url = null, ?string $iconURL = null) : self {
		$author = $this->data["author"] ?? [];
		if (!is_array($author)) {
			$author = [];
		}
		$author["name"] = $name;
		if ($url !== null) {
			$author["url"] = $url;
		}
		if ($iconURL !== null) {
			$author["icon_url"] = $iconURL;
		}
		$this->data["author"] = $author;
		return $this;
	}

	public function setTitle(string $title) : self {
		$this->data["title"] = $title;
		return $this;
	}

	public function setDescription(string $description) : self {
		$this->data["description"] = $description;
		return $this;
	}

	public function setColor(int $color) : self {
		$this->data["color"] = $color;
		return $this;
	}

	public function addField(string $name, string $value, bool $inline = false) : self {
		$fields = $this->data["fields"] ?? [];
		if (!is_array($fields)) {
			$fields = [];
		}
		$fields[] = [
			"name" => $name,
			"value" => $value,
			"inline" => $inline,
		];
		$this->data["fields"] = $fields;
		return $this;
	}

	public function setThumbnail(string $url) : self {
		$thumbnail = $this->data["thumbnail"] ?? [];
		if (!is_array($thumbnail)) {
			$thumbnail = [];
		}
		$thumbnail["url"] = $url;
		$this->data["thumbnail"] = $thumbnail;
		return $this;
	}

	public function setImage(string $url) : self {
		$image = $this->data["image"] ?? [];
		if (!is_array($image)) {
			$image = [];
		}
		$image["url"] = $url;
		$this->data["image"] = $image;
		return $this;
	}

	public function setFooter(string $text, ?string $iconURL = null) : self {
		$footer = $this->data["footer"] ?? [];
		if (!is_array($footer)) {
			$footer = [];
		}
		$footer["text"] = $text;
		if ($iconURL !== null) {
			$footer["icon_url"] = $iconURL;
		}
		$this->data["footer"] = $footer;
		return $this;
	}

	public function setTimestamp(DateTime $timestamp) : self {
		$timestamp->setTimezone(new DateTimeZone("UTC"));
		$this->data["timestamp"] = $timestamp->format("Y-m-d\TH:i:s.v\Z");
		return $this;
	}

	public static function create() : Embed {
		return new Embed();
	}
}
