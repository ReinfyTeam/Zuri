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

namespace ReinfyTeam\Zuri\thread;

use pmmp\thread\ThreadSafe;
use pmmp\thread\ThreadSafeArray;
use function count;
use function is_array;
use function unserialize;
use function serialize;

final class CheckResults extends ThreadSafe {
	private ThreadSafeArray $queue;

	public function __construct() {
		$this->queue = new ThreadSafeArray();
	}

	public function addResult(array $result, string $check, ?string $player) : void {
		$this->queue[] = serialize([
			'result' => $result,
			'check' => $check,
			'player' => $player
		]);
	}

	/** @return array{result:array,check:class-string,player:?string}|null */
	public function getNextResult() : ?array {
		$result = $this->queue->shift();
		if(!is_string($result)){
			return null;
		}

		$result = unserialize($result, ["allowed_classes" => false]);
		return is_array($result) ? $result : null;
	}

	public function isEmpty() : bool {
		return count($this->queue) === 0;
	}
}