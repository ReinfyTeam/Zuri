<?php

declare(strict_types=1);

namespace ReinfyTeam\Zuri\config;

final class CacheData {
	private function __construct() {
	}

	public const string AIRJUMP_LAST_UP_DISTANCE = "AirJump.lastUpDistance";
	public const string ANTIVOID_LAST_Y = "AntiVoid.lastY";
	public const string AUTOCLICK_A_AVG_DEVIATION = "AutoClickA.avgDeviation";
	public const string AUTOCLICK_A_AVG_SPEED = "AutoClickA.avgSpeed";
	public const string AUTOCLICK_A_TICKS = "AutoClickA.ticks";
	public const string AUTOCLICK_B_LAST_CLICK = "AutoClickB.lastClick";
	public const string AUTOCLICK_B_TICKS = "AutoClickB.ticks";
	public const string AUTOCLICK_C_LAST_CLICK = "AutoClickC.lastClick";
	public const string AUTOCLICK_C_TICKS = "AutoClickC.ticks";
	public const string CHESTAURA_COUNT_TRANSACTION = "ChestAura.countTransaction";
	public const string CHESTAURA_TIME_OPEN_CHEST = "ChestAura.timeOpenChest";
	public const string CHESTSTEALER_LAST_TIME = "ChestStealer.lastTime";
	public const string CHESTSTEALER_TICKS = "ChestStealer.ticks";
	public const string FASTBOW_CURRENT_HS_INDEX = "FastBow.currentHsIndex";
	public const string FASTBOW_HS_HIT_TIME = "FastBow.hsHitTime";
	public const string FASTBOW_HS_TIME_LIST = "FastBow.hsTimeList";
	public const string FASTBOW_HS_TIME_SUM = "FastBow.hsTimeSum";
	public const string FASTBOW_SHOOT_FIRST_TICK = "FastBow.shootFirstTick";
	public const string FASTDROP_LAST_TICK = "FastDrop.lastTick";
	public const string FASTEAT_LAST_TICK = "FastEat.lastTick";
	public const string FASTTHROW_LAST_USE = "FastThrow.lastUse";
	public const string FLY_A_LAST_TIME = "FlyA.lastTime";
	public const string FLY_A_LAST_Y_NO_GROUND = "FlyA.lastYNoGround";
	public const string FLY_B_BUFFER = "FlyB.buffer";
	public const string GHOSTHAND_A_BUFFER = "GhostHandA.buffer";
	public const string HITBOX_A_BUFFER = "HitboxA.buffer";
	public const string INSTABREAK_BREAK_TIMES = "InstaBreak.breakTimes";
	public const string ITEMLERP_A_BUFFER = "ItemLerpA.buffer";
	public const string ITEMLERP_A_LAST_HELD_SWITCH = "ItemLerpA.lastHeldSwitch";
	public const string INVENTORYCLEANER_TICKS_TRANSACTION = "InventoryCleaner.ticksTransaction";
	public const string INVENTORYCLEANER_TRANSACTION = "InventoryCleaner.transaction";
	public const string INVALID_PACKETS_LAST_PACKET_TICK = "InvalidPackets.lastPacketTick";
	public const string INVALID_PACKETS_TICK_PACKETS = "InvalidPackets.tickPackets";
	public const string NOSLOW_A_BUFFER = "NoSlowA.buffer";
	public const string OMNISPRINT_A_BUFFER = "OmniSprintA.buffer";
	public const string OMNISPRINT_A_LAST_MOVE_XZ = "OmniSprintA.lastMoveXZ";
	public const string REACH_E_BUFFER = "ReachE.buffer";
	public const string REGEN_B_HEAL_COUNT = "RegenB.healCount";
	public const string REGEN_B_HEAL_TIME = "RegenB.healTime";
	public const string REGEN_B_LAST_HEALTH_TICK = "RegenB.lastHealthTick";
	public const string ROTATION_A_BUFFER = "RotationA.buffer";
	public const string ROTATION_A_LAST_DELTA_PITCH = "RotationA.lastDeltaPitch";
	public const string ROTATION_A_LAST_DELTA_YAW = "RotationA.lastDeltaYaw";
	public const string ROTATION_A_LAST_PITCH = "RotationA.lastPitch";
	public const string ROTATION_A_LAST_YAW = "RotationA.lastYaw";
	public const string ROTATION_B_BUFFER = "RotationB.buffer";
	public const string ROTATION_B_LAST_DELTA_YAW = "RotationB.lastDeltaYaw";
	public const string ROTATION_B_LAST_PITCH = "RotationB.lastPitch";
	public const string ROTATION_B_LAST_YAW = "RotationB.lastYaw";
	public const string SCAFFOLD_B_OLD_PITCH = "ScaffoldB.oldPitch";
	public const string SCAFFOLD_E_BUFFER = "ScaffoldE.buffer";
	public const string SCAFFOLD_E_LAST_BLOCK = "ScaffoldE.lastBlock";
	public const string SCAFFOLD_E_LAST_PLACE_AT = "ScaffoldE.lastPlaceAt";
	public const string SCAFFOLD_F_BUFFER = "ScaffoldF.buffer";
	public const string SCAFFOLD_F_LAST_BLOCK = "ScaffoldF.lastBlock";
	public const string SCAFFOLD_F_LAST_PLACE_AT = "ScaffoldF.lastPlaceAt";
	public const string SCAFFOLD_F_LAST_PLAYER = "ScaffoldF.lastPlayer";
	public const string SPEED_A_LAST_DISTANCE_XZ = "SpeedA.lastDistanceXZ";
	public const string SPEED_B_MOVE_TIME = "SpeedB.moveTime";
	public const string SPAM_A_TICK = "SpamA.tick";
	public const string SPAM_A_VIOLATION = "SpamA.violation";
	public const string SPAM_B_LAST_MESSAGE = "SpamB.lastMessage";
	public const string STEP_LAST_Y = "Step.lastY";
	public const string TIMER_A_BALANCE = "TimerA.balance";
	public const string TIMER_A_LAST_TIME = "TimerA.lastTime";
	public const string TIMER_A_TICK = "TimerA.tick";
	public const string TIMER_C_DELAY_COUNTER = "TimerC.delayCounter";
	public const string TIMER_D_BUFFER = "TimerD.buffer";
	public const string TIMER_D_DRIFT = "TimerD.drift";
	public const string TIMER_D_LAST_AT_MS = "TimerD.lastAtMs";
	public const string TIMER_D_LAST_AUTH_TICK = "TimerD.lastAuthTick";
	public const string TIMER_D_SAMPLES = "TimerD.samples";
	public const string VELOCITY_A_BUFFER = "VelocityA.buffer";
	public const string VELOCITY_A_HIT_AT = "VelocityA.hitAt";
}
