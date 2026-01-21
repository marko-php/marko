<?php

declare(strict_types=1);

describe('Package Scaffolding', function (): void {
    it('creates marko/database package with valid composer.json', function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';

        expect(file_exists($composerPath))->toBeTrue();

        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer)->not->toBeNull();
        expect($composer['name'])->toBe('marko/database');
        expect($composer['type'])->toBe('library');
        expect($composer['require']['php'])->toBe('^8.5');
        expect($composer['require']['marko/core'])->toBe('^0.1');
    });

    it('creates marko/database-mysql package with valid composer.json requiring marko/database', function (): void {
        $composerPath = dirname(__DIR__, 2) . '/database-mysql/composer.json';

        expect(file_exists($composerPath))->toBeTrue();

        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer)->not->toBeNull();
        expect($composer['name'])->toBe('marko/database-mysql');
        expect($composer['type'])->toBe('library');
        expect($composer['require']['php'])->toBe('^8.5');
        expect($composer['require']['marko/core'])->toBe('^0.1');
        expect($composer['require']['marko/database'])->toBe('^0.1');
    });

    it('creates marko/database-pgsql package with valid composer.json requiring marko/database', function (): void {
        $composerPath = dirname(__DIR__, 2) . '/database-pgsql/composer.json';

        expect(file_exists($composerPath))->toBeTrue();

        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer)->not->toBeNull();
        expect($composer['name'])->toBe('marko/database-pgsql');
        expect($composer['type'])->toBe('library');
        expect($composer['require']['php'])->toBe('^8.5');
        expect($composer['require']['marko/core'])->toBe('^0.1');
        expect($composer['require']['marko/database'])->toBe('^0.1');
    });

    it('creates module.php for database-mysql with connection binding', function (): void {
        $modulePath = dirname(__DIR__, 2) . '/database-mysql/module.php';

        expect(file_exists($modulePath))->toBeTrue();

        $module = require $modulePath;

        expect($module)->toBeArray();
        expect($module['enabled'])->toBeTrue();
        expect($module['bindings'])->toBeArray();
        expect($module['bindings'])->toHaveKey('Marko\\Database\\Connection\\ConnectionInterface');
    });

    it('creates module.php for database-pgsql with connection binding', function (): void {
        $modulePath = dirname(__DIR__, 2) . '/database-pgsql/module.php';

        expect(file_exists($modulePath))->toBeTrue();

        $module = require $modulePath;

        expect($module)->toBeArray();
        expect($module['enabled'])->toBeTrue();
        expect($module['bindings'])->toBeArray();
        expect($module['bindings'])->toHaveKey('Marko\\Database\\Connection\\ConnectionInterface');
    });

    it('creates proper directory structure for all three packages', function (): void {
        $packagesDir = dirname(__DIR__, 2);

        // marko/database directories
        expect(is_dir($packagesDir . '/database/src'))->toBeTrue();
        expect(is_dir($packagesDir . '/database/tests'))->toBeTrue();

        // marko/database-mysql directories
        expect(is_dir($packagesDir . '/database-mysql/src'))->toBeTrue();
        expect(is_dir($packagesDir . '/database-mysql/tests'))->toBeTrue();

        // marko/database-pgsql directories
        expect(is_dir($packagesDir . '/database-pgsql/src'))->toBeTrue();
        expect(is_dir($packagesDir . '/database-pgsql/tests'))->toBeTrue();
    });

    it('configures PSR-4 namespaces correctly', function (): void {
        $packagesDir = dirname(__DIR__, 2);

        // marko/database namespace
        $databaseComposer = json_decode(file_get_contents($packagesDir . '/database/composer.json'), true);
        expect($databaseComposer['autoload']['psr-4'])->toHaveKey('Marko\\Database\\');
        expect($databaseComposer['autoload']['psr-4']['Marko\\Database\\'])->toBe('src/');

        // marko/database-mysql namespace
        $mysqlComposer = json_decode(file_get_contents($packagesDir . '/database-mysql/composer.json'), true);
        expect($mysqlComposer['autoload']['psr-4'])->toHaveKey('Marko\\Database\\MySql\\');
        expect($mysqlComposer['autoload']['psr-4']['Marko\\Database\\MySql\\'])->toBe('src/');

        // marko/database-pgsql namespace
        $pgsqlComposer = json_decode(file_get_contents($packagesDir . '/database-pgsql/composer.json'), true);
        expect($pgsqlComposer['autoload']['psr-4'])->toHaveKey('Marko\\Database\\PgSql\\');
        expect($pgsqlComposer['autoload']['psr-4']['Marko\\Database\\PgSql\\'])->toBe('src/');
    });

    it(
        'creates README.md for marko/database explaining entity-driven schema and Data Mapper pattern',
        function (): void {
            $readmePath = dirname(__DIR__) . '/README.md';
    
            expect(file_exists($readmePath))->toBeTrue();
    
            $content = file_get_contents($readmePath);
    
            // Core concepts covered
        expect($content)->toContain('Entity-Driven Schema');
            expect($content)->toContain('Data Mapper');
            expect($content)->toContain('Type Inference');
    
            // Attributes overview
        expect($content)->toContain('#[Table]');
            expect($content)->toContain('#[Column]');
            expect($content)->toContain('#[Index]');
    
            // Post entity example with various attribute types
        expect($content)->toContain('class Post');
            expect($content)->toContain('primaryKey');
            expect($content)->toContain('autoIncrement');
            expect($content)->toContain('unique');
            expect($content)->toContain('nullable');
            expect($content)->toContain('default');
    
            // Repository pattern
        expect($content)->toContain('Repository');
    
            // CLI commands overview
        expect($content)->toContain('db:diff');
            expect($content)->toContain('db:migrate');
            expect($content)->toContain('db:rollback');
            expect($content)->toContain('db:status');
    
            // Framework comparison
        expect($content)->toContain('Laravel');
            expect($content)->toContain('Doctrine');
            expect($content)->toContain('Marko');
    
            // Single source of truth benefits
        expect($content)->toContain('single source of truth');
        }
    );

    it('creates README.md for marko/database-mysql with installation and configuration', function (): void {
        $readmePath = dirname(__DIR__, 2) . '/database-mysql/README.md';

        expect(file_exists($readmePath))->toBeTrue();

        $content = file_get_contents($readmePath);

        // Installation instructions
        expect($content)->toContain('composer require marko/database-mysql');

        // Configuration
        expect($content)->toContain('config/database.php');
        expect($content)->toContain('DB_HOST');
        expect($content)->toContain('DB_DATABASE');
        expect($content)->toContain('DB_USERNAME');
        expect($content)->toContain('DB_PASSWORD');

        // Driver-specific notes
        expect($content)->toContain('MySQL');
        expect($content)->toContain('MariaDB');
    });

    it('creates README.md for marko/database-pgsql with installation and configuration', function (): void {
        $readmePath = dirname(__DIR__, 2) . '/database-pgsql/README.md';

        expect(file_exists($readmePath))->toBeTrue();

        $content = file_get_contents($readmePath);

        // Installation instructions
        expect($content)->toContain('composer require marko/database-pgsql');

        // Configuration
        expect($content)->toContain('config/database.php');
        expect($content)->toContain('DB_HOST');
        expect($content)->toContain('DB_DATABASE');
        expect($content)->toContain('DB_USERNAME');
        expect($content)->toContain('DB_PASSWORD');

        // Driver-specific notes
        expect($content)->toContain('PostgreSQL');
    });
});
