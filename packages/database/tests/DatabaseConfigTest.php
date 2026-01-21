<?php

declare(strict_types=1);

use Marko\Database\Config\DatabaseConfig;
use Marko\Database\Exceptions\ConfigurationException;

describe('DatabaseConfig', function (): void {
    it('creates DatabaseConfig that reads from config/database.php', function (): void {
        // Create a temporary config directory and file for testing
        $tempDir = sys_get_temp_dir() . '/marko_test_' . uniqid();
        $configDir = $tempDir . '/config';
        mkdir($configDir, 0755, true);

        $configContent = <<<'PHP'
<?php

return [
    'driver' => 'mysql',
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'test_db',
    'username' => 'root',
    'password' => 'secret',
];
PHP;
        file_put_contents($configDir . '/database.php', $configContent);

        try {
            $config = new DatabaseConfig($tempDir);

            expect($config->driver)->toBe('mysql');
            expect($config->host)->toBe('localhost');
            expect($config->port)->toBe(3306);
            expect($config->database)->toBe('test_db');
            expect($config->username)->toBe('root');
            expect($config->password)->toBe('secret');
        } finally {
            // Cleanup
            unlink($configDir . '/database.php');
            rmdir($configDir);
            rmdir($tempDir);
        }
    });

    it('throws ConfigurationException when config file is missing', function (): void {
        $tempDir = sys_get_temp_dir() . '/marko_test_' . uniqid();
        mkdir($tempDir, 0755, true);

        try {
            expect(fn () => new DatabaseConfig($tempDir))
                ->toThrow(ConfigurationException::class)
                ->and(fn () => new DatabaseConfig($tempDir))
                ->toThrow(ConfigurationException::class, 'not found');
        } finally {
            rmdir($tempDir);
        }
    });

    it('throws ConfigurationException when required keys are missing', function (): void {
        $tempDir = sys_get_temp_dir() . '/marko_test_' . uniqid();
        $configDir = $tempDir . '/config';
        mkdir($configDir, 0755, true);

        // Missing 'driver' key
        $configContent = <<<'PHP'
<?php

return [
    'host' => 'localhost',
    'database' => 'test_db',
];
PHP;
        file_put_contents($configDir . '/database.php', $configContent);

        try {
            expect(fn () => new DatabaseConfig($tempDir))
                ->toThrow(ConfigurationException::class)
                ->and(fn () => new DatabaseConfig($tempDir))
                ->toThrow(ConfigurationException::class, 'driver');
        } finally {
            unlink($configDir . '/database.php');
            rmdir($configDir);
            rmdir($tempDir);
        }
    });
});
