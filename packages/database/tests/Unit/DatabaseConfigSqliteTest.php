<?php

declare(strict_types=1);

use Marko\Core\Path\ProjectPaths;
use Marko\Database\Config\DatabaseConfig;
use Marko\Database\Exceptions\ConfigurationException;

/**
 * A project base path unique to this process; the constructor reads config/database.php beneath it.
 */
function sqliteProjectBase(): string
{
    static $base = null;

    return $base ??= sys_get_temp_dir() . '/marko_sqlite_cfg_' . bin2hex(random_bytes(8));
}

/**
 * Build the config through the constructor (a real config/database.php) and through fromArray().
 *
 * @param array<string, mixed> $config
 * @return array{constructed: DatabaseConfig, fromArray: DatabaseConfig}
 */
function buildSqliteConfigBothWays(
    array $config,
): array {
    return [
        'constructed' => sqliteConfigFromFile($config),
        'fromArray' => DatabaseConfig::fromArray($config, sqliteProjectBase()),
    ];
}

/**
 * @param array<string, mixed> $config
 */
function sqliteConfigFromFile(
    array $config,
): DatabaseConfig {
    $base = sqliteProjectBase();
    mkdir($base . '/config', 0755, true);
    file_put_contents($base . '/config/database.php', '<?php return ' . var_export($config, true) . ';');

    try {
        return new DatabaseConfig(new ProjectPaths($base));
    } finally {
        unlink($base . '/config/database.php');
        rmdir($base . '/config');
        rmdir($base);
    }
}

describe('DatabaseConfig sqlite', function (): void {
    it('accepts a sqlite config with only driver and database keys', function (): void {
        $configs = buildSqliteConfigBothWays(['driver' => 'sqlite', 'database' => '/data/app.db']);

        foreach ($configs as $config) {
            expect($config->driver)->toBe('sqlite')
                ->and($config->database)->toBe('/data/app.db')
                ->and($config->host)->toBe('')
                ->and($config->port)->toBe(0)
                ->and($config->username)->toBe('')
                ->and($config->password)->toBe('');
        }
    });

    it('still requires connection credentials for mysql and pgsql', function (): void {
        foreach (['mysql', 'pgsql'] as $driver) {
            expect(fn () => DatabaseConfig::fromArray(['driver' => $driver, 'database' => 'app']))
                ->toThrow(ConfigurationException::class, "'host'");
        }
    });

    it('throws a configuration exception when a sqlite config has no database key', function (): void {
        expect(fn () => DatabaseConfig::fromArray(['driver' => 'sqlite'], sqliteProjectBase()))
            ->toThrow(ConfigurationException::class, "'database'")
            ->and(fn () => sqliteConfigFromFile(['driver' => 'sqlite']))
            ->toThrow(ConfigurationException::class, "'database'");
    });

    it(
        'resolves a relative sqlite database path against the project base path only',
        function (): void {
            $relative = buildSqliteConfigBothWays(['driver' => 'sqlite', 'database' => 'storage/app.db']);
            $absolute = buildSqliteConfigBothWays(['driver' => 'sqlite', 'database' => '/data/app.db']);
            $memory = buildSqliteConfigBothWays(['driver' => 'sqlite', 'database' => ':memory:']);
            $uri = buildSqliteConfigBothWays(['driver' => 'sqlite', 'database' => 'file:app.db?mode=ro']);

            foreach ($relative as $config) {
                expect($config->database)->toBe(sqliteProjectBase() . '/storage/app.db');
            }

            foreach ($absolute as $config) {
                expect($config->database)->toBe('/data/app.db');
            }

            foreach ($memory as $config) {
                expect($config->database)->toBe(':memory:');
            }

            foreach ($uri as $config) {
                expect($config->database)->toBe('file:app.db?mode=ro');
            }

            expect(fn () => DatabaseConfig::fromArray(['driver' => 'sqlite', 'database' => 'storage/app.db']))
                ->toThrow(ConfigurationException::class, 'base path');
        },
    );

    it(
        'reads sqlite pragma overrides and create_if_missing and defaults create_if_missing to true',
        function (): void {
            $configured = buildSqliteConfigBothWays([
                'driver' => 'sqlite',
                'database' => ':memory:',
                'sqlite' => ['pragmas' => ['busy_timeout' => 1000, 'journal_mode' => 'wal'], 'create_if_missing' => false],
            ]);
            $defaults = buildSqliteConfigBothWays(['driver' => 'sqlite', 'database' => ':memory:']);
            $other = DatabaseConfig::fromArray([
                'driver' => 'mysql',
                'host' => 'h',
                'port' => 1,
                'database' => 'd',
                'username' => 'u',
                'password' => 'p',
            ]);

            foreach ($configured as $config) {
                expect($config->sqlitePragmas)->toBe(['busy_timeout' => 1000, 'journal_mode' => 'wal'])
                    ->and($config->sqliteCreateIfMissing)->toBeFalse();
            }

            foreach ($defaults as $config) {
                expect($config->sqlitePragmas)->toBeEmpty()
                    ->and($config->sqliteCreateIfMissing)->toBeTrue();
            }

            expect($other->sqlitePragmas)->toBeEmpty();
        },
    );

    it('throws a configuration exception when the sqlite option or its pragmas are not arrays', function (): void {
        $base = ['driver' => 'sqlite', 'database' => ':memory:'];

        foreach ([
            ['sqlite' => 'wal'],
            ['sqlite' => ['pragmas' => 'wal']],
            ['sqlite' => ['create_if_missing' => 'yes']],
        ] as $invalid) {
            expect(fn () => DatabaseConfig::fromArray([...$base, ...$invalid], sqliteProjectBase()))
                ->toThrow(ConfigurationException::class)
                ->and(fn () => sqliteConfigFromFile([...$base, ...$invalid]))
                ->toThrow(ConfigurationException::class);
        }
    });
});
