#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Fixes method/function signatures to have each parameter on its own line.
 *
 * Usage:
 *   php tools/fix-multiline-params.php [path]
 *   php tools/fix-multiline-params.php packages/core/src
 *   php tools/fix-multiline-params.php --dry-run packages/
 */

$dryRun = false;
$paths = [];

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--dry-run') {
        $dryRun = true;
    } else {
        $paths[] = $arg;
    }
}

if ($paths === []) {
    $paths = [__DIR__ . '/../packages'];
}

$fixedFiles = 0;
$totalFixes = 0;

foreach ($paths as $path) {
    $fullPath = str_starts_with($path, '/') ? $path : __DIR__ . '/../' . $path;

    if (is_file($fullPath)) {
        $result = processFile($fullPath, $dryRun);
        if ($result > 0) {
            $fixedFiles++;
            $totalFixes += $result;
        }
    } elseif (is_dir($fullPath)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($fullPath),
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                continue;
            }
            if ($file->getExtension() !== 'php') {
                continue;
            }
            if (str_contains($file->getPathname(), '/vendor/')) {
                continue;
            }

            $result = processFile($file->getPathname(), $dryRun);
            if ($result > 0) {
                $fixedFiles++;
                $totalFixes += $result;
            }
        }
    }
}

echo "\n";
if ($dryRun) {
    echo "Dry run: would fix $totalFixes signatures in $fixedFiles files\n";
} else {
    echo "Fixed $totalFixes signatures in $fixedFiles files\n";
}

function processFile(
    string $filePath,
    bool $dryRun,
): int {
    $content = file_get_contents($filePath);
    $original = $content;
    $fixes = 0;

    // Process line by line to detect indentation
    $lines = explode("\n", $content);
    $newLines = [];
    $i = 0;

    while ($i < count($lines)) {
        $line = $lines[$i];

        // Match single-line function signature with parameters
        // Capture leading whitespace to preserve indentation
        if (preg_match(
            '/^(\s*)((?:public|protected|private|static)\s+)*function\s+\w+\(([^)]+)\)(\s*:\s*[?\w\\\\|&]+)?\s*\{?\s*$/',
            $line,
            $matches
        )) {
            $leadingWhitespace = $matches[1];
            $modifiers = $matches[2] ?? '';
            $params = $matches[3];
            $returnType = $matches[4] ?? '';

            // Skip if already multiline (params contain newline) or no params
            if (str_contains($params, "\n") || trim($params) === '') {
                $newLines[] = $line;
                $i++;
                continue;
            }

            // Parse parameters
            $paramList = parseParameters($params);
            if (count($paramList) === 0) {
                $newLines[] = $line;
                $i++;
                continue;
            }

            $fixes++;

            // Extract function declaration part
            preg_match('/^(\s*(?:(?:public|protected|private|static)\s+)*function\s+\w+)\(/', $line, $funcMatch);
            $funcDecl = $funcMatch[1];

            // Calculate parameter indentation (base + 4 spaces)
            $paramIndent = $leadingWhitespace . '    ';

            // Build new lines
            $newLines[] = $funcDecl . '(';
            foreach ($paramList as $param) {
                $newLines[] = $paramIndent . trim($param) . ',';
            }

            // Closing paren with return type and brace
            $closingLine = $leadingWhitespace . ')';
            if ($returnType !== '') {
                $closingLine .= $returnType;
            }
            $closingLine .= ' {';
            $newLines[] = $closingLine;

            // Check if next line is just '{' and skip it
            if (isset($lines[$i + 1]) && trim($lines[$i + 1]) === '{') {
                $i++;
            }

            $i++;
            continue;
        }

        $newLines[] = $line;
        $i++;
    }

    $newContent = implode("\n", $newLines);

    if ($newContent !== $original) {
        $relativePath = str_replace(dirname(__DIR__) . '/', '', $filePath);
        if ($dryRun) {
            echo "Would fix: $relativePath ($fixes signatures)\n";
        } else {
            file_put_contents($filePath, $newContent);
            echo "Fixed: $relativePath ($fixes signatures)\n";
        }

        return $fixes;
    }

    return 0;
}

function parseParameters(
    string $params,
): array {
    $result = [];
    $current = '';
    $depth = 0;

    for ($i = 0; $i < strlen($params); $i++) {
        $char = $params[$i];

        if ($char === '(' || $char === '[') {
            $depth++;
            $current .= $char;
        } elseif ($char === ')' || $char === ']') {
            $depth--;
            $current .= $char;
        } elseif ($char === ',' && $depth === 0) {
            $trimmed = trim($current);
            if ($trimmed !== '') {
                $result[] = $trimmed;
            }
            $current = '';
        } else {
            $current .= $char;
        }
    }

    $trimmed = trim($current);
    if ($trimmed !== '') {
        $result[] = $trimmed;
    }

    return $result;
}
