<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Log\Config\LogConfig;
use Marko\Log\Exceptions\InvalidLogLevelException;
use Marko\Log\LogLevel;

function createMockLogConfigRepository(
    array $configData = [],
): ConfigRepositoryInterface {
    return new readonly class ($configData) implements ConfigRepositoryInterface
    {
        public function __construct(
            private array $data,
        ) {}

        public function get(
            string $key,
            ?string $scope = null,
        ): mixed {
            if (!$this->has($key, $scope)) {
                throw new ConfigNotFoundException($key);
            }

            return $this->data[$key];
        }

        public function has(
            string $key,
            ?string $scope = null,
        ): bool {
            return isset($this->data[$key]);
        }

        public function getString(
            string $key,
            ?string $scope = null,
        ): string {
            return (string) $this->get($key, $scope);
        }

        public function getInt(
            string $key,
            ?string $scope = null,
        ): int {
            return (int) $this->get($key, $scope);
        }

        public function getBool(
            string $key,
            ?string $scope = null,
        ): bool {
            return (bool) $this->get($key, $scope);
        }

        public function getFloat(
            string $key,
            ?string $scope = null,
        ): float {
            return (float) $this->get($key, $scope);
        }

        public function getArray(
            string $key,
            ?string $scope = null,
        ): array {
            return (array) $this->get($key, $scope);
        }

        public function all(
            ?string $scope = null,
        ): array {
            return $this->data;
        }

        public function withScope(
            string $scope,
        ): ConfigRepositoryInterface {
            return $this;
        }
    };
}

it('reads driver from config without fallback', function () {
    $config = new LogConfig(createMockLogConfigRepository([
        'log.driver' => 'database',
    ]));

    expect($config->driver())->toBe('database');
});

it('throws when driver is not configured', function () {
    $config = new LogConfig(createMockLogConfigRepository());

    expect(fn () => $config->driver())
        ->toThrow(ConfigNotFoundException::class);
});

it('reads path from config without fallback', function () {
    $config = new LogConfig(createMockLogConfigRepository([
        'log.path' => '/var/log/app',
    ]));

    expect($config->path())->toBe('/var/log/app');
});

it('throws when path is not configured', function () {
    $config = new LogConfig(createMockLogConfigRepository());

    expect(fn () => $config->path())
        ->toThrow(ConfigNotFoundException::class);
});

it('reads level from config without fallback', function () {
    $config = new LogConfig(createMockLogConfigRepository([
        'log.level' => 'error',
    ]));

    expect($config->level())->toBe(LogLevel::Error);
});

it('throws when level is not configured', function () {
    $config = new LogConfig(createMockLogConfigRepository());

    expect(fn () => $config->level())
        ->toThrow(ConfigNotFoundException::class);
});

it('throws InvalidLogLevelException for invalid level', function () {
    $config = new LogConfig(createMockLogConfigRepository([
        'log.level' => 'invalid',
    ]));

    expect(fn () => $config->level())
        ->toThrow(InvalidLogLevelException::class);
});

it('reads channel from config without fallback', function () {
    $config = new LogConfig(createMockLogConfigRepository([
        'log.channel' => 'api',
    ]));

    expect($config->channel())->toBe('api');
});

it('throws when channel is not configured', function () {
    $config = new LogConfig(createMockLogConfigRepository());

    expect(fn () => $config->channel())
        ->toThrow(ConfigNotFoundException::class);
});

it('reads format from config without fallback', function () {
    $config = new LogConfig(createMockLogConfigRepository([
        'log.format' => '{level}: {message}',
    ]));

    expect($config->format())->toBe('{level}: {message}');
});

it('throws when format is not configured', function () {
    $config = new LogConfig(createMockLogConfigRepository());

    expect(fn () => $config->format())
        ->toThrow(ConfigNotFoundException::class);
});

it('reads date_format from config without fallback', function () {
    $config = new LogConfig(createMockLogConfigRepository([
        'log.date_format' => 'd/m/Y H:i:s',
    ]));

    expect($config->dateFormat())->toBe('d/m/Y H:i:s');
});

it('throws when date_format is not configured', function () {
    $config = new LogConfig(createMockLogConfigRepository());

    expect(fn () => $config->dateFormat())
        ->toThrow(ConfigNotFoundException::class);
});

it('reads max_files from config without fallback', function () {
    $config = new LogConfig(createMockLogConfigRepository([
        'log.max_files' => 14,
    ]));

    expect($config->maxFiles())->toBe(14);
});

it('throws when max_files is not configured', function () {
    $config = new LogConfig(createMockLogConfigRepository());

    expect(fn () => $config->maxFiles())
        ->toThrow(ConfigNotFoundException::class);
});

it('reads max_file_size from config without fallback', function () {
    $config = new LogConfig(createMockLogConfigRepository([
        'log.max_file_size' => 5 * 1024 * 1024,
    ]));

    expect($config->maxFileSize())->toBe(5 * 1024 * 1024);
});

it('throws when max_file_size is not configured', function () {
    $config = new LogConfig(createMockLogConfigRepository());

    expect(fn () => $config->maxFileSize())
        ->toThrow(ConfigNotFoundException::class);
});

it('config file contains all required keys with defaults', function () {
    $configPath = dirname(__DIR__, 3) . '/config/log.php';
    $config = require $configPath;

    expect($config)->toBeArray()
        ->and($config)->toHaveKey('driver')
        ->and($config)->toHaveKey('path')
        ->and($config)->toHaveKey('level')
        ->and($config)->toHaveKey('channel')
        ->and($config)->toHaveKey('format')
        ->and($config)->toHaveKey('date_format')
        ->and($config)->toHaveKey('max_files')
        ->and($config)->toHaveKey('max_file_size');
});
