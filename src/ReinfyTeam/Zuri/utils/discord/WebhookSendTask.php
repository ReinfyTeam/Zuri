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

use pocketmine\scheduler\AsyncTask;
use pocketmine\thread\NonThreadSafeValue;
use ReinfyTeam\Zuri\ZuriAC;
use function curl_close;
use function curl_exec;
use function curl_getinfo;
use function curl_init;
use function curl_setopt;
use function in_array;
use function is_array;
use function json_encode;

class WebhookSendTask extends AsyncTask {
	/** @var NonThreadSafeValue<Webhook> */
	protected NonThreadSafeValue $webhook;
	/** @var NonThreadSafeValue<Message> */
	protected NonThreadSafeValue $message;

	public function __construct(Webhook $webhook, Message $message) {
		$this->webhook = new NonThreadSafeValue($webhook);
		$this->message = new NonThreadSafeValue($message);
	}

	public function onRun() : void {
		/** @var Webhook $webhook */
		$webhook = $this->webhook->deserialize();
		/** @var Message $message */
		$message = $this->message->deserialize();

		$ch = curl_init($webhook->getURL());
		if ($ch === false) {
			$this->setResult(["", 0]);
			return;
		}

		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
		$responseBody = (string) (curl_exec($ch) ?: "");
		$responseCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		$this->setResult([
			$responseBody,
			$responseCode,
		]);
		curl_close($ch);
	}

	public function onCompletion() : void {
		$result = $this->getResult();
		if (!is_array($result)) {
			return;
		}
		$responseBody = (string) ($result[0] ?? "");
		$responseCode = (int) ($result[1] ?? 0);

		if (!in_array($responseCode, [200, 204], true)) {
			ZuriAC::getInstance()->getLogger()->debug(
				"[Discord] [ERROR]: An error occurred while sending to discord ({$responseCode}): {$responseBody}"
			);
		}
	}
}
