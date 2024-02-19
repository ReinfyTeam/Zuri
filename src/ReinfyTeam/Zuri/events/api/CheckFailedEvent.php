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

namespace ReinfyTeam\Zuri\events\api;

use pocketmine\event\CancellableTrait;
use ReinfyTeam\Zuri\player\PlayerAPI;

class CheckFailedEvent extends APIEvent {
	use CancellableTrait;

	public function __construct(PlayerAPI $playerAPI, string $supplier, string $subType) {
		$this->supplier = $supplier;
		$this->subType = $subType;
		parent::__construct($playerAPI);
	}

	public function getSupplier() : string {
		return $this->supplier;
	}

	public function getSubType() : string {
		return $this->supplier;
	}
}