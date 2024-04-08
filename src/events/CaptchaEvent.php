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

use ReinfyTeam\Zuri\config\ConfigManager;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\CharUtil;
use ReinfyTeam\Zuri\utils\ReplaceText;
use function rand;

class CaptchaEvent extends ConfigManager {
	private PlayerAPI $playerAPI;

	public function __construct(PlayerAPI $playerAPI) {
		$this->playerAPI = $playerAPI;
	}

	public function getPlayerAPI() : PlayerAPI {
		return $this->playerAPI;
	}

	public function sendMessage() {
		$this->playerAPI->getPlayer()->sendMessage(ReplaceText::replace($this->playerAPI, self::getData(self::CAPTCHA_TEXT)));
	}

	public function sendTip() {
		$this->playerAPI->getPlayer()->sendTip(ReplaceText::replace($this->playerAPI, self::getData(self::CAPTCHA_TEXT)));
	}

	public function sendTitle() {
		$this->playerAPI->getPlayer()->sendSubTitle(ReplaceText::replace($this->playerAPI, self::getData(self::CAPTCHA_TEXT)));
	}

	public function sendCaptcha() {
		if ($this->playerAPI->isCaptcha()) {
			if ($this->playerAPI->getCaptchaCode() === "nocode") {
				$this->playerAPI->setCaptchaCode(CharUtil::generatorCode(self::getData(self::CAPTCHA_CODE_LENGTH)));
			}
			if (self::getData(self::CAPTCHA_RANDOMIZE) === true) {
				switch(rand(1, 3)) {
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
				if (self::getData(self::CAPTCHA_MESSAGE) === true) {
					$this->sendMessage();
				}
				if (self::getData(self::CAPTCHA_TIP) === true) {
					$this->sendTip();
				}
				if (self::getData(self::CAPTCHA_TITLE) === true) {
					$this->sendTitle();
				}
			}
		}
	}
}