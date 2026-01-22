<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Mail\Config\MailConfig;
use Marko\Mail\Smtp\SmtpConfig;

function createSmtpMockConfigRepository(
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
