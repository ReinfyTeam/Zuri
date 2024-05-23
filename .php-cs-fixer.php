<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

return (function () {
	$finder = Finder::create();
	foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . "/src")) as $file) {
		$file = realpath($file);
		if (substr($file, -4) === ".php") {
			$finder->append([$file]);
		}
	}


	return (new Config)
		->setRiskyAllowed(true)
		->setFinder($finder)
		->setIndent("\t")
		->setRules([
			"align_multiline_comment" => [
				"comment_type" => "phpdocs_only"
			],
			"array_indentation" => true,
			"array_syntax" => [
				"syntax" => "short"
			],
			"binary_operator_spaces" => [
				"default" => "single_space"
			],
			"blank_line_after_namespace" => true,
			"blank_line_after_opening_tag" => true,
			"blank_line_before_statement" => [
				"statements" => [
					"declare"
				]
			],
			"braces" => [
				"allow_single_line_closure" => false,
				"position_after_anonymous_constructs" => "same",
				"position_after_control_structures" => "same",
				"position_after_functions_and_oop_constructs" => "same",
			],
			"cast_spaces" => [
				"space" => "single"
			],
			"concat_space" => [
				"spacing" => "one"
			],
			"declare_strict_types" => true,
			"elseif" => true,
			"global_namespace_import" => [
				"import_constants" => true,
				"import_functions" => true,
				"import_classes" => null,
			],
			'header_comment' => [
				'comment_type' => 'comment',
				'header' => <<<BODY

	 ____           _            __           _____
	|  _ \    ___  (_)  _ __    / _|  _   _  |_   _|   ___    __ _   _ __ ___
	| |_) |  / _ \ | | | '_ \  | |_  | | | |   | |    / _ \  / _` | | '_ ` _ \
	|  _ <  |  __/ | | | | | | |  _| | |_| |   | |   |  __/ | (_| | | | | | | |
	|_| \_\  \___| |_| |_| |_| |_|    \__, |   |_|    \___|  \__,_| |_| |_| |_|
	                                  |___/

	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU Lesser General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.
	
	Zuri attempts to enforce "vanilla Minecraft" mechanics, as well as preventing
	players from abusing weaknesses in Minecraft or its protocol, making your server
	more safe. Organized in different sections, various checks are performed to test 
	players doing, covering a wide range including flying and speeding, fighting 
	hacks, fast block breaking and nukers, inventory hacks, chat spam and other types 
	of malicious behaviour.

	@author ReinfyTeam
	@link https://github.com/ReinfyTeam/


	BODY,
				'location' => 'after_open'
			],
			"indentation_type" => true,
			"native_function_invocation" => [
				"scope" => "namespaced",
				"include" => ["@all"],
			],
			"no_closing_tag" => true,
			"no_empty_phpdoc" => true,
			"no_superfluous_phpdoc_tags" => [
				"allow_mixed" => true,
			],
			"no_trailing_whitespace" => true,
			"no_trailing_whitespace_in_comment" => true,
			"no_whitespace_in_blank_line" => true,
			"no_unused_imports" => true,
			"ordered_imports" => [
				"imports_order" => [
					"class",
					"function",
					"const",
				],
				"sort_algorithm" => "alpha"
			],
			"phpdoc_line_span" => [
				"property" => "single",
				"method" => null,
				"const" => null
			],
			"phpdoc_trim" => true,
			"phpdoc_trim_consecutive_blank_line_separation" => true,
			"return_type_declaration" => [
				"space_before" => "one"
			],
			"single_import_per_statement" => true,
			"strict_param" => true,
			"unary_operator_spaces" => true,
		]);
})();