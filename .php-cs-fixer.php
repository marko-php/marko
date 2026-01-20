<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/packages',
        __DIR__ . '/demo/app',
        __DIR__ . '/demo/modules',
    ])
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

$rules = [
    '@PSR12' => true,
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
];

return (new PhpCsFixer\Config())
    ->setRules($rules)
    ->setFinder($finder)
    ->setRiskyAllowed(true)
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache');
