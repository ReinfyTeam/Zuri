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

/**
 * @author  VennDev <venn.dev@gmail.com>
 * @package vennv\vapm
 * 
 * This interface is used to create a await group object that can be used to wait for a group of coroutines to complete.
 */
interface AwaitGroupInterface
{

    /**
     * @param int $count
     * @return void
     *
     * This function is used to add the count to the group
     */
    public function add(int $count): void;

    /**
     * @return Generator
     *
     * This function is used to decrement the count
     */
    public function done(): Generator;

    /**
     * @return bool
     *
     * This function is used to check if the count is zero
     */
    public function isDone(): bool;

    /**
     * @return int
     *
     * This function is used to get the count
     */
    public function getCount(): int;

    /**
     * @return void
     *
     * This function is used to reset the count
     */
    public function reset(): void;

    /**
     * @return void
     *
     * This function is used to wait for the count to be zero
     */
    public function wait(): void;

}

final class AwaitGroup implements AwaitGroupInterface
{

    private int $count = 0;

    public function add(int $count): void
    {
        $this->count += $count;
    }

    public function done(): Generator
    {
        $this->count--;
        yield;
    }

    public function isDone(): bool
    {
        return $this->count === 0;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function reset(): void
    {
        $this->count = 0;
    }

    public function wait(): void
    {
        $gc = new GarbageCollection();
        while ($this->count > 0) {
            CoroutineGen::run();
            $gc->collectWL();
        }
    }

    public function __destruct()
    {
        $this->reset();
    }

}