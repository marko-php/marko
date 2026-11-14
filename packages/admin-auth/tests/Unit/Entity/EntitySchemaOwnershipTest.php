<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Tests\Unit\Entity;

use Marko\Database\Attributes\Table as TableAttribute;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\SchemaBuilder;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\Table;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

/*
 * db:migrate creates a package's tables only from its entities (vendor/<vendor>/<package>/src/Entity); the Migrator
 * never reads a package's own database/migrations directory. A table admin-auth reads or writes with raw SQL but no
 * entity declares is never created, and the first query against it fails on a fresh install (#336).
 */

/**
 * The tables the admin-auth entities declare, built as db:migrate builds them.
 *
 * @return array<string, Table>
 */
function adminAuthEntityTables(): array
{
    $metadataFactory = new EntityMetadataFactory();
    $schemaBuilder = new SchemaBuilder();
    $tables = [];

    foreach (glob(dirname(__DIR__, 3) . '/src/Entity/*.php') as $file) {
        $class = 'Marko\\AdminAuth\\Entity\\' . basename($file, '.php');
        $reflection = new ReflectionClass($class);

        if (
            $reflection->isInterface()
            || $reflection->getAttributes(TableAttribute::class) === []
        ) {
            continue;
        }

        $table = $schemaBuilder->build($metadataFactory->parse($class));
        $tables[$table->name] = $table;
    }

    return $tables;
}

/**
 * The source of every admin-auth class outside src/Entity.
 *
 * @return array<string, string> Source keyed by path
 */
function adminAuthSqlSources(): array
{
    $sources = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 3) . '/src'));

    /** @var SplFileInfo $file */
    foreach ($files as $file) {
        if (
            $file->getExtension() !== 'php'
            || str_contains($file->getPathname(), '/src/Entity/')
        ) {
            continue;
        }

        $sources[$file->getPathname()] = (string) file_get_contents($file->getPathname());
    }

    return $sources;
}

/**
 * Every match of a pattern's first group across the admin-auth sources.
 *
 * @return list<string>
 */
function adminAuthSqlMatches(
    string $pattern,
): array {
    $names = [];

    foreach (adminAuthSqlSources() as $source) {
        preg_match_all($pattern, $source, $matches);
        $names = [...$names, ...$matches[1]];
    }

    return array_values(array_unique($names));
}

it('owns every table named in the repositories raw SQL with an entity', function (): void {
    // A table is named bare or through the repositories' quoting helper: FROM {$this->table('roles')}
    $tables = adminAuthSqlMatches('/\b(?:FROM|JOIN|INTO|UPDATE)\s+(?:\{\$this->table\(\')?([a-z_][a-z0-9_]*)\b/');

    expect($tables)->toContain('admin_user_roles', 'role_permissions', 'roles', 'permissions')
        ->and(array_values(array_diff($tables, array_keys(adminAuthEntityTables()))))->toBe([]);
});

it('owns every identifier the repositories quote with an entity table or column', function (): void {
    $known = [];

    foreach (adminAuthEntityTables() as $table) {
        $known[] = $table->name;

        foreach ($table->columns as $column) {
            /** @var Column $column */
            $known[] = $column->name;
        }
    }

    $quoted = adminAuthSqlMatches("/quoteIdentifier\\(\\s*'([^']+)'\\s*\\)/");

    expect($quoted)->toContain('role_permissions', 'permissions')
        ->and(array_values(array_diff($quoted, $known)))->toBe([]);
});

it('ships no hand-written migrations, which db:migrate would never run', function (): void {
    expect(is_dir(dirname(__DIR__, 3) . '/database/migrations'))->toBeFalse();
});
