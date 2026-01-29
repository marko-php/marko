<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Mail\Config\MailConfig;
use Marko\Mail\Exception\MailException;

function createMailMockConfigRepository(
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
    $config = new MailConfig(createMailMockConfigRepository());

    expect(fn () => $config->ensureConfigExists())
        ->toThrow(MailException::class, 'Mail configuration file not found.');
});

test('provides default configuration file', function () {
    $configPath = dirname(__DIR__, 3) . '/config/mail.php';

    expect(file_exists($configPath))->toBeTrue()
        ->and(is_array(require $configPath))->toBeTrue();
});

test('it reads driver from config without fallback', function () {
    $config = new MailConfig(createMailMockConfigRepository([
        'mail.driver' => 'sendmail',
    ]));

    expect($config->driver())->toBe('sendmail');
});

test('it reads from address from config without fallback', function () {
    $config = new MailConfig(createMailMockConfigRepository([
        'mail.from.address' => 'test@example.org',
    ]));

    expect($config->fromAddress())->toBe('test@example.org');
});

test('it reads from name from config without fallback', function () {
    $config = new MailConfig(createMailMockConfigRepository([
        'mail.from.name' => 'Test Sender',
    ]));

    expect($config->fromName())->toBe('Test Sender');
});

test('config file contains all required keys with defaults', function () {
    $configPath = dirname(__DIR__, 3) . '/config/mail.php';
    $config = require $configPath;

    expect($config)->toBeArray()
        ->toHaveKey('driver')
        ->toHaveKey('from');

    expect($config['from'])->toBeArray()
        ->toHaveKey('address')
        ->toHaveKey('name');

    // Verify defaults are set
    expect($config['driver'])->toBe('smtp')
        ->and($config['from']['address'])->toBe('hello@example.com')
        ->and($config['from']['name'])->toBe('Marko Application');
});
