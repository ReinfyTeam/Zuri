<?php

use PhpCsFixer\Config;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

$finder = PhpCsFixer\Finder::create()
    ->in(new RecursiveDirectoryIterator("../vendor")) // Target specific vendor package
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

$config = new Config();
return $config->setRules([
        '@PSR12' => true,
        'encoding' => true, // Fixes potential BOM/encoding issues
        'no_trailing_whitespace' => true,
        'no_trailing_whitespace_in_comment' => true,
    ])
    ->setFinder($finder)
    ->setUsingCache(true);
