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

namespace ReinfyTeam\Zuri\utils\Discord;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\thread\NonThreadSafeValue;
use function curl_close;
use function curl_exec;
use function curl_getinfo;
use function curl_init;
use function curl_setopt;
use function in_array;
use function json_encode;

class WebhookSendTask extends AsyncTask {
	protected NonThreadSafeValue $webhook;
	protected NonThreadSafeValue $message;

	public function __construct(Webhook $webhook, Message $message) {
		$this->webhook = $webhook;
		$this->message = $message;
	}

	public function onRun() : void {
		$ch = curl_init($this->webhook->getURL());
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($this->message));
		curl_setopt($ch, CURLOPT_POST,true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
		$this->setResult([curl_exec($ch), curl_getinfo($ch, CURLINFO_RESPONSE_CODE)]);
		curl_close($ch);
	}

	public function onCompletion() : void {
		$response = $this->getResult();
		if (!in_array($response[1], [200, 204], true)) {
			Server::getInstance()->getLogger()->error("[Discord] We have error ({$response[1]}): " . $response[0]);
		}
	}
}
