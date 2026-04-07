<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/vendor/') // Target specific vendor package
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

$config = new PhpCsFixer\Config();
return $config->setRules([
        '@PSR12' => true,
        'encoding' => true, // Fixes potential BOM/encoding issues
        'no_trailing_whitespace' => true,
        'no_trailing_whitespace_in_comment' => true,
    ])
    ->setFinder($finder)
    ->setUsingCache(true);