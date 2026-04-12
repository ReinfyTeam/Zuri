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
use function fclose;
use function fgets;
use function fopen;
use function fwrite;
use function is_array;
use function touch;
use function unlink;
use function file_exists;
use function call_user_func;
use function stream_set_blocking;

interface StreamInterface
{

    /**
     * @return Promise
     * @throws Throwable
     *
     * Use this to read a file or url.
     */
    public static function read(string $path): Promise;

    /**
     * @return Promise
     * @throws Throwable
     *
     * Use this to write to a file.
     */
    public static function write(string $path, string $data): Promise;

    /**
     * @return Promise
     * @throws Throwable
     *
     * Use this to append to a file.
     */
    public static function append(string $path, string $data): Promise;

    /**
     * @return Promise
     * @throws Throwable
     *
     * Use this to delete a file.
     */
    public static function delete(string $path): Promise;

    /**
     * @return Promise
     * @throws Throwable
     *
     * Use this to create a file.
     */
    public static function create(string $path): Promise;

    /**
     * @return Promise
     * @throws Throwable
     *
     * Use this to create a file or overwrite a file.
     */
    public static function overWrite(string $path, string $data): Promise;

    /**
     * @param array<int|string, mixed> $array
     * @return Promise
     * @throws Throwable
     *
     * Use this to flatten an array.
     */
    public static function flattenArray(array $array): Promise;

}

final class Stream implements StreamInterface
{

    /**
     * @throws Throwable
     */
    public static function read(string $path): Promise
    {
        return new Promise(function ($resolve, $reject) use ($path): void {
            $lines = '';
            $handle = fopen($path, 'r');

            if ($handle === false) {
                $reject(Error::UNABLE_TO_OPEN_FILE);
            } else {
                stream_set_blocking($handle, false);

                while (($line = fgets($handle)) !== false) {
                    $lines .= $line;
                    FiberManager::wait();
                }

                fclose($handle);
            }

            $resolve($lines);
        });
    }

    /**
     * @throws Throwable
     */
    public static function write(string $path, string $data): Promise
    {
        return new Promise(function ($resolve, $reject) use ($path, $data): void {
            System::setTimeout(function () use ($resolve, $reject, $path, $data): void {
                $callback = function ($path, $data) use ($reject): void {
                    $handle = fopen($path, 'w');

                    if ($handle === false) {
                        $reject(Error::UNABLE_TO_OPEN_FILE);
                    } else {
                        stream_set_blocking($handle, false);
                        fwrite($handle, $data);
                        fclose($handle);
                    }
                };

                call_user_func($callback, $path, $data);
                $resolve();
            }, 0);
        });
    }

    /**
     * @throws Throwable
     */
    public static function append(string $path, string $data): Promise
    {
        return new Promise(function ($resolve, $reject) use ($path, $data): void {
            System::setTimeout(function () use ($resolve, $reject, $path, $data): void {
                $callback = function ($path, $data) use ($reject): void {
                    $handle = fopen($path, 'a');

                    if ($handle === false) {
                        $reject(Error::UNABLE_TO_OPEN_FILE);
                    } else {
                        stream_set_blocking($handle, false);
                        fwrite($handle, $data);
                        fclose($handle);
                    }
                };

                call_user_func($callback, $path, $data);
                $resolve();
            }, 0);
        });
    }

    /**
     * @throws Throwable
     */
    public static function delete(string $path): Promise
    {
        return new Promise(function ($resolve, $reject) use ($path): void {
            System::setTimeout(function () use ($resolve, $reject, $path): void {
                $callback = function ($path) use ($reject): void {
                    file_exists($path) ? unlink($path) : $reject(Error::FILE_DOES_NOT_EXIST);
                };
                call_user_func($callback, $path);
                $resolve();
            }, 0);
        });
    }

    /**
     * @throws Throwable
     */
    public static function create(string $path): Promise
    {
        return new Promise(function ($resolve, $reject) use ($path): void {
            System::setTimeout(function () use ($resolve, $reject, $path): void {
                $callback = function ($path) use ($reject): void {
                    !file_exists($path) ? touch($path) : $reject(Error::FILE_ALREADY_EXISTS);
                };
                call_user_func($callback, $path);
                $resolve();
            }, 0);
        });
    }

    /**
     * @throws Throwable
     */
    public static function overWrite(string $path, string $data): Promise
    {
        return new Promise(function ($resolve, $reject) use ($path, $data): void {
            System::setTimeout(function () use ($resolve, $reject, $path, $data): void {
                $callback = function ($path, $data) use ($reject): void {
                    $handle = fopen($path, 'w+');
                    if ($handle === false) {
                        $reject(Error::UNABLE_TO_OPEN_FILE);
                    } else {
                        stream_set_blocking($handle, false);
                        fwrite($handle, $data);
                        fclose($handle);
                    }
                };

                call_user_func($callback, $path, $data);
                $resolve();
            }, 0);
        });
    }

    /**
     * @param array<int|string, mixed> $array
     * @throws Throwable
     */
    public static function flattenArray(array $array): Promise
    {
        return new Promise(function ($resolve, $reject) use ($array) {
            $result = [];
            $stack = [$array];

            while (!empty($stack)) {
                $element = array_shift($stack);
                if ($element === null) {
                    $reject(Error::INVALID_ARRAY);
                    break;
                }

                foreach ($element as $value) {
                    is_array($value) ? array_unshift($stack, $value) : $result[] = $value;
                    FiberManager::wait();
                }
                FiberManager::wait();
            }
            
            $resolve($result);
        });
    }

}