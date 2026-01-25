<?php

declare(strict_types=1);

namespace Marko\Mail\Smtp\Tests\Unit;

use Marko\Mail\Config\MailConfig;
use Marko\Mail\Contracts\MailerInterface;
use Marko\Mail\Smtp\SmtpConfig;
use Marko\Mail\Smtp\SmtpMailer;
use Marko\Mail\Smtp\SmtpMailerFactory;
use Marko\Mail\Smtp\SocketInterface;

test('SmtpMailerFactory creates SmtpMailer instance', function (): void {
    $mailConfig = $this->createMock(MailConfig::class);
    $mailConfig->method('driverConfig')->willReturn([
        'host' => 'smtp.example.com',
        'port' => 587,
    ]);

    $smtpConfig = new SmtpConfig($mailConfig);
    $socket = $this->createMock(SocketInterface::class);
    $factory = new SmtpMailerFactory($smtpConfig, $socket);

    $mailer = $factory->create();

    expect($mailer)->toBeInstanceOf(SmtpMailer::class);
});

test('SmtpMailerFactory returns MailerInterface', function (): void {
    $mailConfig = $this->createMock(MailConfig::class);
    $mailConfig->method('driverConfig')->willReturn([]);

    $smtpConfig = new SmtpConfig($mailConfig);
    $socket = $this->createMock(SocketInterface::class);
    $factory = new SmtpMailerFactory($smtpConfig, $socket);

    $mailer = $factory->create();

    expect($mailer)->toBeInstanceOf(MailerInterface::class);
});
