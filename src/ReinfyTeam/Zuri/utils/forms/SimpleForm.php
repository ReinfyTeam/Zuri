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

declare(strict_types = 1);

namespace ReinfyTeam\Zuri\utils\forms;

use pocketmine\form\FormValidationException;
use function count;
use function gettype;
use function is_array;
use function is_int;
use function is_string;

class SimpleForm extends Form {
	public const IMAGE_TYPE_PATH = 0;
	public const IMAGE_TYPE_URL = 1;

	private string $content = "";

	/** @var array<int,int|string> */
	private array $labelMap = [];

	public function __construct(?callable $callable) {
		parent::__construct($callable);
		$this->data["type"] = "form";
		$this->data["title"] = "";
		$this->data["content"] = $this->content;
		$this->data["buttons"] = [];
	}

	public function processData(mixed &$data) : void {
		if ($data !== null) {
			if (!is_int($data)) {
				throw new FormValidationException("Expected an integer response, got " . gettype($data));
			}
			$buttons = $this->data["buttons"] ?? [];
			if (!is_array($buttons)) {
				throw new FormValidationException("Invalid buttons data");
			}
			$count = count($buttons);
			if ($data >= $count || $data < 0) {
				throw new FormValidationException("Button $data does not exist");
			}
			$data = $this->labelMap[$data] ?? null;
		}
	}

	/**
	 * @return $this
	 */
	public function setTitle(string $title) : self {
		$this->data["title"] = $title;
		return $this;
	}

	public function getTitle() : string {
		$title = $this->data["title"] ?? "";
		return is_string($title) ? $title : "";
	}

	public function getContent() : string {
		$content = $this->data["content"] ?? "";
		return is_string($content) ? $content : "";
	}

	/**
	 * @return $this
	 */
	public function setContent(string $content) : self {
		$this->data["content"] = $content;
		return $this;
	}

	/**
	 * @return $this
	 */
	public function addButton(string $text, int $imageType = -1, string $imagePath = "", ?string $label = null) : self {
		$content = ["text" => $text];
		if ($imageType !== -1) {
			$content["image"]["type"] = $imageType === 0 ? "path" : "url";
			$content["image"]["data"] = $imagePath;
		}
		$buttons = $this->data["buttons"] ?? [];
		if (!is_array($buttons)) {
			$buttons = [];
		}
		$buttons[] = $content;
		$this->data["buttons"] = $buttons;
		$this->labelMap[] = $label ?? count($this->labelMap);
		return $this;
	}
}
