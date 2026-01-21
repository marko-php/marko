<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Feature;

use Marko\Database\Config\DatabaseConfig;
use Marko\Database\Exceptions\ConfigurationException;
use Marko\Database\Exceptions\DatabaseException;

describe('Driver Error Handling', function (): void {
    it('throws loud errors when no driver installed', function (): void {
        // DatabaseException should provide helpful error messages
        $exception = DatabaseException::noDriverInstalled('sqlite');

        expect($exception)->toBeInstanceOf(DatabaseException::class);
        expect($exception->getMessage())->toContain('sqlite');
        expect($exception->getMessage())->toContain('not installed');
        expect($exception->getSuggestion())->toContain('composer require');
    });

    it('throws ConfigurationException when config file not found', function (): void {
        $exception = ConfigurationException::configFileNotFound('/path/to/missing/config.php');

        expect($exception)->toBeInstanceOf(ConfigurationException::class);
        expect($exception->getMessage())->toContain('not found');
        expect($exception->getMessage())->toContain('/path/to/missing/config.php');
    });

    it('throws ConfigurationException when required config key missing', function (): void {
        $exception = ConfigurationException::missingRequiredKey('host');

        expect($exception)->toBeInstanceOf(ConfigurationException::class);
        expect($exception->getMessage())->toContain('host');
        expect($exception->getMessage())->toContain('missing');
    });

    it('validates database configuration from file', function (): void {
        // Create a temporary config file
        $tempDir = sys_get_temp_dir() . '/marko_config_test_' . uniqid();
        mkdir($tempDir . '/config', 0777, true);

        file_put_contents($tempDir . '/config/database.php', <<<'PHP'
<?php
return [
    'driver' => 'mysql',
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'test',
    'username' => 'root',
    'password' => '',
];
PHP);

        $config = new DatabaseConfig($tempDir);

        expect($config->driver)->toBe('mysql');
        expect($config->host)->toBe('localhost');
        expect($config->port)->toBe(3306);
        expect($config->database)->toBe('test');

        // Cleanup
        unlink($tempDir . '/config/database.php');
        rmdir($tempDir . '/config');
        rmdir($tempDir);
    });

    it('throws when config file not found', function (): void {
        $tempDir = sys_get_temp_dir() . '/marko_nonexistent_' . uniqid();
        mkdir($tempDir);

        expect(fn () => new DatabaseConfig($tempDir))
            ->toThrow(ConfigurationException::class, 'not found');

        rmdir($tempDir);
    });

    it('throws when required config key missing', function (): void {
        $tempDir = sys_get_temp_dir() . '/marko_config_test_' . uniqid();
        mkdir($tempDir . '/config', 0777, true);

        // Missing 'password' key
        file_put_contents($tempDir . '/config/database.php', <<<'PHP'
<?php
return [
    'driver' => 'mysql',
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'test',
    'username' => 'root',
    // missing password
];
PHP);

        expect(fn () => new DatabaseConfig($tempDir))
            ->toThrow(ConfigurationException::class, 'password');

        // Cleanup
        unlink($tempDir . '/config/database.php');
        rmdir($tempDir . '/config');
        rmdir($tempDir);
    });

    it('provides helpful error context for missing driver', function (): void {
        $exception = DatabaseException::noDriverInstalled('pgsql');

        expect($exception)->toBeInstanceOf(DatabaseException::class);
        expect($exception->getMessage())->toContain('pgsql');
        expect($exception->getSuggestion())->toContain('marko/database-pgsql');
    });

    it('provides helpful suggestion for known drivers', function (): void {
        // MySQL driver
        $mysqlException = DatabaseException::noDriverInstalled('mysql');
        expect($mysqlException->getSuggestion())->toContain('marko/database-mysql');

        // PostgreSQL driver
        $pgsqlException = DatabaseException::noDriverInstalled('pgsql');
        expect($pgsqlException->getSuggestion())->toContain('marko/database-pgsql');
    });
});
