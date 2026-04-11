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

use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\player\Player;
use ReinfyTeam\Zuri\lang\Lang;
use ReinfyTeam\Zuri\lang\LangKeys;
use ReinfyTeam\Zuri\player\PlayerAPI;
use function microtime;

/**
 * Captures immutable packet/network state for async worker evaluation.
 *
 * Used by: BadPackets, MessageSpoof, InvalidPackets, Crasher, FastDrop,
 * FastEat, FastThrow, ImpossiblePitch, SelfHit, and other network checks
 */
class NetworkSnapshot extends AsyncSnapshot {
	public const SCHEMA_VERSION = 1;

	/** Packet metadata. */
	private string $packetName;
	private float $packetTime;

	/** Player state. */
	private int $ping;
	private bool $survival;
	private int $onlineTime;
	private int $attackTicks;
	private int $teleportTicks;

	/** Packet-specific data (serialized). */
	/** @var array<string,mixed> */
	private array $packetData;

	/** Cached network data. */
	/** @var array<string,mixed> */
	private array $cachedData = [];

	/**
	 * Captures immutable packet and player network context.
	 *
	 * @param string $checkType Check type identifier.
	 * @param Player $player Player entity sending the packet.
	 * @param PlayerAPI $playerAPI Player API wrapper for tracked state.
	 * @param DataPacket $packet Packet instance being evaluated.
	 * @return void
	 */
	public function __construct(string $checkType, Player $player, PlayerAPI $playerAPI, DataPacket $packet) {
		parent::__construct($checkType);

		$this->packetName = $packet::class;
		$this->packetTime = microtime(true);
		$this->ping = (int) ($player->getNetworkSession()->getPing() ?? 0);
		$this->survival = $player->isSurvival();
		$this->onlineTime = $playerAPI->getOnlineTime();
		$this->attackTicks = $playerAPI->getAttackTicks();
		$this->teleportTicks = $playerAPI->getTeleportTicks();

		// Extract serializable packet data
		$this->packetData = [];
	}

	/**
	 * Add packet-specific data that is JSON-serializable.
	 *
	 * @param string $key Packet data key.
	 * @param mixed $value Packet data value.
	 * @return self Current instance for fluent chaining.
	 */
	public function addPacketData(string $key, mixed $value) : self {
		$this->packetData[$key] = $value;
		return $this;
	}

	/**
	 * Add cached network interaction data.
	 *
	 * @param string $key Cached data key.
	 * @param mixed $value Cached data value.
	 * @return self Current instance for fluent chaining.
	 */
	public function addCachedData(string $key, mixed $value) : self {
		$this->cachedData[$key] = $value;
		return $this;
	}

	/**
	 * Builds the immutable payload for async processing.
	 *
	 * @return array<string,mixed> Serialized network snapshot payload.
	 */
	public function build() : array {
		return [
			"type" => $this->checkType,
			"schemaVersion" => self::SCHEMA_VERSION,
			"captureTime" => $this->captureTime,
			"packetName" => $this->packetName,
			"packetTime" => $this->packetTime,
			"ping" => $this->ping,
			"survival" => $this->survival,
			"onlineTime" => $this->onlineTime,
			"attackTicks" => $this->attackTicks,
			"teleportTicks" => $this->teleportTicks,
			"packetData" => $this->packetData,
			"cachedData" => $this->cachedData,
		];
	}

	/**
	 * Validates snapshot values before async dispatch.
	 *
	 * @throws SnapshotException If snapshot values are invalid.
	 */
	public function validate() : void {
		if ($this->packetName === "") {
			throw new SnapshotException(Lang::get(LangKeys::DEBUG_SNAPSHOT_INVALID_PACKET_NAME));
		}
	}
}
