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
$serverPath = realpath($argv[1] ?? "");
$pluginPath = realpath($argv[2] ?? $root . "/build/Zuri.phar");
if ($serverPath === false || !is_file($serverPath) || $pluginPath === false || !is_file($pluginPath)) {
	fwrite(STDERR, "Usage: php -dphar.readonly=0 tools/test-runtime.php <PocketMine-MP.phar> [Zuri.phar]\n");
	exit(1);
}
$testRoot = $root . "/build/runtime-smoke-" . bin2hex(random_bytes(6));
mkdir($testRoot, 0777, true);
$probePath = $testRoot . "/RuntimeProbe.phar";
$probe = new Phar($probePath);
$probe["plugin.yml"] = "name: ZuriRuntimeProbe\nversion: 1.0.0\nmain: ZuriRuntimeTest\\RuntimeProbe\napi: 5.0.0\ndepend: [Zuri]\n";
$probe["src/ZuriRuntimeTest/RuntimeProbe.php"] = file_get_contents($root . "/tests/fixtures/RuntimeProbe.php");
$probe->setStub("<?php __HALT_COMPILER();");
unset($probe);

foreach ([[3, 60], [0, 20], [-2, 20]] as [$delay, $period]) {
	$runtime = $testRoot . "/delay-" . $delay;
	mkdir($runtime . "/plugins", 0777, true);
	mkdir($runtime . "/plugin_data/Zuri", 0777, true);
	mkdir($runtime . "/plugin_data/ZuriRuntimeProbe", 0777, true);
	copy($pluginPath, $runtime . "/plugins/Zuri.phar");
	copy($probePath, $runtime . "/plugins/RuntimeProbe.phar");
	$config = yaml_parse(file_get_contents("phar://" . $pluginPath . "/resources/config.yml"));
	$config["zuri"]["metrics"]["delay"] = $delay;
	file_put_contents($runtime . "/plugin_data/Zuri/config.yml", yaml_emit($config));
	file_put_contents($runtime . "/plugin_data/ZuriRuntimeProbe/expectations.json", json_encode(["period" => $period], JSON_THROW_ON_ERROR));
	$socket = stream_socket_server("udp://127.0.0.1:0", $errorCode, $errorMessage, STREAM_SERVER_BIND);
	if ($socket === false) {
		throw new RuntimeException("Cannot reserve a local test port: " . $errorMessage);
	}
	$port = substr(strrchr(stream_socket_get_name($socket, false), ":"), 1);
	fclose($socket);
	file_put_contents($runtime . "/server.properties", "server-ip=127.0.0.1\nserver-port=" . $port . "\nenable-ipv6=off\nmotd=Zuri runtime smoke test\nmax-players=2\nlevel-name=runtime-test\nlevel-type=FLAT\nview-distance=2\nspawn-protection=0\n");
	$process = proc_open([
		PHP_BINARY, $serverPath, "--data=" . $runtime, "--plugins=" . $runtime . "/plugins", "--no-wizard", "--disable-ansi"
	], [0 => ["pipe", "r"], 1 => ["file", $runtime . "/console.log", "w"], 2 => ["file", $runtime . "/stderr.log", "w"]], $pipes, $root);
	if (!is_resource($process)) {
		throw new RuntimeException("Unable to start PocketMine runtime test");
	}
	$deadline = microtime(true) + 45.0;
	$status = proc_get_status($process);
	while ($status["running"] && microtime(true) < $deadline) {
		usleep(100000);
		$status = proc_get_status($process);
	}
	$timedOut = $status["running"];
	if ($timedOut) {
		fwrite($pipes[0], "stop\n");
		$shutdownDeadline = microtime(true) + 5.0;
		while ($status["running"] && microtime(true) < $shutdownDeadline) {
			usleep(100000);
			$status = proc_get_status($process);
		}
		if ($status["running"]) {
			proc_terminate($process);
		}
	}
	fclose($pipes[0]);
	$closeCode = proc_close($process);
	$exitCode = $status["exitcode"] >= 0 ? $status["exitcode"] : $closeCode;
	$resultPath = $runtime . "/plugin_data/ZuriRuntimeProbe/result.json";
	$result = is_file($resultPath) ? json_decode(file_get_contents($resultPath), true, 512, JSON_THROW_ON_ERROR) : null;
	if ($timedOut || $exitCode !== 0 || ($result["ok"] ?? false) !== true || !str_starts_with($result["zuriFile"] ?? "", "phar://")) {
		fwrite(STDERR, "PocketMine runtime test failed for metrics delay " . $delay . ": " . json_encode($result) . "\n" . file_get_contents($runtime . "/console.log") . file_get_contents($runtime . "/stderr.log"));
		exit(1);
	}
	echo "PocketMine runtime: metrics delay " . $delay . ", startup and two periodic refreshes passed; clean shutdown\n";
}
echo "Runtime logs: " . $testRoot . "\n";
