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

final class DescriptorSpec
{

    public const BASIC = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w']
    ];

    public const IGNORE_STDIN = [
        0 => ['file', '/dev/null', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w']
    ];

    public const IGNORE_STDOUT = [
        0 => ['pipe', 'r'],
        1 => ['file', '/dev/null', 'w'],
        2 => ['pipe', 'w']
    ];

    public const IGNORE_STDERR = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['file', '/dev/null', 'w']
    ];

    public const IGNORE_STDOUT_AND_STDERR = [
        0 => ['pipe', 'r'],
        1 => ['file', '/dev/null', 'w'],
        2 => ['file', '/dev/null', 'w']
    ];

    public const IGNORE_STDIN_AND_STDERR = [
        0 => ['file', '/dev/null', 'r'],
        1 => ['pipe', 'w'],
        2 => ['file', '/dev/null', 'w']
    ];

    public const IGNORE_STDIN_AND_STDOUT = [
        0 => ['file', '/dev/null', 'r'],
        1 => ['file', '/dev/null', 'w'],
        2 => ['pipe', 'w']
    ];

}