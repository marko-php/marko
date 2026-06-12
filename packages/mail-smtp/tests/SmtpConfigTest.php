<?php

declare(strict_types=1);

use Marko\Mail\Config\MailConfig;
use Marko\Mail\Exception\MailException;
use Marko\Mail\Smtp\SmtpConfig;
use Marko\Testing\Fake\FakeConfigRepository;

it('extracts host from mail config', function (): void {
    $configRepo = new FakeConfigRepository([
        'mail.smtp' => ['host' => 'smtp.example.com', 'port' => 587, 'encryption' => 'tls', 'timeout' => 30, 'auth_mode' => 'login'],
    ]);

    $mailConfig = new MailConfig($configRepo);
    $smtpConfig = new SmtpConfig($mailConfig);

    expect($smtpConfig->host())->toBe('smtp.example.com');
});

it('extracts port from mail config', function (): void {
    $configRepo = new FakeConfigRepository([
        'mail.smtp' => ['host' => 'localhost', 'port' => 465, 'encryption' => 'tls', 'timeout' => 30, 'auth_mode' => 'login'],
    ]);

    $mailConfig = new MailConfig($configRepo);
    $smtpConfig = new SmtpConfig($mailConfig);

    expect($smtpConfig->port())->toBe(465);
});

it('extracts encryption setting', function (): void {
    $configRepo = new FakeConfigRepository([
        'mail.smtp' => ['host' => 'localhost', 'port' => 587, 'encryption' => 'ssl', 'timeout' => 30, 'auth_mode' => 'login'],
    ]);

    $mailConfig = new MailConfig($configRepo);
    $smtpConfig = new SmtpConfig($mailConfig);

    expect($smtpConfig->encryption())->toBe('ssl');
});

it('extracts username and password', function (): void {
    $configRepo = new FakeConfigRepository([
        'mail.smtp' => [
            'host' => 'localhost',
            'port' => 587,
            'encryption' => 'tls',
            'timeout' => 30,
            'auth_mode' => 'login',
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
    $configRepo = new FakeConfigRepository([
        'mail.smtp' => ['host' => 'localhost', 'port' => 587, 'encryption' => 'tls', 'timeout' => 60, 'auth_mode' => 'login'],
    ]);

    $mailConfig = new MailConfig($configRepo);
    $smtpConfig = new SmtpConfig($mailConfig);

    expect($smtpConfig->timeout())->toBe(60);
});

it('extracts auth_mode setting', function (): void {
    $configRepo = new FakeConfigRepository([
        'mail.smtp' => ['host' => 'localhost', 'port' => 587, 'encryption' => 'tls', 'timeout' => 30, 'auth_mode' => 'plain'],
    ]);

    $mailConfig = new MailConfig($configRepo);
    $smtpConfig = new SmtpConfig($mailConfig);

    expect($smtpConfig->authMode())->toBe('plain');
});

it('returns null username and password when absent from config', function (): void {
    $configRepo = new FakeConfigRepository([
        'mail.smtp' => ['host' => 'localhost', 'port' => 587, 'encryption' => 'tls', 'timeout' => 30, 'auth_mode' => 'login'],
    ]);

    $mailConfig = new MailConfig($configRepo);
    $smtpConfig = new SmtpConfig($mailConfig);

    expect($smtpConfig->username())->toBeNull()
        ->and($smtpConfig->password())->toBeNull();
});

it(
    'throws a loud exception (no hardcoded fallback) when a required SmtpConfig key (host/port/encryption/timeout/auth_mode) is missing',
    function (string $missingKey): void {
        $requiredKeys = ['host' => 'localhost', 'port' => 587, 'encryption' => 'tls', 'timeout' => 30, 'auth_mode' => 'login'];
        unset($requiredKeys[$missingKey]);

        $configRepo = new FakeConfigRepository(['mail.smtp' => $requiredKeys]);
        $mailConfig = new MailConfig($configRepo);
        $smtpConfig = new SmtpConfig($mailConfig);

        // Access the getter to trigger the exception
        match ($missingKey) {
            'host' => $smtpConfig->host(),
            'port' => $smtpConfig->port(),
            'encryption' => $smtpConfig->encryption(),
            'timeout' => $smtpConfig->timeout(),
            'auth_mode' => $smtpConfig->authMode(),
        };
    },
)->throws(MailException::class)
    ->with(['host', 'port', 'encryption', 'timeout', 'auth_mode']);

it('uses FakeConfigRepository in SmtpConfigTest', function (): void {
    $repo = new FakeConfigRepository(
        ['mail.smtp' => ['host' => 'localhost', 'port' => 587, 'encryption' => 'tls', 'timeout' => 30, 'auth_mode' => 'login']],
    );
    $mailConfig = new MailConfig($repo);
    $smtpConfig = new SmtpConfig($mailConfig);

    expect($smtpConfig->host())->toBe('localhost');
});
