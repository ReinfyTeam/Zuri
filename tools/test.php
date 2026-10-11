<?php

/*
 *
 *  ____           _            __           _____
 * |  _ \    ___  (_)  _ __    / _|  _   _  |_   _|   ___    __ _   _ __ ___
 * | |_) |  / _ \ | | | '_ \  | |_  | | | |   | |    / _ \  / _` | | '_ ` _ \
 * |  _ <  |  __/ | | | | | | |  _| | |_| |   | |   |  __/ | (_| | | | | | | |
 * |_| \_\  \___| |_| |_| |_| |_|    \__, |   |_|    \___|  \__,_| |_| |_| |_|
 *                                   |___/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Zuri attempts to enforce "vanilla Minecraft" mechanics, as well as preventing
 * players from abusing weaknesses in Minecraft or its protocol, making your server
 * more safe. Organized in different sections, various checks are performed to test
 * players doing, covering a wide range including flying and speeding, fighting
 * hacks, fast block breaking and nukers, inventory hacks, chat spam and other types
 * of malicious behaviour.
 *
 * @author ReinfyTeam
 * @link https://github.com/ReinfyTeam/
 *
 *
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$files = [];
foreach (["src", "tools", "tests"] as $directory) {
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . "/" . $directory, FilesystemIterator::SKIP_DOTS));
	foreach ($iterator as $file) {
		if ($file->isFile() && $file->getExtension() === "php") {
			$files[] = $file->getPathname();
		}
	}
}
sort($files);
foreach ($files as $file) {
	$process = proc_open([PHP_BINARY, "-l", $file], [1 => ["pipe", "w"], 2 => ["pipe", "w"]], $pipes);
	if (!is_resource($process)) {
		throw new RuntimeException("Unable to start PHP syntax check");
	}
	$output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	if (proc_close($process) !== 0) {
		fwrite(STDERR, $output);
		exit(1);
	}
}
echo "PHP syntax: " . count($files) . " files passed\n";
$tests = glob($root . "/tests/*.php");
if ($tests === false || $tests === []) {
	throw new RuntimeException("No regression tests found");
}
foreach ($tests as $test) {
	$process = proc_open([PHP_BINARY, $test], [1 => ["pipe", "w"], 2 => ["pipe", "w"]], $pipes);
	if (!is_resource($process)) {
		throw new RuntimeException("Unable to start regression test");
	}
	echo stream_get_contents($pipes[1]);
	fwrite(STDERR, stream_get_contents($pipes[2]));
	fclose($pipes[1]);
	fclose($pipes[2]);
	if (($exitCode = proc_close($process)) !== 0) {
		exit($exitCode);
	}
}
