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

final class CheckConstants {
	private function __construct() {
	}
	public const string AIMASSISTC_MAX_PITCH = "max-pitch";
	public const string AIMASSISTC_MIN_PITCH = "min-pitch";
	public const string AIMASSISTC_MIN_YAW = "min-yaw";
	public const string AIMASSISTD_MAX_ABS_PITCH = "max-abs-pitch";
	public const string AIMASSISTD_MAX_ABS_YAW = "max-abs-yaw";
	public const string AIMASSISTD_MIN_ABS_PITCH = "min-abs-pitch";
	public const string AIMASSISTD_MIN_ABS_YAW = "min-abs-yaw";
	public const string AIRMOVEMENT_AIR_LIMIT = "air-limit";
	public const string AIRMOVEMENT_EFFECT_AMPLIFIER = "effect-amplifier";
	public const string AIRMOVEMENT_EFFECT_CONST = "effect-const";
	public const string AIRMOVEMENT_EFFECT_MULTIPLIER = "effect-multiplier";
	public const string AUTOCLICKA_MAX_DEVIATION = "max-deviation";
	public const string AUTOCLICKA_MAX_TICKS = "max-ticks";
	public const string AUTOCLICKB_DIFF_TICKS = "diff-ticks";
	public const string AUTOCLICKB_DIFF_TIME = "diff-time";
	public const string AUTOCLICKC_ANIMATION_DIFF_TICKS = "animation-diff-ticks";
	public const string AUTOCLICKC_ANIMATION_DIFF_TIME = "animation-diff-time";
	public const string BLOCKREACH_MAX_CREATIVE_REACH = "max-creative-reach";
	public const string BLOCKREACH_MAX_SURVIVAL_REACH = "max-survival-reach";
	public const string CHESTAURA_TRANSACTION_DIVISIBLE = "transaction-divisible";
	public const string CHESTSTEALER_DIFF_TICKS = "diff-ticks";
	public const string CHESTSTEALER_DIFF_TIME = "diff-time";
	public const string CLICKTP_MAX_DISTANCE = "max-distance";
	public const string CRASHER_MAX_Y = "max-y";
	public const string DEVICESPOOFID_MAX_LENGTH = "max-length";
	public const string DEVICESPOOFID_MIN_LENGTH = "min-length";
	public const string DEVICESPOOFID_MIN_UNIQUE_CHARS = "min-unique-chars";
	public const string EDITIONFAKERB_ANDROID = "android";
	public const string EDITIONFAKERB_APPLE = "apple";
	public const string EDITIONFAKERB_NINTENDO = "nintendo";
	public const string EDITIONFAKERB_PLAYSTATION = "playstation";
	public const string EDITIONFAKERB_WINDOWS_10 = "windows-10";
	public const string EDITIONFAKERB_XBOX = "xbox";
	public const string FASTBOW_MAX_HIT_TIME = "max-hit-time";
	public const string FASTDROP_TIME_LIMIT = "time-limit";
	public const string FASTEAT_TIMEDIFF_LIMIT = "timediff-limit";
	public const string FASTTHROW_TIMEDIFF_LIMIT = "timediff-limit";
	public const string FLYA_MAX_GROUND_DIFF = "max-ground-diff";
	public const string FLYB_PACKET_BUFFER_LIMIT = "packet-buffer-limit";
	public const string FLYC_MAX_AIR_TICKS = "max-air-ticks";
	public const string IMPOSSIBLEPITCH_MAX_PITCH = "max-pitch";
	public const string INPUTSPOOFA_MAX_AXIS = "max-axis";
	public const string INPUTSPOOFA_MAX_VECTOR_LENGTH = "max-vector-length";
	public const string INVALIDPACKETS_MAX_PACKET_SPEED = "max-packet-speed";
	public const string INVENTORYCLEANER_DIFF_TICKS = "diff-ticks";
	public const string INVENTORYCLEANER_MAX_TRANSACTION = "max-transaction";
	public const string INVENTORYMOVE_MOVE_SENSITIVITY = "move-sensitivity";
	public const string KILLAURAB_DELTA_PITCH = "delta-pitch";
	public const string KILLAURAB_DELTA_YAW = "delta-yaw";
	public const string KILLAURAC_MAX_DISTANCE = "max-distance";
	public const string KILLAURAC_SUSPECIOUS_COUNT = "suspecious-count";
	public const string KILLAURAC_SUSPECIOUS_PITCH = "suspecious-pitch";
	public const string KILLAURAE_MAX_RANGE = "max-range";
	public const string MESSAGESPOOF_MAX_LENGTH = "max-length";
	public const string NOSLOWA_BUFFER_LIMIT = "buffer-limit";
	public const string NOSLOWA_MAX_PING = "max-ping";
	public const string NOSLOWA_MAX_XZ_DISTANCE_SQUARED = "max-xz-distance-squared";
	public const string OMNISPRINT_BUFFER_LIMIT = "buffer-limit";
	public const string OMNISPRINT_MAX_PING = "max-ping";
	public const string OMNISPRINT_MAX_SPEED = "max-speed";
	public const string OMNISPRINT_MIN_INPUT_LENGTH = "min-input-length";
	public const string REACHA_SURVIVAL_MAX_DISTANCE = "survival-max-distance";
	public const string REACHB_CREATIVE_MAX_DISTANCE = "creative-max-distance";
	public const string REACHB_SURVIVAL_MAX_DISTANCE = "survival-max-distance";
	public const string REACHC_MAX_REACH_EYE_DISTANCE = "max-reach-eye-distance";
	public const string REACHD_DAMAGER_SPRINTING_EYE_DISTANCE = "damager-sprinting-eye-distance";
	public const string REACHD_DEFAULT_EYE_DISTANCE = "default-eye-distance";
	public const string REACHD_NOT_SPRINTING_DAMAGER_EYE_DISTANCE = "not-sprinting-damager-eye-distance";
	public const string REACHD_NOT_SPRINTING_EYE_DISTANCE = "not-sprinting-eye-distance";
	public const string REACHD_REACH_EYE_LIMIT = "reach-eye-limit";
	public const string REACHD_SPRINTING_EYE_DISTANCE = "sprinting-eye-distance";
	public const string REACHE_EDGE_BUFFER_LIMIT = "edge-buffer-limit";
	public const string REACHE_EDGE_MAX_PING = "edge-max-ping";
	public const string REACHE_EDGE_MIN_STABILITY_TICKS = "edge-min-stability-ticks";
	public const string REACHE_EDGE_MIN_TELEPORT_TICKS = "edge-min-teleport-ticks";
	public const string REACHE_EDGE_PING_COMPENSATION = "edge-ping-compensation";
	public const string REACHE_EDGE_REACH_LIMIT = "edge-reach-limit";
	public const string REGENA_MAX_HEAL_AMOUNT = "max-heal-amount";
	public const string REGENB_MAX_HEALCOUNT = "max-healcount";
	public const string REGENB_MAX_HEALRATE = "max-healrate";
	public const string ROTATIONA_BUFFER_LIMIT = "buffer-limit";
	public const string ROTATIONA_COMBAT_WINDOW_TICKS = "combat-window-ticks";
	public const string ROTATIONA_MAX_DELTA_PITCH = "max-delta-pitch";
	public const string ROTATIONA_MAX_DELTA_YAW = "max-delta-yaw";
	public const string ROTATIONA_MAX_PING = "max-ping";
	public const string ROTATIONA_MIN_DELTA_PITCH = "min-delta-pitch";
	public const string ROTATIONA_MIN_DELTA_YAW = "min-delta-yaw";
	public const string ROTATIONA_PITCH_STEP_EPSILON = "pitch-step-epsilon";
	public const string ROTATIONA_YAW_STEP_EPSILON = "yaw-step-epsilon";
	public const string ROTATIONB_COMBAT_WINDOW_TICKS = "combat-window-ticks";
	public const string ROTATIONB_MAX_PING = "max-ping";
	public const string ROTATIONB_SNAP_BUFFER_LIMIT = "snap-buffer-limit";
	public const string ROTATIONB_SNAP_MAX_DELTA_PITCH = "snap-max-delta-pitch";
	public const string ROTATIONB_SNAP_MIN_DELTA_YAW = "snap-min-delta-yaw";
	public const string ROTATIONB_SNAP_REPEAT_EPSILON = "snap-repeat-epsilon";
	public const string SCAFFOLDA_BOX_RANGE_X = "box-range-x";
	public const string SCAFFOLDA_BOX_RANGE_Y = "box-range-y";
	public const string SCAFFOLDA_BOX_RANGE_Z = "box-range-z";
	public const string SCAFFOLDB_SUSPECIOUS_PITCH_LIMIT = "suspecious-pitch-limit";
	public const string SCAFFOLDC_MAX_PLACE_DISTANCE = "max-place-distance";
	public const string SCAFFOLDE_EXPANSION_BUFFER_LIMIT = "expansion-buffer-limit";
	public const string SCAFFOLDE_EXPANSION_MAX_PING = "expansion-max-ping";
	public const string SCAFFOLDE_MAX_HORIZONTAL_DISTANCE_SQUARED = "max-horizontal-distance-squared";
	public const string SCAFFOLDE_MAX_PLACE_INTERVAL = "max-place-interval";
	public const string SCAFFOLDE_MAX_SEQUENTIAL_DISTANCE = "max-sequential-distance";
	public const string SCAFFOLDF_GHOST_BUFFER_LIMIT = "ghost-buffer-limit";
	public const string SCAFFOLDF_GHOST_MAX_PING = "ghost-max-ping";
	public const string SCAFFOLDF_GHOST_MAX_PLACE_INTERVAL = "ghost-max-place-interval";
	public const string SCAFFOLDF_GHOST_MAX_PLAYER_STEP_SQUARED = "ghost-max-player-step-squared";
	public const string SCAFFOLDF_GHOST_MIN_BLOCK_STEP = "ghost-min-block-step";
	public const string SCAFFOLDF_GHOST_MIN_PLAYER_BLOCK_DISTANCE_SQUARED = "ghost-min-player-block-distance-squared";
	public const string SPAMA_MAX_VIOLATION_RATE = "max-violation-rate";
	public const string SPEEDA_FRICTION_FACTOR = "friction-factor";
	public const string SPEEDA_GROUND_FACTOR = "ground-factor";
	public const string SPEEDA_ICE_FACTOR = "ice-factor";
	public const string SPEEDA_JUMP_FACTOR = "jump-factor";
	public const string SPEEDA_KNOCKBACK_FACTOR = "knockback-factor";
	public const string SPEEDA_LASTJUMP_FACTOR = "lastjump-factor";
	public const string SPEEDA_LASTMOVE_FACTOR = "lastmove-factor";
	public const string SPEEDA_THRESHOLD = "threshold";
	public const string SPEEDA_XZ_DISTANCE = "xz-distance";
	public const string SPEEDB_ICE_WALKING_DISTANCE_LIMIT = "ice-walking-distance-limit";
	public const string SPEEDB_ICE_WALKING_SPEED_LIMIT = "ice-walking-speed-limit";
	public const string SPEEDB_JUMP_DISTANCE_LIMIT = "jump-distance-limit";
	public const string SPEEDB_JUMP_SPEED_LIMIT = "jump-speed-limit";
	public const string SPEEDB_SPEED_EFFECT_DISTANCE_LIMIT = "speed-effect-distance-limit";
	public const string SPEEDB_SPEED_EFFECT_LIMIT = "speed-effect-limit";
	public const string SPEEDB_SPRINTING_DISTANCE_LIMIT = "sprinting-distance-limit";
	public const string SPEEDB_SPRINTING_SPEED_LIMIT = "sprinting-speed-limit";
	public const string SPEEDB_STAIRS_SPEED_LIMIT = "stairs-speed-limit";
	public const string SPEEDB_STAIRS_WALKING_DISTANCE_LIMIT = "stairs-walking-distance-limit";
	public const string SPEEDB_TIME_EFFECT_LIMIT = "time-effect-limit";
	public const string SPEEDB_TIME_LIMIT = "time-limit";
	public const string SPEEDB_TOP_BLOCK_LIMIT = "top-block-limit";
	public const string SPEEDB_WAKLING_DISTANCE_LIMIT = "wakling-distance-limit";
	public const string SPEEDB_WALKING_SPEED_LIMIT = "walking-speed-limit";
	public const string SPIDER_LIMIT_Y_DIFF = "limit-y-diff";
	public const string STEP_JUMP_LIMIT = "jump-limit";
	public const string STEP_STAIRS_LIMIT = "stairs-limit";
	public const string STEP_Y_LIMIT = "y-limit";
	public const string TIMERA_MAX_DIFF = "max-diff";
	public const string TIMERB_DIFF_BALANCE = "diff-balance";
	public const string TIMERD_DRIFT_BUFFER_LIMIT = "drift-buffer-limit";
	public const string TIMERD_DRIFT_EXPECTED_MS_PER_TICK = "drift-expected-ms-per-tick";
	public const string TIMERD_DRIFT_MAX_NEGATIVE = "drift-max-negative";
	public const string TIMERD_DRIFT_MAX_TICK_JUMP = "drift-max-tick-jump";
	public const string TIMERD_DRIFT_WARMUP_SAMPLES = "drift-warmup-samples";
	public const string TOWER_INVALID_PITCH = "invalid-pitch";
	public const string TOWER_MARGIN_ERROR = "margin-error";
	public const string VELOCITYA_BUFFER_LIMIT = "buffer-limit";
	public const string VELOCITYA_MAX_OBSERVE_TICKS = "max-observe-ticks";
	public const string VELOCITYA_MAX_PING = "max-ping";
	public const string VELOCITYA_MIN_RESPONSE_DISTANCE_SQUARED = "min-response-distance-squared";
	public const string VELOCITYA_START_OBSERVE_TICKS = "start-observe-ticks";
}
