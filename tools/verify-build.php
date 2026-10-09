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

$path = $argv[1] ?? dirname(__DIR__) . "/build/Zuri.phar";
$phar = new Phar($path);
foreach (["plugin.yml", "src/ReinfyTeam/Zuri/ZuriAC.php", "resources/config.yml", "resources/constants.yml", "resources/lang/en_US.yml"] as $entry) {
	if (!isset($phar[$entry])) {
		throw new RuntimeException("Build is missing " . $entry);
	}
}
$metadata = yaml_parse($phar["plugin.yml"]->getContent());
if (!is_array($metadata) || ($metadata["main"] ?? null) !== 'ReinfyTeam\Zuri\ZuriAC') {
	throw new RuntimeException("Build has an invalid plugin entry point");
}
$temporarySource = tempnam(sys_get_temp_dir(), "zuri-build-");
if ($temporarySource === false) {
	throw new RuntimeException("Unable to create a build validation file");
}
$files = 0;
try {
	foreach (new RecursiveIteratorIterator($phar) as $file) {
		if ($file->isFile() && $file->getExtension() === "php") {
			if (file_put_contents($temporarySource, $file->getContent()) === false) {
				throw new RuntimeException("Unable to read compiled PHP source");
			}
			$process = proc_open([PHP_BINARY, "-l", $temporarySource], [1 => ["pipe", "w"], 2 => ["pipe", "w"]], $pipes);
			if (!is_resource($process)) {
				throw new RuntimeException("Unable to check compiled PHP source");
			}
			$output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
			fclose($pipes[1]);
			fclose($pipes[2]);
			if (proc_close($process) !== 0) {
				throw new RuntimeException($file->getPathname() . "\n" . $output);
			}
			++$files;
		}
	}
} finally {
	unlink($temporarySource);
}

if ($files === 0) {
	throw new RuntimeException("Build contains no PHP source");
}
echo "PHAR validation: metadata, resources and " . $files . " PHP files passed\n";
