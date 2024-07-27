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

use pocketmine\form\Form as IForm;
use pocketmine\player\Player;

abstract class Form implements IForm {
	/** @var array */
	protected array $data = [];
	/** @var callable|null */
	private $callable;

	public function __construct(?callable $callable) {
		$this->callable = $callable;
	}

	/**
	 * @deprecated
	 * @see Player::sendForm()
	 */
	public function sendToPlayer(Player $player) : void {
		$player->sendForm($this);
	}

	public function getCallable() : ?callable {
		return $this->callable;
	}

	public function setCallable(?callable $callable): void {
		$this->callable = $callable;
	}

	public function handleResponse(Player $player, $data) : void {
		$this->processData($data);
		$callable = $this->getCallable();
		if ($callable !== null) {
			$callable($player, $data);
		}
	}

	public function processData(&$data) : void {
	}

	public function jsonSerialize() : array {
		return $this->data;
	}
}
