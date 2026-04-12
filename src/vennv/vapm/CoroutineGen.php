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

use Closure;
use ReflectionException;
use SplQueue;
use Generator;
use Throwable;

interface CoroutineGenInterface
{

    /**
     * @return SplQueue|null
     *
     * This function returns the task queue.
     */
    public static function getTaskQueue(): ?SplQueue;

    /**
     * @param mixed ...$coroutines
     * @return void
     *
     * This is a blocking function that runs all the coroutines passed to it.
     */
    public static function runNonBlocking(mixed ...$coroutines): void;

    /**
     * @param mixed ...$coroutines
     * @return void
     *
     * This is a blocking function that runs all the coroutines passed to it.
     */
    public static function runBlocking(mixed ...$coroutines): void;

    /**
     * @param callable $callback
     * @param int $times
     * @return Closure
     *
     * This is a generator that runs a callback function a specified amount of times.
     */
    public static function repeat(callable $callback, int $times): Closure;

    /**
     * @param int $milliseconds
     * @return Generator
     *
     * This is a generator that yields for a specified amount of milliseconds.
     */
    public static function delay(int $milliseconds): Generator;

    /**
     * @return void
     *
     * This function runs the task queue.
     */
    public static function run(): void;

}

final class CoroutineGen implements CoroutineGenInterface
{

    private static ?SplQueue $taskQueue = null;

    public static function getTaskQueue(): ?SplQueue
    {
        return self::$taskQueue;
    }

    /**
     * @param mixed ...$coroutines
     * @return void
     * @throws Throwable
     */
    public static function runNonBlocking(mixed ...$coroutines): void
    {
        System::init();
        self::$taskQueue ??= new SplQueue();
        foreach ($coroutines as $coroutine) {
            $result = is_callable($coroutine) ? $coroutine() : $coroutine;
            $result instanceof Generator 
                ? self::schedule(new ChildCoroutine($result))
                : $result;
        }
        self::run();
    }

    /**
     * @param mixed ...$coroutines
     * @return void
     * @throws Throwable
     */
    public static function runBlocking(mixed ...$coroutines): void
    {
        self::runNonBlocking(...$coroutines);
        $gc = new GarbageCollection();
        while (!self::$taskQueue?->isEmpty()) {
            self::run();
            $gc->collectWL();
        }
    }

    /**
     * @param mixed ...$coroutines
     * @return Closure
     */
    private static function processCoroutine(mixed ...$coroutines): Closure
    {
        return function () use ($coroutines): void {
            foreach ($coroutines as $coroutine) {
                $result = is_callable($coroutine) ? $coroutine() : $coroutine;
                $result instanceof Generator 
                    ? self::schedule(new ChildCoroutine($result))
                    : $result;
            }
            self::run();
        };
    }

    public static function repeat(callable $callback, int $times): Closure
    {
        $gc = new GarbageCollection();
        for ($i = 0; $i < $times; $i++) {
            $result = $callback();
            if ($result instanceof Generator) {
                $callback = self::processCoroutine($result);
            }
            $gc->collectWL();
        }
        return fn() => null;
    }

    public static function delay(int $milliseconds): Generator
    {
        for ($i = 0; $i < GeneratorManager::calculateSeconds($milliseconds); $i++) yield;
    }

    private static function schedule(ChildCoroutine $childCoroutine): void
    {
        self::$taskQueue?->enqueue($childCoroutine);
    }

    /**
     * @throws ReflectionException
     * @throws Throwable
     */
    public static function run(): void
    {
        if (!self::$taskQueue?->isEmpty()) {
            $coroutine = self::$taskQueue?->dequeue();
            if ($coroutine instanceof ChildCoroutine && !$coroutine->isFinished()) {
                self::schedule($coroutine->run());
            }
        }
    }

}
