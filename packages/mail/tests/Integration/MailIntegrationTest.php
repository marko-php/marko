<?php

declare(strict_types=1);

namespace Marko\Mail\Tests\Integration;

use Closure;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Mail\Config\MailConfig;
use Marko\Mail\Contracts\MailerInterface;
use Marko\Mail\Exception\MailException;
use Marko\Mail\Smtp\SmtpConfig;
use Marko\Mail\Smtp\SmtpMailer;
use Marko\Mail\Smtp\SmtpMailerFactory;
use Marko\Mail\Smtp\SocketInterface;

/**
 * Create a stub config repository for integration testing.
 *
 * @param array<string, mixed> $values
 */
function createIntegrationConfigRepository(
    array $values = [],
): ConfigRepositoryInterface {
    return new readonly class ($values) implements ConfigRepositoryInterface
    {
        public function __construct(
            private array $values,
        ) {}

        public function get(
            string $key,
            ?string $scope = null,
        ): mixed {
            if (!$this->has($key, $scope)) {
                throw new ConfigNotFoundException($key);
            }

            return $this->values[$key];
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

        public function has(
            string $key,
            ?string $scope = null,
        ): bool {
            return isset($this->values[$key]);
        }

        public function all(
            ?string $scope = null,
        ): array {
            return $this->values;
        }

        public function withScope(
            string $scope,
        ): ConfigRepositoryInterface {
            return $this;
        }
    };
}

/**
 * Create a stub socket for integration testing.
 */
function createIntegrationSocket(): SocketInterface
{
    return new class () implements SocketInterface
    {
        public private(set) bool $connected = false;

        public function connect(
            string $host,
            int $port,
            ?string $encryption = null,
            int $timeout = 30,
        ): void {
            $this->connected = true;
        }

        public function read(): string
        {
            return "220 smtp.example.com ESMTP\r\n";
        }

        public function write(string $data): void {}

        public function enableTls(): bool
        {
            return true;
        }

        public function close(): void
        {
            $this->connected = false;
        }
    };
}

test('MailConfig loads from config file', function (): void {
    $configRepo = createIntegrationConfigRepository([
        'mail' => true, // Indicates config exists
        'mail.driver' => 'smtp',
        'mail.from.address' => 'noreply@example.com',
        'mail.from.name' => 'Test Application',
        'mail.smtp' => [
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'user@example.com',
            'password' => 'secret',
            'timeout' => 60,
        ],
    ]);

    $mailConfig = new MailConfig($configRepo);

    // Should not throw when config exists
    $mailConfig->ensureConfigExists();

    expect($mailConfig->driver())->toBe('smtp')
        ->and($mailConfig->fromAddress())->toBe('noreply@example.com')
        ->and($mailConfig->fromName())->toBe('Test Application')
        ->and($mailConfig->driverConfig('smtp'))->toBe([
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'user@example.com',
            'password' => 'secret',
            'timeout' => 60,
        ]);
});

test('SmtpConfig extracts SMTP settings from MailConfig', function (): void {
    $configRepo = createIntegrationConfigRepository([
        'mail' => true,
        'mail.smtp' => [
            'host' => 'mail.test.com',
            'port' => 465,
            'encryption' => 'ssl',
            'username' => 'smtp-user',
            'password' => 'smtp-pass',
            'timeout' => 45,
            'auth_mode' => 'plain',
        ],
    ]);

    $mailConfig = new MailConfig($configRepo);
    $smtpConfig = new SmtpConfig($mailConfig);

    expect($smtpConfig->host())->toBe('mail.test.com')
        ->and($smtpConfig->port())->toBe(465)
        ->and($smtpConfig->encryption())->toBe('ssl')
        ->and($smtpConfig->username())->toBe('smtp-user')
        ->and($smtpConfig->password())->toBe('smtp-pass')
        ->and($smtpConfig->timeout())->toBe(45)
        ->and($smtpConfig->authMode())->toBe('plain');
});

test('SmtpMailerFactory creates configured mailer', function (): void {
    $configRepo = createIntegrationConfigRepository([
        'mail' => true,
        'mail.driver' => 'smtp',
        'mail.smtp' => [
            'host' => 'smtp.factory.test',
            'port' => 587,
            'encryption' => 'tls',
        ],
    ]);

    $mailConfig = new MailConfig($configRepo);
    $smtpConfig = new SmtpConfig($mailConfig);
    $socket = createIntegrationSocket();

    $factory = new SmtpMailerFactory($smtpConfig, $socket);
    $mailer = $factory->create();

    expect($mailer)->toBeInstanceOf(MailerInterface::class)
        ->and($mailer)->toBeInstanceOf(SmtpMailer::class);
});

test('module bindings resolve correctly', function (): void {
    // Test mail-smtp module
    $smtpModulePath = dirname(__DIR__, 3) . '/mail-smtp/module.php';
    expect(file_exists($smtpModulePath))->toBeTrue();

    $smtpModule = require $smtpModulePath;

    expect($smtpModule)->toBeArray()
        ->and($smtpModule)->toHaveKey('bindings')
        ->and($smtpModule['bindings'])->toHaveKey(MailerInterface::class)
        ->and($smtpModule['bindings'][MailerInterface::class])->toBeInstanceOf(Closure::class);
});

test('missing driver throws MailException', function (): void {
    // When no mail config exists, ensureConfigExists should throw
    $configRepo = createIntegrationConfigRepository();

    $mailConfig = new MailConfig($configRepo);

    expect(fn () => $mailConfig->ensureConfigExists())
        ->toThrow(MailException::class, 'Mail configuration file not found.');
});

test('MailException noDriverInstalled provides helpful message', function (): void {
    $exception = MailException::noDriverInstalled();

    expect($exception->getMessage())->toBe('No mail driver installed.')
        ->and($exception->getContext())->toBe('Attempted to resolve MailerInterface but no implementation is bound.')
        ->and($exception->getSuggestion())->toBe('Install a mail driver package: composer require marko/mail-smtp');
});
