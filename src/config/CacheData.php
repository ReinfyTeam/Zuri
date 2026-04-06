<?php

declare(strict_types=1);

namespace ReinfyTeam\Zuri\config;

final class CacheData {
	private function __construct() {
	}

	public const AIRJUMP_LAST_UP_DISTANCE = "AirJump.lastUpDistance";
	public const ANTIVOID_LAST_Y = "AntiVoid.lastY";
	public const AUTOCLICK_A_AVG_DEVIATION = "AutoClickA.avgDeviation";
	public const AUTOCLICK_A_AVG_SPEED = "AutoClickA.avgSpeed";
	public const AUTOCLICK_A_TICKS = "AutoClickA.ticks";
	public const AUTOCLICK_B_LAST_CLICK = "AutoClickB.lastClick";
	public const AUTOCLICK_B_TICKS = "AutoClickB.ticks";
	public const AUTOCLICK_C_LAST_CLICK = "AutoClickC.lastClick";
	public const AUTOCLICK_C_TICKS = "AutoClickC.ticks";
	public const CHESTAURA_COUNT_TRANSACTION = "ChestAura.countTransaction";
	public const CHESTAURA_TIME_OPEN_CHEST = "ChestAura.timeOpenChest";
	public const CHESTSTEALER_LAST_TIME = "ChestStealer.lastTime";
	public const CHESTSTEALER_TICKS = "ChestStealer.ticks";
	public const FASTBOW_CURRENT_HS_INDEX = "FastBow.currentHsIndex";
	public const FASTBOW_HS_HIT_TIME = "FastBow.hsHitTime";
	public const FASTBOW_HS_TIME_LIST = "FastBow.hsTimeList";
	public const FASTBOW_HS_TIME_SUM = "FastBow.hsTimeSum";
	public const FASTBOW_SHOOT_FIRST_TICK = "FastBow.shootFirstTick";
	public const FASTDROP_LAST_TICK = "FastDrop.lastTick";
	public const FASTEAT_LAST_TICK = "FastEat.lastTick";
	public const FASTTHROW_LAST_USE = "FastThrow.lastUse";
	public const FLY_A_LAST_TIME = "FlyA.lastTime";
	public const FLY_A_LAST_Y_NO_GROUND = "FlyA.lastYNoGround";
	public const INSTABREAK_BREAK_TIMES = "InstaBreak.breakTimes";
	public const INVENTORYCLEANER_TICKS_TRANSACTION = "InventoryCleaner.ticksTransaction";
	public const INVENTORYCLEANER_TRANSACTION = "InventoryCleaner.transaction";
	public const INVALID_PACKETS_LAST_PACKET_TICK = "InvalidPackets.lastPacketTick";
	public const INVALID_PACKETS_TICK_PACKETS = "InvalidPackets.tickPackets";
	public const NOSLOW_A_BUFFER = "NoSlowA.buffer";
	public const REACH_E_BUFFER = "ReachE.buffer";
	public const REGEN_B_HEAL_COUNT = "RegenB.healCount";
	public const REGEN_B_HEAL_TIME = "RegenB.healTime";
	public const REGEN_B_LAST_HEALTH_TICK = "RegenB.lastHealthTick";
	public const ROTATION_A_BUFFER = "RotationA.buffer";
	public const ROTATION_A_LAST_DELTA_PITCH = "RotationA.lastDeltaPitch";
	public const ROTATION_A_LAST_DELTA_YAW = "RotationA.lastDeltaYaw";
	public const ROTATION_A_LAST_PITCH = "RotationA.lastPitch";
	public const ROTATION_A_LAST_YAW = "RotationA.lastYaw";
	public const ROTATION_B_BUFFER = "RotationB.buffer";
	public const ROTATION_B_LAST_DELTA_YAW = "RotationB.lastDeltaYaw";
	public const ROTATION_B_LAST_PITCH = "RotationB.lastPitch";
	public const ROTATION_B_LAST_YAW = "RotationB.lastYaw";
	public const SCAFFOLD_B_OLD_PITCH = "ScaffoldB.oldPitch";
	public const SCAFFOLD_E_BUFFER = "ScaffoldE.buffer";
	public const SCAFFOLD_E_LAST_BLOCK = "ScaffoldE.lastBlock";
	public const SCAFFOLD_E_LAST_PLACE_AT = "ScaffoldE.lastPlaceAt";
	public const SCAFFOLD_F_BUFFER = "ScaffoldF.buffer";
	public const SCAFFOLD_F_LAST_BLOCK = "ScaffoldF.lastBlock";
	public const SCAFFOLD_F_LAST_PLACE_AT = "ScaffoldF.lastPlaceAt";
	public const SCAFFOLD_F_LAST_PLAYER = "ScaffoldF.lastPlayer";
	public const SPEED_A_LAST_DISTANCE_XZ = "SpeedA.lastDistanceXZ";
	public const SPEED_B_MOVE_TIME = "SpeedB.moveTime";
	public const SPAM_A_TICK = "SpamA.tick";
	public const SPAM_A_VIOLATION = "SpamA.violation";
	public const SPAM_B_LAST_MESSAGE = "SpamB.lastMessage";
	public const STEP_LAST_Y = "Step.lastY";
	public const TIMER_A_BALANCE = "TimerA.balance";
	public const TIMER_A_LAST_TIME = "TimerA.lastTime";
	public const TIMER_A_TICK = "TimerA.tick";
	public const TIMER_C_DELAY_COUNTER = "TimerC.delayCounter";
	public const TIMER_D_BUFFER = "TimerD.buffer";
	public const TIMER_D_DRIFT = "TimerD.drift";
	public const TIMER_D_LAST_AT_MS = "TimerD.lastAtMs";
	public const TIMER_D_LAST_AUTH_TICK = "TimerD.lastAuthTick";
	public const TIMER_D_SAMPLES = "TimerD.samples";
	public const VELOCITY_A_BUFFER = "VelocityA.buffer";
	public const VELOCITY_A_HIT_AT = "VelocityA.hitAt";
}
