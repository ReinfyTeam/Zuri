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

use vennv\vapm\utils\Utils;
use Closure;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use InvalidArgumentException;
use Throwable;
use function strlen;
use function rtrim;
use function fclose;
use function fwrite;
use function get_called_class;
use function is_array;
use function is_callable;
use function is_resource;
use function is_string;
use function json_decode;
use function json_encode;
use function proc_get_status;
use function proc_open;
use function str_replace;
use function stream_get_contents;
use function stream_set_blocking;
use const PHP_BINARY;
use const PHP_EOL;
use const STDIN;
use const STDOUT;

interface ThreadInterface
{

    /**
     * This abstract method use to run the thread
     */
    public function onRun(): void;

    /**
     * @param array<int, array<string>> $mode
     * @throws ReflectionException
     * @throws Throwable
     * @phpstan-param array<int, array<string>> $mode
     *
     * This method use to start the thread
     */
    public function start(array $mode = DescriptorSpec::BASIC): Promise;

}

interface ThreadedInterface
{

    /**
     * @return mixed
     */
    public function getInput(): mixed;

    /**
     * This method use to get the pid of the thread
     */
    public function getPid(): int;

    /**
     * This method use to get the exit code of the thread
     */
    public function getExitCode(): int;

    /*
     * This method use to get the running status of the thread
     */
    public function isRunning(): bool;

    /**
     * This method use to get the signaled status of the thread
     */
    public function isSignaled(): bool;

    /**
     * This method use to get the stopped status of the thread
     */
    public function isStopped(): bool;

    /**
     * @return array<string, mixed>
     * @phpstan-return array<string, mixed>
     *
     * This method use to get the shared data of the main thread
     */
    public static function getDataMainThread(): array;

    /**
     * @param array<string, mixed> $shared
     * @phpstan-param array<string, mixed> $shared
     *
     * This method use to set the shared data of the main thread
     */
    public static function setShared(array $shared): void;

    /**
     * @param string $key
     * @param mixed $value
     * @phpstan-param mixed $value
     *
     * This method use to add the shared data of the MAIN-THREAD
     */
    public static function addShared(string $key, mixed $value): void;

    /**
     * @return array<string, mixed>
     *
     * This method use to get the shared data of the child thread
     */
    public static function getSharedData(): array;

    /**
     * @param array<string, mixed> $data
     * @return void
     * @phpstan-param array<string, mixed> $data
     *
     * This method use to post all data the main thread
     */
    public static function postMainThread(array $data): void;

    /**
     * @param string $data
     * @return void
     *
     * This method use to load the shared data from the main thread
     */
    public static function loadSharedData(string $data): void;

    /**
     * @param string $data
     * @return void
     *
     * This method use to post the data on the thread
     */
    public static function post(string $data): void;

    /**
     * @param int $pid
     * @return bool
     *
     * This method use to check the thread is running or not
     */
    public static function threadIsRunning(int $pid): bool;

    /**
     * @param int $pid
     * @return bool
     *
     * This method use to kill the thread
     */
    public static function killThread(int $pid): bool;

}

abstract class Thread implements ThreadInterface, ThreadedInterface
{

    private const POST_MAIN_THREAD = 'postMainThread'; // example: postMainThread=>{data}

    private const POST_THREAD = 'postThread'; // example: postAlertThread=>{data}

    private int $pid = -1;

    private int $exitCode = -1;

    private bool $isRunning = false;

    private bool $signaled = false;

    private bool $stopped = false;

    /**
     * @var array<string, mixed>
     * @phpstan-var array<string, mixed>
     */
    private static array $shared = [];

    /**
     * @var array<int, Thread>
     * @phpstan-var array<int, Thread>
     */
    private static array $threads = [];

    /**
     * @var array<int|string, mixed>
     * @phpstan-var array<int|string, mixed>
     */
    private static array $inputs = [];

    /**
     * @var array<int, mixed>
     * @phpstan-var array<int, mixed>
     */
    private static array $args = [];

    /**
     * @param mixed $input
     * @param array<int, mixed> $args
     * @phpstan-param array<int, mixed> $args
     */
    public function __construct(mixed $input = '', array $args = [])
    {
        self::$inputs[$this->getCalledClassId()] = $input;
        self::$args[$this->getCalledClassId()] = $args;
    }

