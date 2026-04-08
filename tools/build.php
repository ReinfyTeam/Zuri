<?php

declare(strict_types=1);

const PHARYNX_DOWNLOAD = "https://github.com/SOF3/pharynx/releases/latest/download/pharynx.phar";

$projectRoot = dirname(__DIR__);
$pluginYmlPath = $projectRoot . DIRECTORY_SEPARATOR . "plugin.yml";
$composerPath = $projectRoot . DIRECTORY_SEPARATOR . "composer.json";
$buildDir = $projectRoot . DIRECTORY_SEPARATOR . "build";
$toolsBinDir = __DIR__ . DIRECTORY_SEPARATOR . "bin";
$pharynxPhar = $toolsBinDir . DIRECTORY_SEPARATOR . "pharynx.phar";

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
runCommand("composer install --no-dev --prefer-dist --no-interaction --working-dir " . escapeshellarg($projectRoot));

@unlink($outputPath);

$pharynxCommand = escapeshellarg(PHP_BINARY)
	. " -d phar.readonly=0"
	. " " . escapeshellarg($pharynxPhar)
	. " -i " . escapeshellarg($projectRoot)
	. " -p" . escapeshellarg($outputPath)
	. " -c" . escapeshellarg($projectRoot);

echo "Building virion-injected phar...\n";
runCommand($pharynxCommand);

echo "Success! Output path: {$outputPath}\n";

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
