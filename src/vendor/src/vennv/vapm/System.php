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
use function curl_init;
use function curl_multi_add_handle;
use function curl_multi_close;
use function curl_multi_exec;
use function curl_multi_getcontent;
use function curl_multi_init;
use function curl_multi_remove_handle;
use function file_get_contents;
use const CURLM_OK;
use const CURLOPT_RETURNTRANSFER;

interface SystemInterface
{

    /**
     * @throws Throwable
     *
     * This function is used to run the event loop with multiple event loops
     */
    public static function runEventLoop(): void;

    /**
     * @throws Throwable
     *
     * This function is used to run the event loop with single event loop
     */
    public static function runSingleEventLoop(): void;

    /**
     * @throws Throwable
     *
     * This function is used to initialize the event loop
     */
    public static function init(): void;

    /**
     * This function is used to run a callback in the event loop with timeout
     */
    public static function setTimeout(callable $callback, int $timeout): SampleMacro;

    /**
     * This function is used to clear the timeout
     */
    public static function clearTimeout(SampleMacro $sampleMacro): void;

    /**
     * This function is used to run a callback in the event loop with interval
     */
    public static function setInterval(callable $callback, int $interval): SampleMacro;

    /**
     * This function is used to clear the interval
     */
    public static function clearInterval(SampleMacro $sampleMacro): void;

    /**
     * @param string $url
     * @param array<string|null, string|array> $options
     * @return Promise when Promise resolve InternetRequestResult and when Promise reject Error
     * @throws Throwable
     * @phpstan-param array{method?: string, headers?: array<int, string>, timeout?: int, body?: array<string, string>} $options
     */
    public static function fetch(string $url, array $options = []): Promise;

    /**
     * @param string ...$curls
     * @return Promise
     * @throws Throwable
     *
     * Use this to curl multiple addresses at once
     */
    public static function fetchAll(string ...$curls): Promise;

    /**
     * @throws Throwable
     *
     * This is a function used only to retrieve results from an address or file path via the file_get_contents method
     */
    public static function read(string $path): Promise;

    /**
     * @param string $name
     * @return void
     *
     * This function is used to start a timer
     */
    public static function time(string $name = 'Console'): void;

    /**
     * @param string $name
     * @return void
     *
     * This function is used to end a timer
     */
    public static function timeEnd(string $name = 'Console'): void;

}

final class System extends EventLoop implements SystemInterface
{

    /**
     * @var array<string, int|float>
     */
    private static array $timings = [];

    private static bool $hasInit = false;

    /**
     * @throws Throwable
     */
    public static function runEventLoop(): void
    {
        self::init();
        parent::run();
    }

    /**
     * @throws Throwable
     */
    public static function runSingleEventLoop(): void
    {
        self::init();
        parent::runSingle();
    }

    public static function init(): void
    {
        if (!self::$hasInit) {
            self::$hasInit = true;
            register_shutdown_function(fn() => self::runSingleEventLoop());
            register_tick_function(fn() => CoroutineGen::run());
        }

        parent::init();
    }

    /**
     * @throws Throwable
     */
    public static function setTimeout(callable $callback, int $timeout): SampleMacro
    {
        self::init();
        $sampleMacro = new SampleMacro($callback, $timeout);
        MacroTask::addTask($sampleMacro);
        return $sampleMacro;
    }

    public static function clearTimeout(SampleMacro $sampleMacro): void
    {
        if ($sampleMacro->isRunning() && !$sampleMacro->isRepeat()) $sampleMacro->stop();
    }

    /**
     * @throws Throwable
     */
    public static function setInterval(callable $callback, int $interval): SampleMacro
    {
        self::init();
        $sampleMacro = new SampleMacro($callback, $interval, true);
        MacroTask::addTask($sampleMacro);
        return $sampleMacro;
    }

    public static function clearInterval(SampleMacro $sampleMacro): void
    {
        if ($sampleMacro->isRunning() && $sampleMacro->isRepeat()) $sampleMacro->stop();
    }

    /**
     * @param string $url
     * @param array<string|null, string|array> $options
     * @return Promise when Promise resolve InternetRequestResult and when Promise reject Error
     * @throws Throwable
     * @phpstan-param array{method?: string, headers?: array<int, string>, timeout?: int, body?: array<string, string>} $options
     */
    public static function fetch(string $url, array $options = []): Promise
    {
        return new Promise(function ($resolve, $reject) use ($url, $options) {
            self::setTimeout(function () use ($resolve, $reject, $url, $options) {
                $method = $options["method"] ?? "GET";

                /** @var array<int, string> $headers */
                $headers = $options["headers"] ?? [];

                /** @var int $timeout */
                $timeout = $options["timeout"] ?? 10;

                /** @var array<string, string> $body */
                $body = $options["body"] ?? [];

                $method === "GET" ? $result = Internet::getURL($url, $timeout, $headers) : $result = Internet::postURL($url, $body, $timeout, $headers);
                $result === null ? $reject(Error::FAILED_IN_FETCHING_DATA) : $resolve($result);
            }, 0);
        });
    }

    /**
     * @param string ...$curls
     * @return Promise
     * @throws Throwable
     *
     * Use this to curl multiple addresses at once
     */
    public static function fetchAll(string ...$curls): Promise
    {
        return new Promise(function ($resolve, $reject) use ($curls): void {
            $multiHandle = curl_multi_init();
            $handles = [];
            foreach ($curls as $url) {
                $handle = curl_init($url);
                if ($handle === false) {
                    $reject(Error::FAILED_IN_FETCHING_DATA);
                } else {
                    curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
                    curl_multi_add_handle($multiHandle, $handle);
                    $handles[] = $handle;
                }
            }

            $running = 0;

            do {
                $status = curl_multi_exec($multiHandle, $running);
                if ($status !== CURLM_OK) $reject(Error::FAILED_IN_FETCHING_DATA);
                FiberManager::wait();
            } while ($running > 0);

            $results = [];
            foreach ($handles as $handle) {
                $results[] = curl_multi_getcontent($handle);
                curl_multi_remove_handle($multiHandle, $handle);
            }

            curl_multi_close($multiHandle);
            $resolve($results);
        });
    }

    /**
     * @throws Throwable
     */
    public static function read(string $path): Promise
    {
        return new Promise(function ($resolve, $reject) use ($path) {
            self::setTimeout(function () use ($resolve, $reject, $path) {
                $ch = file_get_contents($path);
                $ch === false ? $reject(Error::FAILED_IN_FETCHING_DATA) : $resolve($ch);
            }, 0);
        });
    }

    public static function time(string $name = 'Console'): void
    {
        self::$timings[$name] = microtime(true);
    }

    public static function timeEnd(string $name = 'Console'): void
    {
        if (!isset(self::$timings[$name])) return;
        $time = microtime(true) - self::$timings[$name];
        echo "Time for $name: $time\n";
        unset(self::$timings[$name]);
    }

}
