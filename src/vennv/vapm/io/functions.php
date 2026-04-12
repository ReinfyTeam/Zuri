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

namespace vennv\vapm\io;

use vennv\vapm\Async;
use vennv\vapm\Promise;
use vennv\vapm\System;

/**
 * This functions is used to handle the IO operations
 */

/**
 * @param callable $callback
 * @return Async
 * 
 * This function is used to run asynchronous callbacks
 */
function async(callable $callback): Async
{
    return new Async($callback);
}

/**
 * @param mixed $await
 * @return mixed
 * 
 * This function is used to wait for a callback to be executed
 */
function await(mixed $await): mixed
{
    return Async::await($await);
}

/**
 * @param int $milliseconds
 * @return Promise
 * 
 * This function is used to delay the execution of a callback
 */
function delay(int $milliseconds): Promise
{
    return new Promise(function($resolve) use ($milliseconds) {
        System::setTimeout(function() use ($resolve) {
            $resolve();
        }, $milliseconds);
    });
}

/**
 * @param callable $callback
 * @param int $milliseconds
 * @return void
 * 
 * This function is used to set a timeout for a callback
 */
function setTimeout(callable $callback, int $milliseconds): void
{
    System::setTimeout($callback, $milliseconds);
}

/**
 * @param callable $callback
 * @param int $milliseconds
 * @return void
 * 
 * This function is used to set an interval for a callback
 */
function setInterval(callable $callback, int $milliseconds): void
{
    System::setInterval($callback, $milliseconds);
}