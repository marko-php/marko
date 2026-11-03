<?php

declare(strict_types=1);

use Marko\Tests\Support\SqlIdentifierQuoting\HardCodedIdentifierQuoteDetector;

require_once __DIR__ . '/Support/SqlIdentifierQuoting/HardCodedIdentifierQuoteDetector.php';

/*
 * Package SQL quotes identifiers through ConnectionInterface::quoteIdentifier(), never with a hard-coded backtick or
 * double quote (#338). Only the driver packages own a quoting rule. This lives in the monorepo suite because each
 * package is split into its own repository.
 */

/** @var list<string> The driver packages, which own their dialect's quoting rule */
const SQL_QUOTING_DRIVER_PACKAGES = ['database-mysql', 'database-pgsql', 'database-sqlite'];

/**
 * @return list<string>
 */
function packageSourceFilesOutsideDrivers(): array
{
    $root = dirname(__DIR__) . '/packages';
    $files = [];

    foreach (glob($root . '/*/src', GLOB_ONLYDIR) ?: [] as $source) {
        if (in_array(basename(dirname($source)), SQL_QUOTING_DRIVER_PACKAGES, true)) {
            continue;
        }

        foreach (new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        ) as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
    }

    sort($files);

    return $files;
}

/**
 * @param list<array{file: string, line: int, literal: string}> $violations
 * @return list<string>
 */
function describeHardCodedQuoting(
    array $violations,
): array {
    $root = dirname(__DIR__) . '/';

    return array_map(
        fn (array $violation): string => sprintf(
            '%s:%d quotes an identifier by hand in %s; use ConnectionInterface::quoteIdentifier()',
            str_replace($root, '', $violation['file']),
            $violation['line'],
            $violation['literal'],
        ),
        $violations,
    );
}

it('finds no hard-coded identifier quoting in package sources', function (): void {
    $files = packageSourceFilesOutsideDrivers();

    expect(count($files))->toBeGreaterThan(500)
        ->and(describeHardCodedQuoting(new HardCodedIdentifierQuoteDetector()->scan($files)))->toBeEmpty();
})->issue(338);

it('excludes the database driver packages', function (): void {
    $files = packageSourceFilesOutsideDrivers();

    expect(array_filter(
        $files,
        fn (string $file): bool => str_contains($file, '/packages/database-mysql/')
            || str_contains($file, '/packages/database-pgsql/')
            || str_contains($file, '/packages/database-sqlite/'),
    ))->toBeEmpty()
        ->and(array_filter($files, fn (string $file): bool => str_contains($file, '/packages/database/src/')))
        ->not->toBeEmpty();
})->issue(338);

it('flags backtick and double-quote delimiters in a violating fixture', function (): void {
    $violations = new HardCodedIdentifierQuoteDetector()->scanFile(
        __DIR__ . '/Fixtures/SqlIdentifierQuoting/violating.php',
    );

    expect(array_map(fn (array $violation): int => $violation['line'], $violations))
        ->toBe([7, 7, 8, 8, 9, 9, 11, 13, 13]);
})->issue(338);

it('flags delimiters inside interpolated double-quoted strings and heredocs', function (): void {
    $violations = new HardCodedIdentifierQuoteDetector()->scanFile(
        __DIR__ . '/Fixtures/SqlIdentifierQuoting/violating.php',
    );

    expect(array_column($violations, 'literal'))
        ->toContain('SELECT * FROM \"', "    SELECT `key` FROM settings\n");
})->issue(338);

it('ignores quote characters in comments and in statements that build no SQL', function (): void {
    expect(new HardCodedIdentifierQuoteDetector()->scanFile(__DIR__ . '/Fixtures/SqlIdentifierQuoting/clean.php'))
        ->toBeEmpty();
})->issue(338);
