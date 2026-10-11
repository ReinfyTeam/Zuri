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

namespace ReinfyTeam\Zuri\player;

use JsonSerializable;
use function array_merge;
use function spl_object_id;

class ExternalData implements JsonSerializable {
	/** @var array<int,array<string,array<string,mixed>>> */
	private array $externalData = [];

	public function setExternalData(PlayerZuri $player, string $module, string $parameter, mixed $value) : void {
		$this->externalData[spl_object_id($player)][$module][$parameter] = $value;
	}

	public function getExternalData(PlayerZuri $player, string $module, string $parameter) : mixed {
		return $this->externalData[spl_object_id($player)][$module][$parameter] ?? null;
	}

	/** @return array<string,mixed> */
	public function getAllExternalData(PlayerZuri $player) : array {
		$data = [];
		foreach ($this->externalData[spl_object_id($player)] ?? [] as $moduleData) {
			$data = array_merge($data, $moduleData);
		}
		return $data;
	}

	/** @return array<string,mixed> */
	public function getModuleExternalData(PlayerZuri $player, string $moduleName) : array {
		return $this->externalData[spl_object_id($player)][$moduleName] ?? [];
	}

	/** @return array<int,array<string,array<string,mixed>>> */
	public function jsonSerialize() : array {
		return $this->externalData;
	}
}