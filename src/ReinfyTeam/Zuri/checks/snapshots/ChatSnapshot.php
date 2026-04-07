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

namespace ReinfyTeam\Zuri\checks\snapshots;

use pocketmine\player\Player;
use ReinfyTeam\Zuri\player\PlayerAPI;
use function is_array;
use function is_string;
use function microtime;
use function strlen;

/**
 * Captures immutable chat state for async worker evaluation.
 *
 * Used by: SpamA, SpamB and other chat checks
 */
class ChatSnapshot extends AsyncSnapshot {
	/** Chat message. */
	private string $message;

	/** Message metadata. */
	private float $messageTime;
	private int $messageLength;

	/** Player state. */
	private int $ping;
	private bool $survival;
	private int $onlineTime;

	/** Chat history snapshot. */
	private array $recentMessages;

	/** Cached chat data. */
	private mixed $cachedData = [];

	public function __construct(string $checkType, Player $player, PlayerAPI $playerAPI, string $message) {
		parent::__construct($checkType);

		$this->message = $message;
		$this->messageTime = microtime(true);
		$this->messageLength = strlen($message);
		$this->ping = $player->getNetworkSession()->getPing();
		$this->survival = $player->isSurvival();
		$this->onlineTime = $playerAPI->getOnlineTime();

		// Capture recent message history (last 10)
		$this->recentMessages = $playerAPI->getExternalData("chat_history") ?? [];
		if (!is_array($this->recentMessages)) {
			$this->recentMessages = [];
		}
	}

	/**
	 * Add cached chat data.
	 */
	public function addCachedData(string $key, mixed $value) : self {
		$this->cachedData[$key] = $value;
		return $this;
	}

	public function build() : array {
		return [
			"type" => $this->checkType,
			"message" => $this->message,
			"messageTime" => $this->messageTime,
			"messageLength" => $this->messageLength,
			"ping" => $this->ping,
			"survival" => $this->survival,
			"onlineTime" => $this->onlineTime,
			"recentMessages" => $this->recentMessages,
			"cachedData" => $this->cachedData,
		];
	}

	public function validate() : void {
		if (!is_string($this->message)) {
			throw new SnapshotException("Invalid message in chat snapshot");
		}
		if ($this->messageLength < 0) {
			throw new SnapshotException("Invalid message length in chat snapshot");
		}
	}
}
