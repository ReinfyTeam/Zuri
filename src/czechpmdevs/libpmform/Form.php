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

namespace czechpmdevs\libpmform;

use Closure;
use czechpmdevs\libpmform\response\FormResponse;
use pocketmine\player\Player;

abstract class Form implements \pocketmine\form\Form {
	public const FORM_TYPE_SIMPLE = "form";
	public const FORM_TYPE_MODAL = "modal";
	public const FORM_TYPE_CUSTOM = "custom_form";

	/** @var mixed[] */
	protected array $data = [];

	/** @var Closure(Player $player, FormResponse $response): void */
	protected Closure $callback;
	protected bool $ignoreInvalidResponse;

	public function __construct(string $formType, bool $ignoreInvalidResponse = false) {
		$this->data["type"] = $formType;
		$this->ignoreInvalidResponse = $ignoreInvalidResponse;
	}

	public function ignoreInvalidResponse(bool $ignoreInvalidResponse = true): void {
		$this->ignoreInvalidResponse = $ignoreInvalidResponse;
	}

	/**
	 * @param Closure(Player $player, FormResponse $response): void $callback
	 */
	public function setCallback(Closure $callback): void {
		$this->callback = $callback;
	}

	final public function handleResponse(Player $player, $data): void {
		$response = new FormResponse($data);
		if ($this->ignoreInvalidResponse && !$response->isValid()) {
			return;
		}
		if (!isset($this->callback)) {
			return;
		}
		$callback = $this->callback;
		$callback($player, $response);
	}

	public function jsonSerialize(): mixed {
		return $this->data;
	}
}
