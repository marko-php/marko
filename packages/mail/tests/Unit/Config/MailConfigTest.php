<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Mail\Config\MailConfig;
use Marko\Mail\Exception\MailException;

function createMailMockConfigRepository(
    array $configData = [],
): ConfigRepositoryInterface {
    /** @noinspection PhpMissingParentConstructorInspection */
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

test('MailConfig loads driver setting', function () {
    $config = new MailConfig(createMailMockConfigRepository([
        'mail.driver' => 'smtp',
    ]));

    expect($config->driver())->toBe('smtp');
});

test('MailConfig loads from address', function () {
    $config = new MailConfig(createMailMockConfigRepository([
        'mail.from.address' => 'hello@example.com',
    ]));

    expect($config->fromAddress())->toBe('hello@example.com');
});

test('MailConfig loads from name', function () {
    $config = new MailConfig(createMailMockConfigRepository([
        'mail.from.name' => 'Marko Application',
    ]));

    expect($config->fromName())->toBe('Marko Application');
});

test('MailConfig provides driver-specific config', function () {
    $smtpConfig = [
        'host' => 'localhost',
        'port' => 587,
        'encryption' => 'tls',
    ];
    $config = new MailConfig(createMailMockConfigRepository([
        'mail.smtp' => $smtpConfig,
    ]));

    expect($config->driverConfig('smtp'))->toBe($smtpConfig);
});

test('MailConfig throws on missing config file', function () {
    $config = new MailConfig(createMailMockConfigRepository([]));

    expect(fn () => $config->ensureConfigExists())
        ->toThrow(MailException::class, 'Mail configuration file not found.');
});

test('provides default configuration file', function () {
    $configPath = dirname(__DIR__, 3) . '/config/mail.php';

    expect(file_exists($configPath))->toBeTrue()
        ->and(is_array(require $configPath))->toBeTrue();
});
