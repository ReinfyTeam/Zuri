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

namespace ReinfyTeam\Zuri\checks\modules\aimassist;

use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use ReinfyTeam\Zuri\checks\Check;
use ReinfyTeam\Zuri\player\PlayerAPI;
use ReinfyTeam\Zuri\utils\discord\DiscordWebhookException;
use function abs;
use function fmod;
use function is_numeric;
use function round;

/**
 * Detects quantized aim movement patterns processed asynchronously.
 */
class AimAssistB extends Check {
	/**
	 * Gets the check name.
	 */
	public function getName() : string {
		return "AimAssist";
	}

	/**
	 * Gets the check subtype identifier.
	 */
	public function getSubType() : string {
		return "B";
	}

	/**
	 * Processes input packets and dispatches async AimAssistB checks.
	 *
	 * @param DataPacket $packet Incoming network packet.
	 * @param PlayerAPI $playerAPI Player state wrapper.
	 *
	 * @throws DiscordWebhookException
	 */
	public function check(DataPacket $packet, PlayerAPI $playerAPI) : void {
		if ($packet instanceof PlayerAuthInputPacket) {
			$player = $playerAPI->getPlayer();
			if (
				!$player->isSurvival() ||
				$playerAPI->getAttackTicks() > 20 ||
				$playerAPI->getTeleportTicks() < 100 ||
				$playerAPI->getTeleportCommandTicks() < 100 ||
				$playerAPI->isRecentlyCancelledEvent() ||
				$playerAPI->getPing() > self::getData(self::PING_LAGGING)
			) {
				return;
			}

			$nLocation = $playerAPI->getNLocation();
			if (!empty($nLocation)) {
				$this->dispatchAsyncCheck($playerAPI->getPlayer()->getName(), [
					"checkName" => $this->getName(),
					"checkSubType" => $this->getSubType(),
					"toYaw" => $nLocation["to"]->getYaw(),
					"fromYaw" => $nLocation["from"]->getYaw(),
					"toPitch" => $nLocation["to"]->getPitch(),
					"fromPitch" => $nLocation["from"]->getPitch(),
				]);
			}
		}
	}

	/**
	 * Evaluates async payload data for AimAssistB violations.
	 *
	 * @param array<string,mixed> $payload Serialized check context.
	 *
	 * @return array<string,mixed>
	 */
	public static function evaluateAsync(array $payload) : array {
		if (($payload["checkName"] ?? null) !== "AimAssist" || ($payload["checkSubType"] ?? null) !== "B") {
			return [];
		}

		$toYawRaw = $payload["toYaw"] ?? 0.0;
		$fromYawRaw = $payload["fromYaw"] ?? 0.0;
		$toYaw = is_numeric($toYawRaw) ? (float) $toYawRaw : 0.0;
		$fromYaw = is_numeric($fromYawRaw) ? (float) $fromYawRaw : 0.0;
		$yawDiff = self::angleDiff($fromYaw, $toYaw);
		if ($yawDiff >= 1.0 && self::isQuantizedStep($yawDiff)) {
			if (self::isApproxMultiple($yawDiff, 1.0) || self::isApproxMultiple($yawDiff, 10.0) || self::isApproxMultiple($yawDiff, 30.0)) {
				return ["failed" => true, "debug" => "toYaw={$toYaw}, fromYaw={$fromYaw}, yawDiff={$yawDiff}"];
			}
		}

		$toPitchRaw = $payload["toPitch"] ?? 0.0;
		$fromPitchRaw = $payload["fromPitch"] ?? 0.0;
		$toPitch = is_numeric($toPitchRaw) ? (float) $toPitchRaw : 0.0;
		$fromPitch = is_numeric($fromPitchRaw) ? (float) $fromPitchRaw : 0.0;
		$pitchDiff = abs($toPitch - $fromPitch);
		if ($pitchDiff >= 1.0 && self::isQuantizedStep($pitchDiff)) {
			if (self::isApproxMultiple($pitchDiff, 1.0) || self::isApproxMultiple($pitchDiff, 10.0) || self::isApproxMultiple($pitchDiff, 30.0)) {
				return ["failed" => true, "debug" => "toYaw={$toYaw}, fromYaw={$fromYaw}, yawDiff={$yawDiff}, toPitch={$toPitch}, fromPitch={$fromPitch}, pitchDiff={$pitchDiff}"];
			}
		}

		return [];
	}

	/**
	 * Checks whether a value is approximately a multiple of a step.
	 *
	 * @param float $value Value to evaluate.
	 * @param float $step Step size.
	 */
	private static function isApproxMultiple(float $value, float $step) : bool {
		return abs($value - (round($value / $step) * $step)) <= 0.0001;
	}

	/**
	 * Checks whether a value follows the expected quantized step size.
	 *
	 * @param float $value Value to evaluate.
	 */
	private static function isQuantizedStep(float $value) : bool {
		return self::isApproxMultiple($value, 0.1);
	}

	private static function angleDiff(float $from, float $to) : float {
		return abs(self::wrapAngleTo180($to - $from));
	}

	private static function wrapAngleTo180(float $angle) : float {
		$wrapped = fmod($angle, 360.0);
		if ($wrapped >= 180.0) {
			$wrapped -= 360.0;
		}
		if ($wrapped < -180.0) {
			$wrapped += 360.0;
		}

		return $wrapped;
	}
}
