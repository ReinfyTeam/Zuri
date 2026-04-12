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

namespace vennv\vapm\ct;

use Generator;
use Closure;
use vennv\vapm\CoroutineGen;
use vennv\vapm\Deferred;
use vennv\vapm\Channel;
use vennv\vapm\AwaitGroup;
use vennv\vapm\Mutex;

/**
 * This file is used to create a coroutine with non-blocking mode
 */

/**
 * @param callable ...$callbacks
 * @return void
 * 
 * This function is used to create a coroutine with non-blocking mode
 */
function c(callable ...$callbacks): void
{
    CoroutineGen::runNonBlocking(...$callbacks);
}

/**
 * @param callable ...$callbacks
 * @return void
 * 
 * This function is used to create a coroutine with blocking mode
 */
function cBlock(callable ...$callbacks): void
{
    CoroutineGen::runBlocking(...$callbacks);
}

/**
 * @param int $milliseconds
 * @return Generator
 * 
 * This function is used to delay the execution of a coroutine
 */
function cDelay(int $milliseconds): Generator
{
    return CoroutineGen::delay($milliseconds);
}

/**
 * @param callable $callback
 * @param int $times
 * @return Closure
 * 
 * This function is used to repeat the execution of a coroutine
 */
function cRepeat(callable $callback, int $times): Closure
{
    return CoroutineGen::repeat($callback, $times);
}

/**
 * @return Channel
 * 
 * This function is used to create a channel
 */
function channel(): Channel
{
    return new Channel();
}

/**
 * @return AwaitGroup
 * 
 * This function is used to create a await group
 */
function awaitGroup(): AwaitGroup
{
    return new AwaitGroup();
}

/**
 * @return Mutex
 * 
 * This function is used to create a mutex
 */
function mutex(): Mutex
{
    return new Mutex();
}

/**
 * @param callable $callback
 * @return Deferred
 * 
 * This function is used to create a deferred
 */
function deferred(callable $callback): Deferred
{
    return new Deferred($callback);
}