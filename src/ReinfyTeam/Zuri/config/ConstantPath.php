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

namespace ReinfyTeam\Zuri\config;

interface ConstantPath {
	public const TOLERANCE_PERCENTAGE = "tolerance_percentage";

	// Types of Speed (Vertical/Horizontal)
	public const SPEED_HORIZONTAL = "speed.horizontal";
	public const SPEED_VERTICAL = "speed.vertical";

	// Horizontal movement
	public const SPEED_WALKING = self::SPEED_HORIZONTAL . ".walking";
	public const SPEED_SPRINTING = self::SPEED_HORIZONTAL . ".sprinting";
	public const SPEED_JUMPING_HORIZONTAL = self::SPEED_HORIZONTAL . ".jumping";

	// Sneaking and crawling
	public const SPEED_SNEAKING = self::SPEED_HORIZONTAL . ".sneaking";
	public const SPEED_SNEAKING_SWIFT = self::SPEED_HORIZONTAL . ".sneaking_swift";
	public const SPEED_SNEAKING_SOUL = self::SPEED_HORIZONTAL . ".sneaking_soul";
	public const SPEED_CRAWLING = self::SPEED_HORIZONTAL . ".crawling";

	// Swimming: horizontal movement
	public const SPEED_SWIMMING = self::SPEED_HORIZONTAL . ".swimming";

	// Game modes and projectiles
	public const SPEED_CREATIVE_FLIGHT = self::SPEED_HORIZONTAL . ".creative_flight";
	public const SPEED_CREATIVE_SPRINT_FLIGHT = self::SPEED_HORIZONTAL . ".creative_sprint_flight";
	public const SPEED_SPECTATOR_FLIGHT = self::SPEED_HORIZONTAL . ".spectator_flight";
	public const SPEED_SPECTATOR_SPRINT_FLIGHT = self::SPEED_HORIZONTAL . ".spectator_sprint_flight";

	// Status effects
	public const SPEED_EFFECT = self::SPEED_HORIZONTAL . ".speed_effect";

	// Vertical movement
	public const SPEED_JUMPING = self::SPEED_VERTICAL . ".jumping";
	public const SPEED_STAIRS_UP = self::SPEED_VERTICAL . ".stairs_up";
	public const SPEED_STAIRS_DOWN = self::SPEED_VERTICAL . ".stairs_down";
	public const SPEED_CLIMBABLE_UP = self::SPEED_VERTICAL . ".climbable_up";
	public const SPEED_CLIMBABLE_DOWN = self::SPEED_VERTICAL . ".climbable_down";
	public const SPEED_LAVA_LIQUID = self::SPEED_VERTICAL . ".lava_liquid";
	public const SPEED_LEVITATION = self::SPEED_VERTICAL . ".levitation";
	public const SPEED_CREATIVE_SPECTATOR_VERTICAL = self::SPEED_VERTICAL . ".creative_spectator_vertical";
	public const SPEED_FALLING_PLAYER_TERMINAL = self::SPEED_VERTICAL . ".falling_player_terminal";
	public const SPEED_SCAFFOLDING_UP = self::SPEED_VERTICAL . ".scaffolding_up";
	public const SPEED_SCAFFOLDING_DOWN = self::SPEED_VERTICAL . ".scaffolding_down";
	public const SPEED_SWIM_UNDERWATER = self::SPEED_VERTICAL . ".swim_underwater";
}