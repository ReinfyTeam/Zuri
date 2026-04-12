<?php

/**
 * Vapm - A library support for PHP about Async, Promise, Coroutine, Thread, GreenThread
 *          and other non-blocking methods. The library also includes some Javascript packages
 *          such as Express. The method is based on Fibers & Generator & Processes, requires
 *          you to have php version from >= 8.1
 *
 * Copyright (C) 2023  VennDev
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

declare(strict_types=1);

namespace vennv\vapm;

use vennv\vapm\utils\Utils;
use function call_user_func;
use function microtime;

interface SampleMacroInterface
{

    public function isRepeat(): bool;

    public function getTimeOut(): float;

    public function getTimeStart(): float;

    public function getCallback(): callable;

    public function getId(): int;

    public function checkTimeOut(): bool;

    public function resetTimeOut(): void;

    public function isRunning(): bool;

    public function run(): void;

    public function stop(): void;

}

final class SampleMacro implements SampleMacroInterface
{

    private float $timeOut;

    private float $timeStart;

    private bool $isRepeat;

    /** @var callable $callback */
    private mixed $callback;

    private int $id;

    public function __construct(callable $callback, int $timeOut = 0, bool $isRepeat = false)
    {
        $this->id = MacroTask::generateId();
        $this->timeOut = Utils::milliSecsToSecs($timeOut);
        $this->isRepeat = $isRepeat;
        $this->timeStart = microtime(true);
        $this->callback = $callback;
    }

    public function isRepeat(): bool
    {
        return $this->isRepeat;
    }

    public function getTimeOut(): float
    {
        return $this->timeOut;
    }

    public function getTimeStart(): float
    {
        return $this->timeStart;
    }

    public function getCallback(): callable
    {
        return $this->callback;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function checkTimeOut(): bool
    {
        return microtime(true) - $this->timeStart >= $this->timeOut;
    }

    public function resetTimeOut(): void
    {
        $this->timeStart = microtime(true);
    }

    public function isRunning(): bool
    {
        return MacroTask::getTask($this->id) !== null;
    }

    public function run(): void
    {
        call_user_func($this->callback);
    }

    public function stop(): void
    {
        MacroTask::removeTask($this);
    }

}