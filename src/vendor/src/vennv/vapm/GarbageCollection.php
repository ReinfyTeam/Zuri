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

use function gc_collect_cycles;

interface GarbageCollectionInterface
{
    /**
     * Set the limit of the loop to collect garbage
     * 
     * @param int $limit
     * @return void
     */
    public function setLimitLoop(int $limit): void;

    /**
     * Collect garbage with limit
     * 
     * @return void
     */
    public function collectWL(): void;

    /**
     * Collect garbage
     * 
     * @return void
     */
    public static function collect(): void;

}

final class GarbageCollection implements GarbageCollectionInterface
{


    private int $countLoop = 0;

    /**
     * @param int $limitLoop The limit of the loop to collect garbage
     */
    public function __construct(private int $limitLoop = 1000)
    {
        // TODO: Implement __construct() method.
    }

    public function setLimitLoop(int $limit): void
    {
        $this->limitLoop = $limit;
    }

    public function collectWL(): void
    {
        if ($this->countLoop >= $this->limitLoop) {
            gc_collect_cycles();
            $this->countLoop = 0;
        } else {
            ++$this->countLoop;
        }
    }

    public static function collect(): void
    {
        gc_collect_cycles();
    }

}
