<?php

declare(strict_types=1);

namespace ReinfyTeam\Zuri\events\api;

use pocketmine\event\CancellableTrait;
use pocketmine\event\Event;

class CheckStateChangeEvent extends Event {
	use CancellableTrait;

	public function __construct(
		private string $checkName,
		private ?string $subType,
		private bool $enabled
	) {
	}

	public function getCheckName() : string {
		return $this->checkName;
	}

	public function getSubType() : ?string {
		return $this->subType;
	}

	public function isEnabled() : bool {
		return $this->enabled;
	}

	public function setEnabled(bool $enabled) : void {
		$this->enabled = $enabled;
	}
}