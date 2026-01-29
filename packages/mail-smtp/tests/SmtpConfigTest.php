<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Mail\Config\MailConfig;
use Marko\Mail\Smtp\SmtpConfig;

function createSmtpMockConfigRepository(
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

it('extracts host from mail config', function (): void {
    $configRepo = createSmtpMockConfigRepository([
        'mail.smtp' => ['host' => 'smtp.example.com'],
    ]);

    $mailConfig = new MailConfig($configRepo);
    $smtpConfig = new SmtpConfig($mailConfig);

    expect($smtpConfig->host())->toBe('smtp.example.com');
});

it('extracts port from mail config', function (): void {
    $configRepo = createSmtpMockConfigRepository([
        'mail.smtp' => ['port' => 465],
    ]);

    $mailConfig = new MailConfig($configRepo);
    $smtpConfig = new SmtpConfig($mailConfig);

    expect($smtpConfig->port())->toBe(465);
});

it('extracts encryption setting', function (): void {
    $configRepo = createSmtpMockConfigRepository([
        'mail.smtp' => ['encryption' => 'ssl'],
    ]);

    $mailConfig = new MailConfig($configRepo);
    $smtpConfig = new SmtpConfig($mailConfig);

    expect($smtpConfig->encryption())->toBe('ssl');
});

it('extracts username and password', function (): void {
    $configRepo = createSmtpMockConfigRepository([
        'mail.smtp' => [
            'username' => 'user@example.com',
            'password' => 'secret123',
        ],
    ]);

    $mailConfig = new MailConfig($configRepo);
    $smtpConfig = new SmtpConfig($mailConfig);

    expect($smtpConfig->username())->toBe('user@example.com')
        ->and($smtpConfig->password())->toBe('secret123');
});

it('extracts timeout setting', function (): void {
    $configRepo = createSmtpMockConfigRepository([
        'mail.smtp' => ['timeout' => 60],
    ]);

    $mailConfig = new MailConfig($configRepo);
    $smtpConfig = new SmtpConfig($mailConfig);

    expect($smtpConfig->timeout())->toBe(60);
});

it('extracts auth_mode setting', function (): void {
    $configRepo = createSmtpMockConfigRepository([
        'mail.smtp' => ['auth_mode' => 'plain'],
    ]);

    $mailConfig = new MailConfig($configRepo);
    $smtpConfig = new SmtpConfig($mailConfig);

    expect($smtpConfig->authMode())->toBe('plain');
});

it('provides default values for optional settings', function (): void {
    $configRepo = createSmtpMockConfigRepository([
        'mail.smtp' => [],
    ]);

    $mailConfig = new MailConfig($configRepo);
    $smtpConfig = new SmtpConfig($mailConfig);

    expect($smtpConfig->host())->toBe('localhost')
        ->and($smtpConfig->port())->toBe(587)
        ->and($smtpConfig->encryption())->toBe('tls')
        ->and($smtpConfig->username())->toBeNull()
        ->and($smtpConfig->password())->toBeNull()
        ->and($smtpConfig->timeout())->toBe(30)
        ->and($smtpConfig->authMode())->toBe('login');
});
