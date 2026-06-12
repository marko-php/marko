<?php

declare(strict_types=1);

namespace Marko\Mail\Smtp\Tests\Unit;

use Marko\Mail\Contracts\MailerInterface;
use Marko\Mail\Smtp\SmtpMailer;
use Marko\Mail\Smtp\SmtpMailerFactory;

test('SmtpMailerFactory creates SmtpMailer instance', function (): void {
    $socket = Helpers::createMockSocket([
        '220 smtp.example.com ESMTP ready',             // connect banner
        "250-smtp.example.com\r\n250 AUTH LOGIN PLAIN", // EHLO response
        '220 Ready to start TLS',                        // STARTTLS reply
        "250-smtp.example.com\r\n250 AUTH LOGIN PLAIN", // re-EHLO after STARTTLS
        '334 VXNlcm5hbWU6',                             // AUTH LOGIN username prompt
        '334 UGFzc3dvcmQ6',                             // AUTH LOGIN password prompt
        '235 Authentication successful',
    ]);

    $smtpConfig = Helpers::createSmtpConfig([
        'host' => 'smtp.example.com',
        'port' => 587,
        'encryption' => 'tls',
        'timeout' => 30,
        'auth_mode' => 'login',
        'username' => 'user@example.com',
        'password' => 'secret',
    ]);

    $factory = new SmtpMailerFactory($smtpConfig, $socket);
    $mailer = $factory->create();

    expect($mailer)->toBeInstanceOf(SmtpMailer::class);
});

test('SmtpMailerFactory returns MailerInterface', function (): void {
    $socket = Helpers::createMockSocket([
        '220 smtp.example.com ESMTP ready',
        "250-smtp.example.com\r\n250 AUTH LOGIN PLAIN",
        '220 Ready to start TLS',
        "250-smtp.example.com\r\n250 AUTH LOGIN PLAIN",
        '334 VXNlcm5hbWU6',
        '334 UGFzc3dvcmQ6',
        '235 Authentication successful',
    ]);

    $smtpConfig = Helpers::createSmtpConfig([
        'host' => 'smtp.example.com',
        'port' => 587,
        'encryption' => 'tls',
        'timeout' => 30,
        'auth_mode' => 'login',
        'username' => 'user@example.com',
        'password' => 'secret',
    ]);

    $factory = new SmtpMailerFactory($smtpConfig, $socket);
    $mailer = $factory->create();

    expect($mailer)->toBeInstanceOf(MailerInterface::class);
});

it(
    'connects, sends EHLO, and authenticates using values from SmtpConfig when create() builds the mailer',
    function (): void {
        $socket = Helpers::createMockSocket([
            '220 smtp.example.com ESMTP ready',
            "250-smtp.example.com\r\n250 AUTH LOGIN PLAIN",
            '220 Ready to start TLS',
            "250-smtp.example.com\r\n250 AUTH LOGIN PLAIN",
            '334 VXNlcm5hbWU6',
            '334 UGFzc3dvcmQ6',
            '235 Authentication successful',
        ]);

        $smtpConfig = Helpers::createSmtpConfig([
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'timeout' => 30,
            'auth_mode' => 'login',
            'username' => 'user@example.com',
            'password' => 'secret',
        ]);

        $factory = new SmtpMailerFactory($smtpConfig, $socket);
        $factory->create();

        expect($socket->connected)->toBeTrue()
            ->and($socket->written)->toContain('EHLO ' . gethostname() . "\r\n")
            ->and($socket->written)->toContain("AUTH LOGIN\r\n")
            ->and($socket->written)->toContain(base64_encode('user@example.com') . "\r\n")
            ->and($socket->written)->toContain(base64_encode('secret') . "\r\n");
    },
);

it('authenticates when the configured auth mode is lowercase "login" (case-insensitive matching)', function (): void {
    $socket = Helpers::createMockSocket([
        '220 smtp.example.com ESMTP ready',
        "250-smtp.example.com\r\n250 AUTH LOGIN PLAIN",
        '220 Ready to start TLS',
        "250-smtp.example.com\r\n250 AUTH LOGIN PLAIN",
        '334 VXNlcm5hbWU6',
        '334 UGFzc3dvcmQ2',
        '235 Authentication successful',
    ]);

    $smtpConfig = Helpers::createSmtpConfig([
        'host' => 'smtp.example.com',
        'port' => 587,
        'encryption' => 'tls',
        'timeout' => 30,
        'auth_mode' => 'login',  // lowercase
        'username' => 'user@example.com',
        'password' => 'secret',
    ]);

    $factory = new SmtpMailerFactory($smtpConfig, $socket);
    $factory->create();

    expect($socket->written)->toContain("AUTH LOGIN\r\n");
});

it('authenticates when the configured auth mode is lowercase "plain"', function (): void {
    $socket = Helpers::createMockSocket([
        '220 smtp.example.com ESMTP ready',
        "250-smtp.example.com\r\n250 AUTH LOGIN PLAIN",
        '220 Ready to start TLS',
        "250-smtp.example.com\r\n250 AUTH LOGIN PLAIN",
        '235 Authentication successful',
    ]);

    $smtpConfig = Helpers::createSmtpConfig([
        'host' => 'smtp.example.com',
        'port' => 587,
        'encryption' => 'tls',
        'timeout' => 30,
        'auth_mode' => 'plain',  // lowercase
        'username' => 'user@example.com',
        'password' => 'secret',
    ]);

    $factory = new SmtpMailerFactory($smtpConfig, $socket);
    $factory->create();

    $expectedCredentials = base64_encode("\0user@example.com\0secret");
    expect($socket->written)->toContain("AUTH PLAIN $expectedCredentials\r\n");
});

it(
    'skips authentication when no username/password is configured (null credentials are valid; no exception thrown)',
    function (): void {
        $socket = Helpers::createMockSocket([
            '220 smtp.example.com ESMTP ready',
            "250-smtp.example.com\r\n250 AUTH LOGIN PLAIN",
            '220 Ready to start TLS',
            "250-smtp.example.com\r\n250 AUTH LOGIN PLAIN",
        ]);

        $smtpConfig = Helpers::createSmtpConfig([
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'timeout' => 30,
            'auth_mode' => 'login',
            // no username or password = null credentials
        ]);

        $factory = new SmtpMailerFactory($smtpConfig, $socket);
        $mailer = $factory->create();

        expect($mailer)->toBeInstanceOf(MailerInterface::class)
            ->and($socket->written)->not->toContain("AUTH LOGIN\r\n")
            ->and($socket->written)->not->toContain('AUTH PLAIN ');
    },
);

it('does not issue STARTTLS when encryption is "ssl" (implicit TLS from connect)', function (): void {
    $socket = Helpers::createMockSocket([
        '220 smtp.example.com ESMTP ready',
        "250-smtp.example.com\r\n250 AUTH LOGIN PLAIN",
        '334 VXNlcm5hbWU6',
        '334 UGFzc3dvcmQ2',
        '235 Authentication successful',
    ]);

    $smtpConfig = Helpers::createSmtpConfig([
        'host' => 'smtp.example.com',
        'port' => 465,
        'encryption' => 'ssl',
        'timeout' => 30,
        'auth_mode' => 'login',
        'username' => 'user@example.com',
        'password' => 'secret',
    ]);

    $factory = new SmtpMailerFactory($smtpConfig, $socket);
    $factory->create();

    expect($socket->written)->not->toContain("STARTTLS\r\n");
});
