<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Log\Config\LogConfig;
use Marko\Log\Exceptions\InvalidLogLevelException;
use Marko\Log\LogLevel;

function createMockLogConfigRepository(
    array $configData = [],
): ConfigRepositoryInterface {
    return new class ($configData) implements ConfigRepositoryInterface
    {
        public function __construct(
            private readonly array $data,
        ) {}

        public function get(
            string $key,
            mixed $default = null,
            ?string $scope = null,
        ): mixed {
            return $this->data[$key] ?? $default;
        }

        public function has(
            string $key,
            ?string $scope = null,
        ): bool {
            return isset($this->data[$key]);
        }

        public function getString(
            string $key,
            ?string $default = null,
            ?string $scope = null,
        ): string {
            return (string) ($this->data[$key] ?? $default);
        }

        public function getInt(
            string $key,
            ?int $default = null,
            ?string $scope = null,
        ): int {
            return (int) ($this->data[$key] ?? $default);
        }

        public function getBool(
            string $key,
            ?bool $default = null,
            ?string $scope = null,
        ): bool {
            return (bool) ($this->data[$key] ?? $default);
        }

        public function getFloat(
            string $key,
            ?float $default = null,
            ?string $scope = null,
        ): float {
            return (float) ($this->data[$key] ?? $default);
        }

        public function getArray(
            string $key,
            ?array $default = null,
            ?string $scope = null,
        ): array {
            return (array) ($this->data[$key] ?? $default ?? []);
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

it('returns configured driver', function () {
    $config = new LogConfig(createMockLogConfigRepository([
        'log.driver' => 'database',
    ]));

    expect($config->driver())->toBe('database');
});

it('returns file as default driver', function () {
    $config = new LogConfig(createMockLogConfigRepository());

    expect($config->driver())->toBe('file');
});

it('returns configured path', function () {
    $config = new LogConfig(createMockLogConfigRepository([
        'log.path' => '/var/log/app',
    ]));

    expect($config->path())->toBe('/var/log/app');
});

it('returns storage/logs as default path', function () {
    $config = new LogConfig(createMockLogConfigRepository());

    expect($config->path())->toBe('storage/logs');
});

it('returns configured level as LogLevel enum', function () {
    $config = new LogConfig(createMockLogConfigRepository([
        'log.level' => 'error',
    ]));

    expect($config->level())->toBe(LogLevel::Error);
});

it('returns debug as default level', function () {
    $config = new LogConfig(createMockLogConfigRepository());

    expect($config->level())->toBe(LogLevel::Debug);
});

it('throws InvalidLogLevelException for invalid level', function () {
    $config = new LogConfig(createMockLogConfigRepository([
        'log.level' => 'invalid',
    ]));

    expect(fn () => $config->level())
        ->toThrow(InvalidLogLevelException::class);
});

it('returns configured channel', function () {
    $config = new LogConfig(createMockLogConfigRepository([
        'log.channel' => 'api',
    ]));

    expect($config->channel())->toBe('api');
});

it('returns app as default channel', function () {
    $config = new LogConfig(createMockLogConfigRepository());

    expect($config->channel())->toBe('app');
});

it('returns configured format', function () {
    $config = new LogConfig(createMockLogConfigRepository([
        'log.format' => '{level}: {message}',
    ]));

    expect($config->format())->toBe('{level}: {message}');
});

it('returns default format string', function () {
    $config = new LogConfig(createMockLogConfigRepository());

    expect($config->format())->toContain('{datetime}')
        ->and($config->format())->toContain('{channel}')
        ->and($config->format())->toContain('{level}')
        ->and($config->format())->toContain('{message}');
});

it('returns configured date format', function () {
    $config = new LogConfig(createMockLogConfigRepository([
        'log.date_format' => 'd/m/Y H:i:s',
    ]));

    expect($config->dateFormat())->toBe('d/m/Y H:i:s');
});

it('returns Y-m-d H:i:s as default date format', function () {
    $config = new LogConfig(createMockLogConfigRepository());

    expect($config->dateFormat())->toBe('Y-m-d H:i:s');
});

it('returns configured max files', function () {
    $config = new LogConfig(createMockLogConfigRepository([
        'log.max_files' => 14,
    ]));

    expect($config->maxFiles())->toBe(14);
});

it('returns 30 as default max files', function () {
    $config = new LogConfig(createMockLogConfigRepository());

    expect($config->maxFiles())->toBe(30);
});

it('returns configured max file size', function () {
    $config = new LogConfig(createMockLogConfigRepository([
        'log.max_file_size' => 5 * 1024 * 1024,
    ]));

    expect($config->maxFileSize())->toBe(5 * 1024 * 1024);
});

it('returns 10MB as default max file size', function () {
    $config = new LogConfig(createMockLogConfigRepository());

    expect($config->maxFileSize())->toBe(10 * 1024 * 1024);
});
