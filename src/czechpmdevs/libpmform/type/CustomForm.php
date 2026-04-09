<?php

/**
 * Copyright (C) 2021 - 2023 CzechPMDevs
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace czechpmdevs\libpmform\type;

use czechpmdevs\libpmform\Form;
use function is_array;

final class CustomForm extends Form {
	public function __construct(string $title, bool $ignoreInvalidResponse = false) {
		parent::__construct(Form::FORM_TYPE_CUSTOM, $ignoreInvalidResponse);
		$this->data["title"] = $title;
		$this->data["content"] = [];
	}

	public function addInput(string $text, ?string $defaultText = null, ?string $placeholder = null) : void {
		/** @var array<string,mixed>[] $content */
		$content = $this->data["content"] ?? [];
		if (!is_array($content)) {
			$content = [];
		}
		$input = ["type" => "input", "text" => $text];
		if ($defaultText !== null) {
			$input["default"] = $defaultText;
		}
		if ($placeholder !== null) {
			$input["placeholder"] = $placeholder;
		}
		$content[] = $input;
		$this->data["content"] = $content;
	}

	public function addLabel(string $text) : void {
		/** @var array<string,mixed>[] $content */
		$content = $this->data["content"] ?? [];
		if (!is_array($content)) {
			$content = [];
		}
		$content[] = ["type" => "label", "text" => $text];
		$this->data["content"] = $content;
	}

	public function addToggle(string $text, ?bool $defaultValue = null) : void {
		/** @var array<string,mixed>[] $content */
		$content = $this->data["content"] ?? [];
		if (!is_array($content)) {
			$content = [];
		}
		$toggle = ["type" => "toggle", "text" => $text];
		if ($defaultValue !== null) {
			$toggle["default"] = $defaultValue;
		}
		$content[] = $toggle;
		$this->data["content"] = $content;
	}

	public function addSlider(string $text, int $min, int $max, int $step = -1, int $default = -1) : void {
		/** @var array<string,mixed>[] $content */
		$content = $this->data["content"] ?? [];
		if (!is_array($content)) {
			$content = [];
		}
		$slider = ["type" => "slider", "text" => $text, "min" => $min, "max" => $max];
		if ($step !== -1) {
			$slider["step"] = $step;
		}
		if ($default !== -1) {
			$slider["default"] = $default;
		}
		$content[] = $slider;
		$this->data["content"] = $content;
	}

	/** @param list<string> $steps */
	public function addStepSlider(string $text, array $steps, int $defaultIndex = -1) : void {
		/** @var array<string,mixed>[] $content */
		$content = $this->data["content"] ?? [];
		if (!is_array($content)) {
			$content = [];
		}
		$stepSlider = ["type" => "step_slider", "text" => $text, "steps" => $steps];
		if ($defaultIndex !== -1) {
			$stepSlider["default"] = $defaultIndex;
		}
		$content[] = $stepSlider;
		$this->data["content"] = $content;
	}

	/** @param list<string> $options */
	public function addDropdown(string $text, array $options, ?int $default = null) : void {
		/** @var array<string,mixed>[] $content */
		$content = $this->data["content"] ?? [];
		if (!is_array($content)) {
			$content = [];
		}
		$dropdown = ["type" => "dropdown", "text" => $text, "options" => $options];
		if ($default !== null) {
			$dropdown["default"] = $default;
		}
		$content[] = $dropdown;
		$this->data["content"] = $content;
	}
}
