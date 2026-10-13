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

$buildDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . "build";
if (!is_dir($buildDirectory) && !mkdir($buildDirectory, 0777, true)) {
	fwrite(STDERR, "Unable to create build directory\n");
	exit(1);
}

// Pharynx 0.3.x still reads the historical PocketMine metadata path while
// the project installs the maintained Axolotl-PM package. Expose metadata
// only at that path; do not duplicate the runtime source tree.
$axolotlMetadata = dirname(__DIR__) . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "axolotl-pm" . DIRECTORY_SEPARATOR . "pocketmine-mp" . DIRECTORY_SEPARATOR . "composer.json";
$legacyMetadataDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "pocketmine" . DIRECTORY_SEPARATOR . "pocketmine-mp";
$legacyMetadata = $legacyMetadataDirectory . DIRECTORY_SEPARATOR . "composer.json";
if (!is_file($axolotlMetadata)) {
	fwrite(STDERR, "Axolotl-PM Composer metadata is missing\n");
	exit(1);
}
if (!is_dir($legacyMetadataDirectory) && !mkdir($legacyMetadataDirectory, 0777, true)) {
	fwrite(STDERR, "Unable to create the Pharynx metadata directory\n");
	exit(1);
}
if (!copy($axolotlMetadata, $legacyMetadata)) {
	fwrite(STDERR, "Unable to expose Axolotl-PM Composer metadata to Pharynx\n");
	exit(1);
}
