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

use Throwable;
use Generator;
use function microtime;

final class MicroTask
{

    /**
     * @var array<int, Promise>
     */
    private static array $tasks = [];

    public static function addTask(int $id, Promise $promise): void
    {
        self::$tasks[$id] = $promise;
    }

    public static function removeTask(int $id): void
    {
        unset(self::$tasks[$id]);
    }

    public static function getTask(int $id): ?Promise
    {
        return self::$tasks[$id] ?? null;
    }

    /**
     * @return Generator
     */
    public static function getTasks(): Generator
    {
        foreach (self::$tasks as $id => $promise) yield $id => $promise;
    }

    public static function isPrepare(): bool
    {
        return !empty(self::$tasks);
    }

    /**
     * @throws Throwable
     */
    public static function run(): void
    {
        $gc = new GarbageCollection();
        foreach (self::getTasks() as $id => $promise) {
            /** @var Promise $promise */
            $promise->useCallbacks();
            $promise->setTimeEnd(microtime(true));
            EventLoop::addReturn($promise);
            /** @var int $id */
            self::removeTask($id);
            $gc->collectWL();
        }
    }

}