    private function getCalledClassId(): int
    {
        return spl_object_id($this);
    }

    public function getInput(): mixed
    {
        return self::$inputs[$this->getCalledClassId()];
    }

    public function getPid(): int
    {
        return $this->pid;
    }

    public function setPid(int $pid): void
    {
        $this->pid = $pid;
    }

    public function getExitCode(): int
    {
        return $this->exitCode;
    }

    protected function setExitCode(int $exitCode): void
    {
        $this->exitCode = $exitCode;
    }

    public function isRunning(): bool
    {
        return $this->isRunning;
    }

    protected function setRunning(bool $isRunning): void
    {
        $this->isRunning = $isRunning;
    }

    public function isSignaled(): bool
    {
        return $this->signaled;
    }

    protected function setSignaled(bool $signaled): void
    {
        $this->signaled = $signaled;
    }

    public function isStopped(): bool
    {
        return $this->stopped;
    }

    protected function setStopped(bool $stopped): void
    {
        $this->stopped = $stopped;
    }

    /**
     * @return array<string, mixed>
     * @phpstan-return array<string, mixed>
     */
    public static function getDataMainThread(): array
    {
        return self::$shared;
    }

    /**
     * @param array<string, mixed> $shared
     * @phpstan-param array<string, mixed> $shared
     */
    public static function setShared(array $shared): void
    {
        self::$shared = $shared;
    }

