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

declare(strict_types = 1);

namespace jojoe77777\FormAPI;

use pocketmine\form\FormValidationException;
use function array_fill;
use function count;
use function gettype;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

class CustomForm extends Form {
	private array $labelMap = [];
	private array $validationMethods = [];

	public function __construct(?callable $callable) {
		parent::__construct($callable);
		$this->data["type"] = "custom_form";
		$this->data["title"] = "";
		$this->data["content"] = [];
	}

	public function processData(&$data) : void {
		if ($data !== null && !is_array($data)) {
			throw new FormValidationException("Expected an array response, got " . gettype($data));
		}
		if (is_array($data)) {
			$actual = count($data);
			$expected = count($this->validationMethods);
			if ($actual > $expected) {
				throw new FormValidationException("Too many result elements, expected $expected, got $actual");
			} elseif ($actual < $expected) {
				// In 1.21.70, the client doesn't send nulls for labels, so we need to polyfill them here to
				// maintain the old behaviour
				$noLabelsIndexMapping = [];
				foreach ($this->data["content"] as $index => ["type" => $r]) {
					if ($r !== "label") {
						$noLabelsIndexMapping[] = $index;
					}
				}
				$expectedWithoutLabels = count($noLabelsIndexMapping);
				if ($actual !== $expectedWithoutLabels) {
					throw new FormValidationException("Wrong number of result elements, expected either " .
						$expected .
						" (with label values, <1.21.70) or " .
						$expectedWithoutLabels .
						" (without label values, >=1.21.70), got " .
						$actual
					);
				}

				//polyfill the missing nulls
				$mappedData = array_fill(0, $expected, null);
				foreach ($data as $givenIndex => $value) {
					$internalIndex = $noLabelsIndexMapping[$givenIndex] ?? null;
					if ($internalIndex === null) {
						throw new FormValidationException("Can't map given offset $givenIndex to an internal element offset (while correcting for labels)");
					}
					// set the appropriate values according to the given index
					// this could (?) still leave unexpected nulls, but the validation below will catch that
					$mappedData[$internalIndex] = $value;
				}
				if (count($mappedData) !== $expected) {
					throw new FormValidationException("This should always match");
				}
				$data = $mappedData;
			}
			$new = [];
			foreach ($data as $i => $v) {
				$validationMethod = $this->validationMethods[$i] ?? null;
				if ($validationMethod === null) {
					throw new FormValidationException("Invalid element " . $i);
				}
				if (!$validationMethod($v)) {
					throw new FormValidationException("Invalid type given for element " . $this->labelMap[$i]);
				}
				$new[$this->labelMap[$i]] = $v;
			}
			$data = $new;
		}
	}

	/**
	 * @return $this
	 */
	public function setTitle(string $title) : self {
		$this->data["title"] = $title;
		return $this;
	}

	public function getTitle() : string {
		return $this->data["title"];
	}

	/**
	 * @return $this
	 */
	public function addLabel(string $text, ?string $label = null) : self {
		$this->addContent(["type" => "label", "text" => $text]);
		$this->labelMap[] = $label ?? count($this->labelMap);
		$this->validationMethods[] = static fn($v) => $v === null;
		return $this;
	}

	/**
	 * @return $this
	 */
	public function addToggle(string $text, bool $default = null, ?string $label = null) : self {
		$content = ["type" => "toggle", "text" => $text];
		if ($default !== null) {
			$content["default"] = $default;
		}
		$this->addContent($content);
		$this->labelMap[] = $label ?? count($this->labelMap);
		$this->validationMethods[] = static fn($v) => is_bool($v);
		return $this;
	}

	/**
	 * @return $this
	 */
	public function addSlider(string $text, int $min, int $max, int $step = -1, int $default = -1, ?string $label = null) : self {
		$content = ["type" => "slider", "text" => $text, "min" => $min, "max" => $max];
		if ($step !== -1) {
			$content["step"] = $step;
		}
		if ($default !== -1) {
			$content["default"] = $default;
		}
		$this->addContent($content);
		$this->labelMap[] = $label ?? count($this->labelMap);
		$this->validationMethods[] = static fn($v) => (is_float($v) || is_int($v)) && $v >= $min && $v <= $max;
		return $this;
	}

	/**
	 * @return $this
	 */
	public function addStepSlider(string $text, array $steps, int $defaultIndex = -1, ?string $label = null) : self {
		$content = ["type" => "step_slider", "text" => $text, "steps" => $steps];
		if ($defaultIndex !== -1) {
			$content["default"] = $defaultIndex;
		}
		$this->addContent($content);
		$this->labelMap[] = $label ?? count($this->labelMap);
		$this->validationMethods[] = static fn($v) => is_int($v) && isset($steps[$v]);
		return $this;
	}

	/**
	 * @return $this
	 */
	public function addDropdown(string $text, array $options, int $default = null, ?string $label = null) : self {
		$this->addContent(["type" => "dropdown", "text" => $text, "options" => $options, "default" => $default]);
		$this->labelMap[] = $label ?? count($this->labelMap);
		$this->validationMethods[] = static fn($v) => is_int($v) && isset($options[$v]);
		return $this;
	}

	/**
	 * @return $this
	 */
	public function addInput(string $text, string $placeholder = "", string $default = null, ?string $label = null) : self {
		$this->addContent(["type" => "input", "text" => $text, "placeholder" => $placeholder, "default" => $default]);
		$this->labelMap[] = $label ?? count($this->labelMap);
		$this->validationMethods[] = static fn($v) => is_string($v);
		return $this;
	}

	/**
	 * @return $this
	 */
	private function addContent(array $content) : self {
		$this->data["content"][] = $content;
		return $this;
	}
}
