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

use Generator;
use const PHP_INT_MAX;

final class MacroTask
{

    private static int $nextId = 0;

    /**
     * @var array<int, SampleMacro>
     */
    private static array $tasks = [];

    public static function generateId(): int
    {
        if (self::$nextId >= PHP_INT_MAX) self::$nextId = 0;
        return self::$nextId++;
    }

    public static function addTask(SampleMacro $sampleMacro): void
    {
        self::$tasks[$sampleMacro->getId()] = $sampleMacro;
    }

    public static function removeTask(SampleMacro $sampleMacro): void
    {
        $id = $sampleMacro->getId();
        if (isset(self::$tasks[$id])) unset(self::$tasks[$id]);
    }

    public static function getTask(int $id): ?SampleMacro
    {
        return self::$tasks[$id] ?? null;
    }

    /**
     * @return Generator
     */
    public static function getTasks(): Generator
    {
        foreach (self::$tasks as $id => $task) yield $id => $task;
    }

    public static function isPrepare(): bool
    {
        return !empty(self::$tasks);
    }

    public static function run(): void
    {
        $gc = new GarbageCollection();
        foreach (self::getTasks() as $task) {
            /** @var SampleMacro $task */
            if ($task->checkTimeOut()) {
                $task->run();
                !$task->isRepeat() ? self::removeTask($task) : $task->resetTimeOut();
            }
            $gc->collectWL();
        }
    }

}