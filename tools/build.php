<?php

declare(strict_types=1);

const PHARYNX_DOWNLOAD = "https://github.com/SOF3/pharynx/releases/latest/download/pharynx.phar";

$projectRoot = dirname(__DIR__);
$pluginYmlPath = $projectRoot . DIRECTORY_SEPARATOR . "plugin.yml";
$composerPath = $projectRoot . DIRECTORY_SEPARATOR . "composer.json";
$buildDir = $projectRoot . DIRECTORY_SEPARATOR . "build";
$toolsBinDir = __DIR__ . DIRECTORY_SEPARATOR . "bin";
$pharynxPhar = $toolsBinDir . DIRECTORY_SEPARATOR . "pharynx.phar";
$buildSource = false;
$sourceOutputPath = $projectRoot . DIRECTORY_SEPARATOR . "output";

foreach (array_slice($argv, 1) as $argument) {
	if ($argument === "--source") {
		$buildSource = true;
		continue;
	}

	if (str_starts_with($argument, "--output=")) {
		$value = trim(substr($argument, strlen("--output=")));
		if ($value !== "") {
			$sourceOutputPath = str_starts_with($value, DIRECTORY_SEPARATOR)
				? $value
				: $projectRoot . DIRECTORY_SEPARATOR . $value;
		}
	}
}

if (!file_exists($pluginYmlPath)) {
	fwrite(STDERR, "plugin.yml not found in project root.\n");
	exit(1);
}

if (!file_exists($composerPath)) {
	fwrite(STDERR, "composer.json not found. Virion-aware builds require composer dependencies.\n");
	exit(1);
}

$pluginName = extractPluginName($pluginYmlPath);
$outputPath = $buildDir . DIRECTORY_SEPARATOR . $pluginName . ".phar";

@mkdir($buildDir, 0777, true);
@mkdir($toolsBinDir, 0777, true);

if (!file_exists($pharynxPhar)) {
	echo "Downloading pharynx...\n";
	$pharynxContents = @file_get_contents(PHARYNX_DOWNLOAD);
	if ($pharynxContents === false) {
		fwrite(STDERR, "Failed to download pharynx from " . PHARYNX_DOWNLOAD . "\n");
		exit(1);
	}
	file_put_contents($pharynxPhar, $pharynxContents);
}

echo "Installing composer dependencies (including virions)...\n";
runCommand("/usr/local/bin/composer install --prefer-dist --no-interaction --working-dir " . escapeshellarg($projectRoot));

@unlink($outputPath);

$pathValue = "/usr/local/bin:/usr/bin:/bin:" . (getenv("PATH") ?: "");

$pharynxCommand = "PATH=" . escapeshellarg($pathValue) . " "
	. escapeshellarg(PHP_BINARY)
	. " -d phar.readonly=0"
	. " " . escapeshellarg($pharynxPhar)
	. " -i " . escapeshellarg($projectRoot)
	. ($buildSource ? " -o" . escapeshellarg($sourceOutputPath) : " -p" . escapeshellarg($outputPath))
	. " -c" . escapeshellarg($projectRoot);

echo $buildSource ? "Building virion-injected source tree...\n" : "Building virion-injected phar...\n";
runCommand($pharynxCommand);

echo "Success! Output path: " . ($buildSource ? $sourceOutputPath : $outputPath) . "\n";

function runCommand(string $command) : void {
	passthru($command, $exitCode);
	if ($exitCode !== 0) {
		fwrite(STDERR, "Command failed ({$exitCode}): {$command}\n");
		exit($exitCode);
	}
}

function extractPluginName(string $pluginYmlPath) : string {
	$contents = file_get_contents($pluginYmlPath);
	if ($contents === false) {
		fwrite(STDERR, "Failed to read plugin.yml\n");
		exit(1);
	}

	if (!preg_match('/^name:\s*["\']?([^"\'\r\n]+)["\']?\s*$/mi', $contents, $matches)) {
		fwrite(STDERR, "Failed to detect plugin name from plugin.yml\n");
		exit(1);
	}

	return trim($matches[1]);
}
