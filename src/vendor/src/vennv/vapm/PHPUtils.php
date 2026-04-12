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

interface PHPUtilsInterface
{

    /**
     * @param array<int|float|string|object> $array
     * @param callable $callback
     * @return Async
     *
     * @phpstan-param array<int|float|string|object> $array
     * @throws Throwable
     * 
     * This function is used to iterate over an array and call a callback function for each element.
     */
    public static function forEach(array $array, callable $callback): Async;

    /**
     * @param array<int|float|string|object> $array
     * @param callable $callback
     * @return Async
     *
     * @phpstan-param array<int|float|string|object> $array
     * @throws Throwable
     * 
     * This function is used to map over an array and apply a callback function to each element.
     */
    public static function arrayMap(array $array, callable $callback): Async;

    /**
     * @param array<int|float|string|object> $array
     * @param callable $callback
     * @return Async
     *
     * @phpstan-param array<int|float|string|object> $array
     * @throws Throwable
     */
    public static function arrayFilter(array $array, callable $callback): Async;

    /**
     * @param array<int|float|string|object> $array
     * @param callable $callback
     * @param mixed $initialValue
     * @return Async
     *
     * @throws Throwable
     * 
     * This function is used to reduce an array to a single value by applying a callback function to each element.
     */
    public static function arrayReduce(array $array, callable $callback, mixed $initialValue): Async;

    /**
     * @param array<int|float|string|object> $array
     * @param string $className
     * @return Async
     *
     * @throws Throwable
     * 
     * This function is used to check if all elements in an array are instances of a specific class.
     */
    public static function instanceOfAll(array $array, string $className): Async;

    /**
     * @param array<int|float|string|object> $array
     * @param string $className
     * @return Async
     *
     * @throws Throwable
     * 
     * This function is used to check if any element in an array is an instance of a specific class.
     */
    public static function instanceOfAny(array $array, string $className): Async;

}

final class PHPUtils implements PHPUtilsInterface
{

    public static function forEach(array $array, callable $callback): Async
    {
        return new Async(function () use ($array, $callback) {
            foreach ($array as $key => $value) {
                $callback($key, $value);
                FiberManager::wait();
            }
        });
    }

    public static function arrayMap(array $array, callable $callback): Async
    {
        return new Async(function () use ($array, $callback) {
            $result = [];
            foreach ($array as $key => $value) {
                $result[$key] = $callback($key, $value);
                FiberManager::wait();
            }
            return $result;
        });
    }

    public static function arrayFilter(array $array, callable $callback): Async
    {
        return new Async(function () use ($array, $callback) {
            $result = [];
            foreach ($array as $key => $value) {
                if ($callback($key, $value)) {
                    $result[$key] = $value;
                }
                FiberManager::wait();
            }
            return $result;
        });
    }

    public static function arrayReduce(array $array, callable $callback, mixed $initialValue): Async
    {
        return new Async(function () use ($array, $callback, $initialValue) {
            $accumulator = $initialValue;
            foreach ($array as $key => $value) {
                $accumulator = $callback($accumulator, $value, $key);
                FiberManager::wait();
            }
            return $accumulator;
        });
    }

    public static function instanceOfAll(array $array, string $className): Async
    {
        return new Async(function () use ($array, $className) {
            foreach ($array as $value) {
                if (!($value instanceof $className)) return false;
                FiberManager::wait();
            }
            return true;
        });
    }

    public static function instanceOfAny(array $array, string $className): Async
    {
        return new Async(function () use ($array, $className) {
            foreach ($array as $value) {
                if ($value instanceof $className) return true;
                FiberManager::wait();
            }
            return false;
        });
    }

}
