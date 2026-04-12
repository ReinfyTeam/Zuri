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

final class Error
{

    public const FAILED_IN_FETCHING_DATA = "Error in fetching data";

    public const WRONG_TYPE_WHEN_USE_CURL_EXEC = "curl_exec() should return string|false when CURL-OPT_RETURN-TRANSFER is set";

    public const UNABLE_START_THREAD = "Unable to start thread";

    public const DEFERRED_CALLBACK_MUST_RETURN_GENERATOR = "Deferred callback must return a Generator";

    public const UNABLE_TO_OPEN_FILE = "Error: Unable to open file!";

    public const FILE_DOES_NOT_EXIST = "Error: File does not exist!";

    public const FILE_ALREADY_EXISTS = "Error: File already exists!";

    public const CANNOT_FIND_FUNCTION_KEYWORD = "Cannot find function or fn keyword in closure";

    public const CANNOT_READ_FILE = "Cannot read file";

    public const INPUT_MUST_BE_STRING_OR_CALLABLE = "Input must be string or callable";

    public const ERROR_TO_CREATE_SOCKET = "Error to create socket";

    public const PAYLOAD_TOO_LARGE = "Payload too large";

    public const INVALID_ARRAY = "Invalid array";

    public const ASYNC_AWAIT_MUST_CALL_IN_ASYNC_FUNCTION = "Async::await() must call in async function";

    public const CHANNEL_IS_CLOSED = "Channel is closed";

}