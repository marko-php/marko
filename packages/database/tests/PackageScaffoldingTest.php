<?php

declare(strict_types=1);

describe('Package Scaffolding', function (): void {
    it('creates marko/database package with valid composer.json', function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';

        expect(file_exists($composerPath))->toBeTrue();

        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer)->not->toBeNull()
            ->and($composer['name'])->toBe('marko/database')
            ->and($composer['type'])->toBe('library')
            ->and($composer['require']['php'])->toBe('^8.5');
    });

    it('creates marko/database-mysql package with valid composer.json requiring marko/database', function (): void {
        $composerPath = dirname(__DIR__, 2) . '/database-mysql/composer.json';

        expect(file_exists($composerPath))->toBeTrue();

        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer)->not->toBeNull()
            ->and($composer['name'])->toBe('marko/database-mysql')
            ->and($composer['type'])->toBe('library')
            ->and($composer['require']['php'])->toBe('^8.5');
    });

    it('creates marko/database-pgsql package with valid composer.json requiring marko/database', function (): void {
        $composerPath = dirname(__DIR__, 2) . '/database-pgsql/composer.json';

        expect(file_exists($composerPath))->toBeTrue();

        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer)->not->toBeNull()
            ->and($composer['name'])->toBe('marko/database-pgsql')
            ->and($composer['type'])->toBe('library')
            ->and($composer['require']['php'])->toBe('^8.5');
    });

    it('creates module.php for database-mysql with connection binding', function (): void {
        $modulePath = dirname(__DIR__, 2) . '/database-mysql/module.php';

        expect(file_exists($modulePath))->toBeTrue();

        $module = require $modulePath;

        expect($module)->toBeArray()
            ->and($module['bindings'])->toBeArray()
            ->and($module['bindings'])->toHaveKey('Marko\\Database\\Connection\\ConnectionInterface');
    });

    it('creates module.php for database-pgsql with connection binding', function (): void {
        $modulePath = dirname(__DIR__, 2) . '/database-pgsql/module.php';

        expect(file_exists($modulePath))->toBeTrue();

        $module = require $modulePath;

        expect($module)->toBeArray()
            ->and($module['bindings'])->toBeArray()
            ->and($module['bindings'])->toHaveKey('Marko\\Database\\Connection\\ConnectionInterface');
    });

    it('creates proper directory structure for all three packages', function (): void {
        $packagesDir = dirname(__DIR__, 2);

        // marko/database directories
        expect(is_dir($packagesDir . '/database/src'))->toBeTrue()
            ->and(is_dir($packagesDir . '/database/tests'))->toBeTrue()
            // marko/database-mysql directories
            ->and(is_dir($packagesDir . '/database-mysql/src'))->toBeTrue()
            ->and(is_dir($packagesDir . '/database-mysql/tests'))->toBeTrue()
            // marko/database-pgsql directories
            ->and(is_dir($packagesDir . '/database-pgsql/src'))->toBeTrue()
            ->and(is_dir($packagesDir . '/database-pgsql/tests'))->toBeTrue();
    });

    it('configures PSR-4 namespaces correctly', function (): void {
        $packagesDir = dirname(__DIR__, 2);

        // marko/database namespace
        $databaseComposer = json_decode(file_get_contents($packagesDir . '/database/composer.json'), true);
        expect($databaseComposer['autoload']['psr-4'])->toHaveKey('Marko\\Database\\')
            ->and($databaseComposer['autoload']['psr-4']['Marko\\Database\\'])->toBe('src/');

        // marko/database-mysql namespace
        $mysqlComposer = json_decode(file_get_contents($packagesDir . '/database-mysql/composer.json'), true);
        expect($mysqlComposer['autoload']['psr-4'])->toHaveKey('Marko\\Database\\MySql\\')
            ->and($mysqlComposer['autoload']['psr-4']['Marko\\Database\\MySql\\'])->toBe('src/');

        // marko/database-pgsql namespace
        $pgsqlComposer = json_decode(file_get_contents($packagesDir . '/database-pgsql/composer.json'), true);
        expect($pgsqlComposer['autoload']['psr-4'])->toHaveKey('Marko\\Database\\PgSql\\')
            ->and($pgsqlComposer['autoload']['psr-4']['Marko\\Database\\PgSql\\'])->toBe('src/');
    });

    it(
        'creates slim README.md for marko/database per docs standards',
        function (): void {
            $readmePath = dirname(__DIR__) . '/README.md';

            expect(file_exists($readmePath))->toBeTrue();

            $content = file_get_contents($readmePath);

            expect($content)->toContain('marko/database')
                ->and($content)->toContain('composer require marko/database')
                ->and($content)->toContain('#[Column')
                ->and($content)->toContain('Repository');
        },
    );

    it('creates README.md for marko/database-mysql with installation and configuration', function (): void {
        $readmePath = dirname(__DIR__, 2) . '/database-mysql/README.md';

        expect(file_exists($readmePath))->toBeTrue();

        $content = file_get_contents($readmePath);

        // Installation instructions
        expect($content)->toContain('composer require marko/database-mysql')
            // Configuration
            ->and($content)->toContain('config/database.php')
            ->and($content)->toContain('DB_HOST')
            ->and($content)->toContain('DB_DATABASE')
            ->and($content)->toContain('DB_USERNAME')
            ->and($content)->toContain('DB_PASSWORD')
            // Driver-specific notes
            ->and($content)->toContain('MySQL')
            ->and($content)->toContain('MariaDB');
    });

    it('creates README.md for marko/database-pgsql with installation and configuration', function (): void {
        $readmePath = dirname(__DIR__, 2) . '/database-pgsql/README.md';

        expect(file_exists($readmePath))->toBeTrue();

        $content = file_get_contents($readmePath);

        // Installation instructions
        expect($content)->toContain('composer require marko/database-pgsql')
            // Configuration
            ->and($content)->toContain('config/database.php')
            ->and($content)->toContain('DB_HOST')
            ->and($content)->toContain('DB_DATABASE')
            ->and($content)->toContain('DB_USERNAME')
            ->and($content)->toContain('DB_PASSWORD')
            // Driver-specific notes
            ->and($content)->toContain('PostgreSQL');
    });
});
