<?php

declare(strict_types=1);

use Marko\Core\Path\ProjectPaths;
use Marko\Database\Config\DatabaseConfig;
use Marko\Database\Exceptions\ConfigurationException;

describe('DatabaseConfig::fromArray()', function (): void {
    it('builds a DatabaseConfig from an array with all required keys', function (): void {
        $config = DatabaseConfig::fromArray([
            'driver' => 'pgsql',
            'host' => 'localhost',
            'port' => 5432,
            'database' => 'mydb',
            'username' => 'admin',
            'password' => 'secret',
        ]);

        expect($config)->toBeInstanceOf(DatabaseConfig::class)
            ->and($config->driver)->toBe('pgsql')
            ->and($config->host)->toBe('localhost')
            ->and($config->port)->toBe(5432)
            ->and($config->database)->toBe('mydb')
            ->and($config->username)->toBe('admin')
            ->and($config->password)->toBe('secret');
    });

    it('throws ConfigurationException when a required key is missing from the array', function (): void {
        expect(fn () => DatabaseConfig::fromArray([
            'host' => 'localhost',
            'port' => 5432,
            'database' => 'mydb',
            'username' => 'admin',
            'password' => 'secret',
        ]))->toThrow(ConfigurationException::class, 'driver');
    });

    it('throws ConfigurationException when ssl_cert is provided without ssl_key', function (): void {
        expect(fn () => DatabaseConfig::fromArray([
            'driver' => 'pgsql',
            'host' => 'localhost',
            'port' => 5432,
            'database' => 'mydb',
            'username' => 'admin',
            'password' => 'secret',
            'ssl_cert' => '/path/to/cert.pem',
        ]))->toThrow(ConfigurationException::class, 'ssl_key');
    });

    it('throws ConfigurationException when ssl_key is provided without ssl_cert', function (): void {
        expect(fn () => DatabaseConfig::fromArray([
            'driver' => 'pgsql',
            'host' => 'localhost',
            'port' => 5432,
            'database' => 'mydb',
            'username' => 'admin',
            'password' => 'secret',
            'ssl_key' => '/path/to/key.pem',
        ]))->toThrow(ConfigurationException::class, 'ssl_cert');
    });

    it('populates SSL fields when provided', function (): void {
        $config = DatabaseConfig::fromArray([
            'driver' => 'pgsql',
            'host' => 'localhost',
            'port' => 5432,
            'database' => 'mydb',
            'username' => 'admin',
            'password' => 'secret',
            'sslmode' => 'require',
            'ssl_ca' => '/path/to/ca.pem',
            'ssl_verify_server_cert' => true,
            'ssl_cert' => '/path/to/cert.pem',
            'ssl_key' => '/path/to/key.pem',
        ]);

        expect($config->sslMode)->toBe('require')
            ->and($config->sslRootCert)->toBe('/path/to/ca.pem')
            ->and($config->sslVerifyServerCert)->toBeTrue()
            ->and($config->sslCert)->toBe('/path/to/cert.pem')
            ->and($config->sslKey)->toBe('/path/to/key.pem');
    });

    it(
        'produces a DatabaseConfig with identical property values to the file-loaded path given equivalent input',
        function (): void {
            $tempDir = sys_get_temp_dir() . '/marko_test_' . bin2hex(random_bytes(8));
            $configDir = $tempDir . '/config';
            mkdir($configDir, 0755, true);

            $configContent = <<<'PHP'
<?php

    return [
    'driver' => 'mysql',
    'host' => 'db.example.com',
    'port' => 3306,
    'database' => 'mydb',
    'username' => 'root',
    'password' => 'pass',
    'sslmode' => 'verify-full',
    'ssl_ca' => '/path/to/ca.pem',
    'ssl_cert' => '/path/to/cert.pem',
    'ssl_key' => '/path/to/key.pem',
];
PHP;
            file_put_contents($configDir . '/database.php', $configContent);

            try {
                $paths = new ProjectPaths($tempDir);
                $fileLoaded = new DatabaseConfig($paths);

                $fromArray = DatabaseConfig::fromArray([
                    'driver' => 'mysql',
                    'host' => 'db.example.com',
                    'port' => 3306,
                    'database' => 'mydb',
                    'username' => 'root',
                    'password' => 'pass',
                    'sslmode' => 'verify-full',
                    'ssl_ca' => '/path/to/ca.pem',
                    'ssl_cert' => '/path/to/cert.pem',
                    'ssl_key' => '/path/to/key.pem',
                ]);

                expect($fromArray->driver)->toBe($fileLoaded->driver)
                    ->and($fromArray->host)->toBe($fileLoaded->host)
                    ->and($fromArray->port)->toBe($fileLoaded->port)
                    ->and($fromArray->database)->toBe($fileLoaded->database)
                    ->and($fromArray->username)->toBe($fileLoaded->username)
                    ->and($fromArray->password)->toBe($fileLoaded->password)
                    ->and($fromArray->sslMode)->toBe($fileLoaded->sslMode)
                    ->and($fromArray->sslRootCert)->toBe($fileLoaded->sslRootCert)
                    ->and($fromArray->sslVerifyServerCert)->toBe($fileLoaded->sslVerifyServerCert)
                    ->and($fromArray->sslCert)->toBe($fileLoaded->sslCert)
                    ->and($fromArray->sslKey)->toBe($fileLoaded->sslKey);
            } finally {
                unlink($configDir . '/database.php');
                rmdir($configDir);
                rmdir($tempDir);
            }
        },
    );
});
