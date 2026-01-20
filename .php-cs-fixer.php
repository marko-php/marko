<?php

declare(strict_types=1);

use Marko\DevTools\PhpCsFixer\PhpdocConsolidateThrowsFixer;
use Marko\DevTools\PhpCsFixer\SimpleStringVariableFixer;
use PhpCsFixer\Config;
use PhpCsFixer\Finder;

// Load custom fixers
require_once __DIR__ . '/dev/php-cs-fixer/PhpdocConsolidateThrowsFixer.php';
require_once __DIR__ . '/dev/php-cs-fixer/SimpleStringVariableFixer.php';

$finder = Finder::create()
    ->in([
        __DIR__ . '/packages',
        __DIR__ . '/demo/app',
        __DIR__ . '/demo/modules',
    ])
    ->exclude('vendor')
    ->exclude('.phpunit.cache')
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

$rules = [
    '@PSR12' => true,
    'braces_position' => [
        'anonymous_classes_opening_brace' => 'next_line_unless_newline_at_signature_end',
    ],
    'fully_qualified_strict_types' => ['import_symbols' => true],
    'global_namespace_import' => ['import_classes' => true, 'import_constants' => false, 'import_functions' => false],
    'array_syntax' => ['syntax' => 'short'],
    'trailing_comma_in_multiline' => ['elements' => ['arrays', 'match', 'parameters', 'arguments']],
    'array_indentation' => true,
    'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline', 'after_heredoc' => true],
    'class_attributes_separation' => ['elements' => ['method' => 'one', 'property' => 'one']],
    'single_line_empty_body' => true,
    'no_unused_imports' => true,
    'ordered_imports' => ['sort_algorithm' => 'alpha'],
    'single_quote' => true,
    'no_extra_blank_lines' => true,
    'no_whitespace_in_blank_line' => true,
    'Marko/phpdoc_consolidate_throws' => true,
    'Marko/simple_string_variable' => true,
];

return (new Config())
    ->setRules($rules)
    ->setFinder($finder)
    ->setRiskyAllowed(true)
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache')
    ->registerCustomFixers([
        new PhpdocConsolidateThrowsFixer(),
        new SimpleStringVariableFixer(),
    ]);
