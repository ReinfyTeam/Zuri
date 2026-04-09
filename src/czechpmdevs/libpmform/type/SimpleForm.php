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

final class SimpleForm extends Form {
	public function __construct(string $title, string $content, bool $ignoreInvalidResponse = false) {
		parent::__construct(Form::FORM_TYPE_SIMPLE, $ignoreInvalidResponse);
		$this->data["title"] = $title;
		$this->data["content"] = $content;
		$this->data["buttons"] = [];
	}

	public function addButton(string $text) : void {
		/** @var array<int,array<string,string>> $buttons */
		$buttons = $this->data["buttons"] ?? [];
		if (!is_array($buttons)) {
			$buttons = [];
		}
		$buttons[] = ["text" => $text];
		$this->data["buttons"] = $buttons;
	}
}
