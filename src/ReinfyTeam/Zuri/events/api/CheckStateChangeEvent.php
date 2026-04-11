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

namespace ReinfyTeam\Zuri\events\api;

use pocketmine\event\CancellableTrait;
use pocketmine\event\Event;

/**
 * Fired before toggling check enabled state.
 */
class CheckStateChangeEvent extends Event {
	use CancellableTrait;

	/**
	 * Creates a check state change event payload.
	 *
	 * @return void
	 */
	public function __construct(
		private string $checkName,
		private ?string $subType,
		private bool $enabled
	) {
	}

	/**
	 * Gets the target check name.
	 */
	public function getCheckName() : string {
		return $this->checkName;
	}

	/**
	 * Gets the target check subtype.
	 */
	public function getSubType() : ?string {
		return $this->subType;
	}

	/**
	 * Gets the requested enabled state.
	 */
	public function isEnabled() : bool {
		return $this->enabled;
	}

	/**
	 * Updates the requested enabled state.
	 */
	public function setEnabled(bool $enabled) : void {
		$this->enabled = $enabled;
	}
}
