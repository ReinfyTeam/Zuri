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
$workflow = yaml_parse_file($root . "/.github/workflows/release-build.yml");
$steps = $workflow["jobs"]["release"]["steps"];
$metadataStep = null;
$releaseStep = null;
foreach ($steps as $step) {
	if (($step["id"] ?? null) === "meta") {
		$metadataStep = $step;
	}
	if (($step["uses"] ?? null) === "softprops/action-gh-release@v3") {
		$releaseStep = $step;
	}
}
$assertions = 0;
$expect = static function(bool $condition, string $message) use (&$assertions) : void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
	++$assertions;
};
$expect(($metadataStep["run"] ?? null) === "php tools/release-metadata.php", "Workflow must execute the tested metadata script");
foreach (["prerelease", "make_latest"] as $key) {
	$expect(($releaseStep["with"][$key] ?? null) === '${{ steps.meta.outputs.' . $key . ' }}', "Release action must consume the tested " . $key . " output");
}
$temp = sys_get_temp_dir() . "/zuri-release-" . bin2hex(random_bytes(8));
mkdir($temp);
$sha = str_repeat("a", 40);
$body = "Fix terrain readiness\n\nPreserve literal text: \$(echo ignored); `echo ignored`";
try {
	foreach ([
		["1.4.0-BETA", true], ["v1.4.0-ALPHA", true], ["1.4.0-rc.1", true],
		["1.4.0-beta.2", true], ["1.4.0.BETA", true], ["1.4.0", false], ["v1.4.1", false],
		["", null], ["1.4", null], ["v1.4.0-BETA malformed", null], ["1.4.0; echo invalid", null]
	] as [$inputVersion, $isPrerelease]) {
		foreach (["workflow_dispatch", "push"] as $event) {
			$environment = array_merge(getenv(), [
				"EVENT_NAME" => $event,
				"INPUT_VERSION" => $inputVersion,
				"INPUT_CHANGELOG" => $body,
				"RAW_MESSAGE" => "Release: " . $inputVersion . "\r\n" . str_replace("\n", "\r\n", $body),
				"SHA" => $sha,
				"GITHUB_OUTPUT" => $temp . "/outputs.txt"
			]);
			$process = proc_open([PHP_BINARY, $root . "/tools/release-metadata.php"], [1 => ["pipe", "w"], 2 => ["pipe", "w"]], $pipes, $temp, $environment);
			if (!is_resource($process)) {
				throw new RuntimeException("Unable to run release metadata regression");
			}
			$output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
			fclose($pipes[1]);
			fclose($pipes[2]);
			$exitCode = proc_close($process);
			$expect(($exitCode === 0) === ($isPrerelease !== null), "Unexpected release version acceptance for " . $event . ": " . $inputVersion . "\n" . $output);
			if ($isPrerelease !== null) {
				$values = [];
				foreach (explode("\n", trim(file_get_contents($temp . "/outputs.txt"))) as $line) {
					[$key, $value] = explode("=", $line, 2);
					$values[$key] = $value;
				}
				$version = ltrim($inputVersion, "v");
				$expect($values["prerelease"] === ($isPrerelease ? "true" : "false"), "Release stage must match " . $inputVersion);
				$expect($values["make_latest"] === ($isPrerelease ? "false" : "true"), "Only stable releases may become Latest");
				$expect($values["tag"] === "v" . $version && $values["target"] === $sha, "Release must target the exact normalized version and tested commit");
				$expect(file_get_contents($temp . "/release-notes.md") === "# Zuri v" . $version . "\n\n" . $body . "\n", "Release notes must preserve multiline input as literal text");
			} else {
				$expect(!file_exists($temp . "/outputs.txt") && !file_exists($temp . "/release-notes.md"), "Invalid versions must not produce publishable metadata");
			}
			foreach (["outputs.txt", "release-notes.md"] as $file) {
				if (file_exists($temp . "/" . $file)) {
					unlink($temp . "/" . $file);
				}
			}
		}
	}
} finally {
	foreach (["outputs.txt", "release-notes.md"] as $file) {
		if (file_exists($temp . "/" . $file)) {
			unlink($temp . "/" . $file);
		}
	}
	rmdir($temp);
}
echo "Release metadata regression: " . $assertions . " assertions passed\n";
