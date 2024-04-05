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

namespace ReinfyTeam\Zuri\events;

use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\Discord;

class KickEvent {
	private PlayerAPI $player;
	private string $moduleName;
	private string $subType;

	public function __construct(PlayerAPI $player, string $moduleName, string $subType) {
		$this->player = $player;
		$this->moduleName = $moduleName;
		$this->subType = $subType;
	}

	public function getPlayer() : PlayerAPI {
		return $this->player;
	}

	public function getModuleName() : string {
		return $this->moduleName;
	}

	public function getSubType() : string {
		return $this->subType;
	}

	public function kick() {
		Discord::Send($this->player, Discord::KICK, ["name" => $this->getModuleName(), "subType" => $this->getSubType()]);
	}
}
