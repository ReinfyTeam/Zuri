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

namespace ReinfyTeam\Zuri\task;

use pocketmine\scheduler\Task;
use ReinfyTeam\Zuri\APIProvider;
use ReinfyTeam\Zuri\events\CaptchaEvent;
use ReinfyTeam\Zuri\player\PlayerAPI;

class CaptchaTask extends Task {
	private static $instance = null;
	protected APIProvider $plugin;

	public function __construct(APIProvider $plugin) {
		$this->plugin = $plugin;
	}

	public function onRun() : void {
		self::$instance = $this;
		foreach ($this->plugin->getServer()->getOnlinePlayers() as $player) {
			if ($player instanceof PlayerAPI) {
				(new CaptchaEvent($player))->sendCaptcha();
			}
		}
	}

	public static function getInstance() : self {
		return self::$instance;
	}
}
