<?php

declare(strict_types=1);

namespace ReinfyTeam\Zuri\checks\network\editionfaker;

use pocketmine\event\Event;
use pocketmine\event\player\PlayerPreLoginEvent;
use ReinfyTeam\Zuri\checks\Check;
use function count_chars;
use function in_array;
use function preg_match;
use function strlen;
use function str_replace;
use function strtolower;
use function trim;

class DeviceSpoofID extends Check {
	private const array INVALID_DEVICE_IDS = [
		"",
		"0",
		"1",
		"unknown",
		"null",
		"none",
		"undefined",
		"ffffffffffffffff",
		"0000000000000000",
		"00000000-0000-0000-0000-000000000000",
		"ffffffff-ffff-ffff-ffff-ffffffffffff",
	];

	public function getName() : string {
		return "DeviceSpoofID";
	}

	public function getSubType() : string {
		return "A";
	}

	public function checkJustEvent(Event $event) : void {
		if (!$event instanceof PlayerPreLoginEvent) {
			return;
		}

		$extraData = $event->getPlayerInfo()->getExtraData();
		$deviceId = trim((string) ($extraData["DeviceId"] ?? ""));
		$normalized = strtolower(str_replace(["-", "_", ":", " "], "", $deviceId));
		$minLength = (int) $this->getConstant("min-length");
		$maxLength = (int) $this->getConstant("max-length");

		if ($deviceId === "" || strlen($deviceId) < $minLength || strlen($deviceId) > $maxLength) {
			$this->kick($event);
			return;
		}

		if (in_array(strtolower($deviceId), self::INVALID_DEVICE_IDS, true) || in_array($normalized, self::INVALID_DEVICE_IDS, true)) {
			$this->kick($event);
			return;
		}

		if ($normalized === "" || preg_match('/^(.)\1+$/', $normalized) === 1) {
			$this->kick($event);
			return;
		}

		if (strlen(count_chars($normalized, 3)) < (int) $this->getConstant("min-unique-chars")) {
			$this->kick($event);
			return;
		}

		if (preg_match('/^[0-9a-f]+$/', $normalized) !== 1 && preg_match('/^[a-z0-9]+$/', $normalized) !== 1) {
			$this->kick($event);
		}
	}

	private function kick(PlayerPreLoginEvent $event) : void {
		$this->warn($event->getPlayerInfo()->getUsername());
		$event->setKickFlag(0, self::getData(self::EDITIONFAKER_MESSAGE));
	}
}