    public static function addShared(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    /**
     * @return array<int|string, mixed>
     * @phpstan-return array<int|string, mixed>
     */
    public static function getSharedData(): array
    {
        $data = fgets(STDIN);

        if (is_string($data)) {
            $data = json_decode($data, true);
            if (is_array($data)) return $data;
        }

        return [];
    }

    /**
     * @param array<string, mixed> $data
     * @phpstan-param array<string, mixed> $data
     */
    public static function postMainThread(array $data): void
    {
        fwrite(STDOUT, self::POST_MAIN_THREAD . '=>' . json_encode($data) . PHP_EOL);
    }

    private static function isPostMainThread(string $data): bool
    {
        return strlen(Utils::getStringAfterSign($data, self::POST_MAIN_THREAD . '=>')) > 0;
    }

    private static function isPostThread(string $data): bool
    {
        return strlen(Utils::getStringAfterSign($data, self::POST_THREAD . '=>')) > 0;
    }

    public static function loadSharedData(string $data): void
    {
        if (self::isPostMainThread($data)) {
            $result = json_decode(Utils::getStringAfterSign($data, self::POST_MAIN_THREAD . '=>'), true);
            if (is_array($result)) self::setShared(array_merge(self::$shared, $result));
        }
    }

    public static function post(string $data): void
    {
        fwrite(STDOUT, self::POST_THREAD . '=>' . $data . PHP_EOL);
    }

    public static function threadIsRunning(int $pid): bool
    {
        return isset(self::$threads[$pid]);
    }

    public static function killThread(int $pid): bool
    {
        if (isset(self::$threads[$pid])) {
            $thread = self::$threads[$pid];

            if ($thread->isRunning()) {
                $thread->setStopped(true);
                return true;
            }
        }

        return false;
    }

    abstract public function onRun(): void;

    /**
     * @param array<int, array<string>> $mode
     * @return Promise
     * @throws ReflectionException
     * @throws Throwable
     * @phpstan-param array<int, array<string>> $mode
     * @phpstan-return Promise
     */
    public function start(array $mode = DescriptorSpec::BASIC): Promise
    {
        return new Promise(function ($resolve, $reject) use ($mode): mixed {
            $idCall = $this->getCalledClassId();

            $className = get_called_class();

            $reflection = new ReflectionClass($className);

            $class = $reflection->getFileName();

            $pathAutoLoad = __FILE__;
            $pathAutoLoad = str_replace(
                'src\vennv\vapm\Thread.php',
                'src\vendor\autoload.php',
                $pathAutoLoad
            );

            $input = self::$inputs[$idCall];

            if (is_string($input)) $input = '\'' . self::$inputs[$idCall] . '\'';

            if (is_callable($input) && $input instanceof Closure) {
                try {
                    $input = Utils::closureToStringSafe($input);
                } catch (Throwable $e) {
                    return $reject(new ThreadException($e->getMessage()));
                }
            }

            if (!is_string($input)) return $reject(new RuntimeException(Error::INPUT_MUST_BE_STRING_OR_CALLABLE));

            $args = self::$args[$idCall];

            if (is_array($args)) {
                foreach ($args as $key => $arg) {
                    $tryToString = Utils::toStringAny($arg);
                    $args[$key] = array_values($tryToString)[0];
                    FiberManager::wait();
                }
            } else {
                throw new InvalidArgumentException('Expected $args to be an array or Traversable.');
            }

            $args = '[' . implode(', ', array_map(function($item) { return '' . $item . ''; }, $args)) . ']';
            $args = str_replace('"', '\'', $args);

            $command = PHP_BINARY . ' -r "require_once \'' . $pathAutoLoad . '\'; include \'' . $class . '\'; $input = ' . $input . '; $args = ' . $args . '; $class = new ' . static::class . '($input, $args); $class->onRun();"';
            
            unset(self::$inputs[$idCall]);
            unset(self::$args[$idCall]);

            $process = proc_open($command, $mode, $pipes);

            $timeStart = microtime(true);
            while (microtime(true) - $timeStart <= 1) {
                FiberManager::wait(); // wait for 1 second
            }

            $output = '';
            $error = '';

            if (is_resource($process)) {
                stream_set_blocking($pipes[0], false);
                stream_set_blocking($pipes[1], false);
                stream_set_blocking($pipes[2], false);

                stream_set_write_buffer($pipes[0], 0);
                stream_set_write_buffer($pipes[1], 0);
                stream_set_write_buffer($pipes[2], 0);

                $data = json_encode(self::getDataMainThread());

                if (is_string($data)) @fwrite($pipes[0], $data);
                @fclose($pipes[0]);

                $status = proc_get_status($process);
                $pid = $status['pid'];
                if (!isset(self::$threads[$pid])) {
                    $this->setPid($pid);
                    self::$threads[$pid] = $this;
                }

                $thread = self::$threads[$pid];
                $thread->setExitCode($status['exitcode']);
                $thread->setRunning($status['running']);
                $thread->setSignaled($status['signaled']);
                $thread->setStopped($status['stopped']);
                while ($thread->isRunning()) {
                    $status = proc_get_status($process);
                    $thread->setExitCode($status['exitcode']);
                    $thread->setRunning($status['running']);
                    $thread->setSignaled($status['signaled']);
                    $thread->setStopped($status['stopped']);

                    if ($thread->isRunning()) {
                        $read = [$pipes[1], $pipes[2]];
                        $write = null;
                        $except = null;
                        $timeout = 0;
                        $n = stream_select($read, $write, $except, $timeout);
                        if ($n === false) break;
                        if ($n > 0) {
                            foreach ($read as $stream) {
                                if (!feof($stream)) {
                                    $data = stream_get_contents($stream);
                                    if ($data !== '') {
                                        $stream === $pipes[1] ? $output .= $data : $error .= $data;
                                    }
                                }
                                FiberManager::wait();
                            }
                        }
                    } elseif ($thread->isStopped() || $thread->isSignaled()) {
                        proc_terminate($process);
                        break;
                    } else {
                        proc_terminate($process);
                        break;
                    }
                    FiberManager::wait();
                }

                $outputStream = stream_get_contents($pipes[1]);
                $errorStream = stream_get_contents($pipes[2]);
                if (!is_bool($outputStream)) $output .= str_contains($output, $outputStream) ? '' : $outputStream;
                if (!is_bool($errorStream)) $error .= str_contains($error, $errorStream) ? '' : $errorStream;

                fclose($pipes[1]);
                fclose($pipes[2]);

                if ($error !== '') {
                    return $reject(new ThreadException($error));
                } else {
                    if ($output !== '' && self::isPostMainThread($output)) self::loadSharedData($output);
                    elseif ($output !== '' && self::isPostThread($output)) {
                        $output = rtrim(Utils::getStringAfterSign($output, self::POST_THREAD . '=>'));
                    }
                }
            } else {
                return $reject(new ThreadException(Error::UNABLE_START_THREAD));
            }

            proc_close($process);
            unset(self::$threads[$this->getPid()]);
            return $resolve($output);
        });
    }

}