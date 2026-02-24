<?php

declare(strict_types=1);

use Marko\Mail\Config\MailConfig;
use Marko\Mail\Smtp\SmtpConfig;
use Marko\Testing\Fake\FakeConfigRepository;

it('extracts host from mail config', function (): void {
    $configRepo = new FakeConfigRepository([
        'mail.smtp' => ['host' => 'smtp.example.com'],
    ]);

    $mailConfig = new MailConfig($configRepo);
    $smtpConfig = new SmtpConfig($mailConfig);

    expect($smtpConfig->host())->toBe('smtp.example.com');
});

it('extracts port from mail config', function (): void {
    $configRepo = new FakeConfigRepository([
        'mail.smtp' => ['port' => 465],
    ]);

    $mailConfig = new MailConfig($configRepo);
    $smtpConfig = new SmtpConfig($mailConfig);

    expect($smtpConfig->port())->toBe(465);
});

it('extracts encryption setting', function (): void {
    $configRepo = new FakeConfigRepository([
        'mail.smtp' => ['encryption' => 'ssl'],
    ]);

    $mailConfig = new MailConfig($configRepo);
    $smtpConfig = new SmtpConfig($mailConfig);

    expect($smtpConfig->encryption())->toBe('ssl');
});

it('extracts username and password', function (): void {
    $configRepo = new FakeConfigRepository([
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
    $configRepo = new FakeConfigRepository([
        'mail.smtp' => ['timeout' => 60],
    ]);

    $mailConfig = new MailConfig($configRepo);
    $smtpConfig = new SmtpConfig($mailConfig);

    expect($smtpConfig->timeout())->toBe(60);
});

it('extracts auth_mode setting', function (): void {
    $configRepo = new FakeConfigRepository([
        'mail.smtp' => ['auth_mode' => 'plain'],
    ]);

    $mailConfig = new MailConfig($configRepo);
    $smtpConfig = new SmtpConfig($mailConfig);

    expect($smtpConfig->authMode())->toBe('plain');
});

it('provides default values for optional settings', function (): void {
    $configRepo = new FakeConfigRepository([
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

it('uses FakeConfigRepository in SmtpConfigTest', function (): void {
    $repo = new FakeConfigRepository(['mail.smtp' => ['host' => 'localhost']]);
    $mailConfig = new MailConfig($repo);
    $smtpConfig = new SmtpConfig($mailConfig);

    expect($smtpConfig->host())->toBe('localhost');
});
