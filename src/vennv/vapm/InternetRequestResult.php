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

interface InternetRequestResultInterface
{

    /**
     * @return string[][]
     */
    public function getHeaders(): array;

    public function getBody(): string;

    public function getCode(): int;

}

final class InternetRequestResult implements InternetRequestResultInterface
{

    /**
     * @var string[][] $headers
     */
    private array $headers;

    private string $body;

    private int $code;

    /**
     * @param string[][] $headers
     * @param string $body
     * @param int $code
     */
    public function __construct(array $headers, string $body, int $code)
    {
        $this->headers = $headers;
        $this->body = $body;
        $this->code = $code;
    }

    /**
     * @return string[][]
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getCode(): int
    {
        return $this->code;
    }

}
