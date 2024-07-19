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

namespace ReinfyTeam\Zuri\events;

use pocketmine\event\CancellableTrait;
use pocketmine\event\Event;
use ReinfyTeam\Zuri\config\ConfigManager;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\CharUtil;
use ReinfyTeam\Zuri\utils\ReplaceText;
use function random_int;

class CaptchaEvent extends Event {
	use CancellableTrait;

	private PlayerAPI $playerAPI;

	public function __construct(PlayerAPI $playerAPI) {
		$this->playerAPI = $playerAPI;
	}

	public function getPlayerAPI() : PlayerAPI {
		return $this->playerAPI;
	}

	protected function sendMessage() : void {
		$this->playerAPI->getPlayer()->sendMessage(ReplaceText::replace($this->playerAPI, ConfigManager::getData(ConfigManager::CAPTCHA_TEXT)));
	}

	protected function sendTip() : void {
		$this->playerAPI->getPlayer()->sendTip(ReplaceText::replace($this->playerAPI, ConfigManager::getData(ConfigManager::CAPTCHA_TEXT)));
	}

	protected function sendTitle() : void {
		$this->playerAPI->getPlayer()->sendSubTitle(ReplaceText::replace($this->playerAPI, ConfigManager::getData(ConfigManager::CAPTCHA_TEXT)));
	}

	public function call() : void {
		$this->sendCaptcha();

		parent::call();
	}

	public function sendCaptcha() : void {
		if ($this->playerAPI->isCaptcha()) {
			if ($this->playerAPI->getCaptchaCode() === "nocode") {
				$this->playerAPI->setCaptchaCode(CharUtil::generatorCode(ConfigManager::getData(ConfigManager::CAPTCHA_CODE_LENGTH)));
			}
			if (ConfigManager::getData(ConfigManager::CAPTCHA_RANDOMIZE) === true) {
				switch (random_int(1, 3)) {
					case 1:
						$this->sendMessage();
						break;
					case 2:
						$this->sendTip();
						break;
					case 3:
						$this->sendTitle();
						break;
				}
			} else {
				if (ConfigManager::getData(ConfigManager::CAPTCHA_MESSAGE) === true) {
					$this->sendMessage();
				}
				if (ConfigManager::getData(ConfigManager::CAPTCHA_TIP) === true) {
					$this->sendTip();
				}
				if (ConfigManager::getData(ConfigManager::CAPTCHA_TITLE) === true) {
					$this->sendTitle();
				}
			}
		}
	}
}