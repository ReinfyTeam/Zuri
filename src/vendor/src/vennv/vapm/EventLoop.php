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

use SplQueue;
use Generator;
use Throwable;
use const PHP_INT_MAX;

interface EventLoopInterface
{

    public static function init(): void;

    public static function generateId(): int;

    public static function addQueue(Promise $promise): void;

    public static function getQueue(int $id): ?Promise;

    public static function addReturn(Promise $promise): void;

    public static function removeReturn(int $id): void;

    public static function isReturn(int $id): bool;

    public static function getReturn(int $id): ?Promise;

    /**
     * @return Generator
     */
    public static function getReturns(): Generator;

}

class EventLoop implements EventLoopInterface
{

    protected const LIMIT = 20; // 20 times run

    protected static int $nextId = 0;

    /**
     * @var SplQueue<Promise>
     */
    protected static SplQueue $queues;

    /**
     * @var array<int, Promise>
     */
    protected static array $returns = [];

    public static function init(): void
    {
        self::$queues ??= new SplQueue();
    }

    public static function generateId(): int
    {
        if (self::$nextId >= PHP_INT_MAX) self::$nextId = 0;
        return self::$nextId++;
    }

    public static function addQueue(Promise $promise): void
    {
        self::$queues->enqueue($promise);
    }

    public static function getQueue(int $id): ?Promise
    {
        while (!self::$queues->isEmpty()) {
            /**
             * @var Promise $promise
             */
            $promise = self::$queues->dequeue();
            if ($promise->getId() === $id) return $promise;
            self::$queues->enqueue($promise);
        }
        return null;
    }

    public static function addReturn(Promise $promise): void
    {
        if (!isset(self::$returns[$promise->getId()])) self::$returns[$promise->getId()] = $promise;
    }

    public static function isReturn(int $id): bool
    {
        return isset(self::$returns[$id]);
    }

    public static function removeReturn(int $id): void
    {
        if (self::isReturn($id)) unset(self::$returns[$id]);
    }

    public static function getReturn(int $id): ?Promise
    {
        return self::$returns[$id] ?? null;
    }

    /**
     * @return Generator
     */
    public static function getReturns(): Generator
    {
        foreach (self::$returns as $id => $promise) yield $id => $promise;
    }

    /**
     * @throws Throwable
     */
    private static function clearGarbage(): void
    {
        $gc = new GarbageCollection();
        foreach (self::getReturns() as $id => $promise) {
            if ($promise instanceof Promise && $promise->canDrop()) unset(self::$returns[$id]);
            $gc->collectWL();
        }
    }

    /**
     * @throws Throwable
     */
    protected static function run(): void
    {
        CoroutineGen::run();

        $i = 0;
        while (!self::$queues->isEmpty() && $i++ < self::LIMIT) {
            /** @var Promise $promise */
            $promise = self::$queues->dequeue();
            $fiber = $promise->getFiber();
            if ($fiber->isSuspended()) $fiber->resume();
            if (
                $fiber->isTerminated() && 
                ($promise->getStatus() !== StatusPromise::PENDING || $promise->isJustGetResult())
            ) {
                try {
                    $promise->isJustGetResult() && $promise->setResult($fiber->getReturn());
                } catch (Throwable $e) {
                    echo $e->getMessage();
                }
                MicroTask::addTask($promise->getId(), $promise);
            } else {
                self::$queues->enqueue($promise);
            }
        }

        MicroTask::isPrepare() && MicroTask::run();
        MacroTask::isPrepare() && MacroTask::run();

        self::clearGarbage();
    }

    /**
     * @throws Throwable
     */
    protected static function runSingle(): void
    {
        $gc = new GarbageCollection();
        while (
            !self::$queues->isEmpty() || 
            (CoroutineGen::getTaskQueue() !== null && !CoroutineGen::getTaskQueue()->isEmpty()) || 
            MicroTask::isPrepare() || MacroTask::isPrepare()
        ) {
            self::run();
            $gc->collectWL();
        }
    }

}
