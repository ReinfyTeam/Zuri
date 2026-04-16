<?php

namespace ReinfyTeam\Zuri\player;

use ReinfyTeam\Zuri\check\Check;

class Violation {
	
	public array $preViolation = [];
	public array $violation = [];
	
	public function getPreViolations(Check $check) : int {
		return $this->preViolation[$check->getName()][$check->getSubType()] ??= 0;
	} 
	
	public function addPreViolation(Check $check, int|float $amount = 1) : void {
		if (isset($this->preViolation[$check->getName()][$check->getSubType()])) {
			foreach ($this->preViolation[$check->getName()][$check->getSubType()] as $index => $time) {
				if (abs($time - microtime(true)) * 20 > 40) {
					unset($this->preViolation[$check->getName()][$check->getSubType()][$index]);
				}
			}
		}

		$this->preViolation[$check->getName()][$check->getSubType()][] = microtime(true);
	}
	
	public function resetPreViolation(Check $check) : void {
		if (isset($this->preViolation[$check->getName()][$check->getSubType()])) {
			unset($this->preViolation[$check->getName()][$check->getSubType()]);
		}
	}
	
	public function getViolations(Check $check) : int {
		return $this->violation[$check->getName()][$check->getSubType()] ??= 0;
	} 
	
	public function addViolation(Check $check, int|float $amount = 1) : void {
		if (isset($this->violation[$check->getName()][$check->getSubType()])) {
			foreach ($this->violation[$check->getName()][$check->getSubType()] as $index => $time) {
				if (abs($time - microtime(true)) * 20 > 40) {
					unset($this->violation[$check->getName()][$check->getSubType()][$index]);
				}
			}
		}

		$this->violation[$check->getName()][$check->getSubType()][] = microtime(true);
	}
	
	public function resetViolation(Check $check) : void {
		if (isset($this->violation[$check->getName()][$check->getSubType()])) {
			unset($this->violation[$check->getName()][$check->getSubType()]);
		}
	}
}