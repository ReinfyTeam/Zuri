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

namespace jojoe77777\FormAPI;

use pocketmine\form\FormValidationException;
use function gettype;
use function is_bool;

class ModalForm extends Form {
	private string $content = "";

	public function __construct(?callable $callable) {
		parent::__construct($callable);
		$this->data["type"] = "modal";
		$this->data["title"] = "";
		$this->data["content"] = $this->content;
		$this->data["button1"] = "";
		$this->data["button2"] = "";
	}

	public function processData(&$data) : void {
		if ($data !== null && !is_bool($data)) {
			throw new FormValidationException("Expected a boolean response, got " . gettype($data));
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
		return $this->data["title"];
	}

	public function getContent() : string {
		return $this->data["content"];
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
	public function setButton1(string $text) : self {
		$this->data["button1"] = $text;
		return $this;
	}

	public function getButton1() : string {
		return $this->data["button1"];
	}

	/**
	 * @return $this
	 */
	public function setButton2(string $text) : self {
		$this->data["button2"] = $text;
		return $this;
	}

	public function getButton2() : string {
		return $this->data["button2"];
	}
}
