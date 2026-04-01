<?php

declare(strict_types=1);

use Marko\Core\Path\ProjectPaths;
use Marko\Database\Config\DatabaseConfig;
use Marko\Database\Exceptions\ConfigurationException;

describe('DatabaseConfig', function (): void {
    it('uses getcwd as default base path when no path provided', function (): void {
        // Save current directory and change to temp directory with config
        $originalCwd = getcwd();
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
            chdir($tempDir);

            // Instantiate without any arguments - should use getcwd() via ProjectPaths
            $paths = new ProjectPaths();
            $config = new DatabaseConfig($paths);

            expect($config->driver)->toBe('mysql')
                ->and($config->host)->toBe('localhost')
                ->and($config->port)->toBe(3306)
                ->and($config->database)->toBe('test_db')
                ->and($config->username)->toBe('root')
                ->and($config->password)->toBe('secret');
        } finally {
            chdir($originalCwd);
            unlink($configDir . '/database.php');
            rmdir($configDir);
            rmdir($tempDir);
        }
    });

    it('accepts explicit base path to override default', function (): void {
        // Create a temporary config directory and file for testing
        $tempDir = sys_get_temp_dir() . '/marko_test_' . uniqid();
        $configDir = $tempDir . '/config';
        mkdir($configDir, 0755, true);

        $configContent = <<<'PHP'
<?php

return [
    'driver' => 'pgsql',
    'host' => 'custom-host',
    'port' => 5432,
    'database' => 'explicit_db',
    'username' => 'explicit_user',
    'password' => 'explicit_pass',
];
PHP;
        file_put_contents($configDir . '/database.php', $configContent);

        try {
            // Explicitly pass base path via ProjectPaths - should use that, not getcwd()
            $paths = new ProjectPaths($tempDir);
            $config = new DatabaseConfig($paths);

            expect($config->driver)->toBe('pgsql')
                ->and($config->host)->toBe('custom-host')
                ->and($config->port)->toBe(5432)
                ->and($config->database)->toBe('explicit_db')
                ->and($config->username)->toBe('explicit_user')
                ->and($config->password)->toBe('explicit_pass');
        } finally {
            // Cleanup
            unlink($configDir . '/database.php');
            rmdir($configDir);
            rmdir($tempDir);
        }
    });

    it('loads config from basePath/config/database.php', function (): void {
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
            $paths = new ProjectPaths($tempDir);
            $config = new DatabaseConfig($paths);

            expect($config->driver)->toBe('mysql')
                ->and($config->host)->toBe('localhost')
                ->and($config->port)->toBe(3306)
                ->and($config->database)->toBe('test_db')
                ->and($config->username)->toBe('root')
                ->and($config->password)->toBe('secret');
        } finally {
            // Cleanup
            unlink($configDir . '/database.php');
            rmdir($configDir);
            rmdir($tempDir);
        }
    });

    it('throws ConfigurationException when config file not found', function (): void {
        $tempDir = sys_get_temp_dir() . '/marko_test_' . uniqid();
        mkdir($tempDir, 0755, true);

        try {
            $paths = new ProjectPaths($tempDir);
            expect(fn () => new DatabaseConfig($paths))
                ->toThrow(ConfigurationException::class)
                ->and(fn () => new DatabaseConfig($paths))
                ->toThrow(ConfigurationException::class, 'not found');
        } finally {
            rmdir($tempDir);
        }
    });

    it('loads optional SSL config when present', function (): void {
        $tempDir = sys_get_temp_dir() . '/marko_test_' . uniqid();
        $configDir = $tempDir . '/config';
        mkdir($configDir, 0755, true);

        $configContent = <<<'PHP'
<?php

return [
    'driver' => 'pgsql',
    'host' => 'db.example.com',
    'port' => 5432,
    'database' => 'test_db',
    'username' => 'root',
    'password' => 'secret',
    'sslmode' => 'require',
    'ssl_ca' => '/path/to/ca.pem',
];
PHP;
        file_put_contents($configDir . '/database.php', $configContent);

        try {
            $paths = new ProjectPaths($tempDir);
            $config = new DatabaseConfig($paths);

            expect($config->sslMode)->toBe('require')
                ->and($config->sslRootCert)->toBe('/path/to/ca.pem')
                ->and($config->sslVerifyServerCert)->toBeFalse();
        } finally {
            unlink($configDir . '/database.php');
            rmdir($configDir);
            rmdir($tempDir);
        }
    });

    it('loads ssl_verify_server_cert when present', function (): void {
        $tempDir = sys_get_temp_dir() . '/marko_test_' . uniqid();
        $configDir = $tempDir . '/config';
        mkdir($configDir, 0755, true);

        $configContent = <<<'PHP'
<?php

return [
    'driver' => 'mysql',
    'host' => 'db.example.com',
    'port' => 3306,
    'database' => 'test_db',
    'username' => 'root',
    'password' => 'secret',
    'ssl_ca' => '/path/to/ca.pem',
    'ssl_verify_server_cert' => true,
];
PHP;
        file_put_contents($configDir . '/database.php', $configContent);

        try {
            $paths = new ProjectPaths($tempDir);
            $config = new DatabaseConfig($paths);

            expect($config->sslRootCert)->toBe('/path/to/ca.pem')
                ->and($config->sslVerifyServerCert)->toBeTrue();
        } finally {
            unlink($configDir . '/database.php');
            rmdir($configDir);
            rmdir($tempDir);
        }
    });

    it('defaults SSL config to null when not present', function (): void {
        $tempDir = sys_get_temp_dir() . '/marko_test_' . uniqid();
        $configDir = $tempDir . '/config';
        mkdir($configDir, 0755, true);

        $configContent = <<<'PHP'
<?php

return [
    'driver' => 'pgsql',
    'host' => 'localhost',
    'port' => 5432,
    'database' => 'test_db',
    'username' => 'root',
    'password' => 'secret',
];
PHP;
        file_put_contents($configDir . '/database.php', $configContent);

        try {
            $paths = new ProjectPaths($tempDir);
            $config = new DatabaseConfig($paths);

            expect($config->sslMode)->toBeNull()
                ->and($config->sslRootCert)->toBeNull()
                ->and($config->sslVerifyServerCert)->toBeFalse();
        } finally {
            unlink($configDir . '/database.php');
            rmdir($configDir);
            rmdir($tempDir);
        }
    });

    it('throws ConfigurationException when required keys missing', function (): void {
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
            $paths = new ProjectPaths($tempDir);
            expect(fn () => new DatabaseConfig($paths))
                ->toThrow(ConfigurationException::class)
                ->and(fn () => new DatabaseConfig($paths))
                ->toThrow(ConfigurationException::class, 'driver');
        } finally {
            unlink($configDir . '/database.php');
            rmdir($configDir);
            rmdir($tempDir);
        }
    });
});
