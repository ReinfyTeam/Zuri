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

use Fiber;
use Throwable;
use function is_null;

interface FiberManagerInterface
{

    /**
     * @throws Throwable
     *
     * This is a function that waits for the current fiber to finish.
     */
    public static function wait(): void;

}

final class FiberManager implements FiberManagerInterface
{

    /**
     * @throws Throwable
     */
    public static function wait(): void
    {
        if (!is_null(Fiber::getCurrent())) Fiber::suspend();
    }

}