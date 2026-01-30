<?php

declare(strict_types=1);

namespace Marko\Mail\Log\Tests\Unit;

use Marko\Log\Contracts\LoggerInterface;
use Marko\Mail\Contracts\MailerInterface;
use Marko\Mail\Log\LogMailer;
use Marko\Mail\Message;
use ReflectionClass;

test('it implements MailerInterface', function (): void {
    $logger = $this->createMock(LoggerInterface::class);
    $mailer = new LogMailer($logger);

    expect($mailer)->toBeInstanceOf(MailerInterface::class);
});

test('it accepts LoggerInterface via constructor', function (): void {
    $logger = $this->createMock(LoggerInterface::class);
    $mailer = new LogMailer($logger);

    $reflection = new ReflectionClass($mailer);
    $constructor = $reflection->getConstructor();
    $parameters = $constructor->getParameters();

    expect($parameters)->toHaveCount(1);
    expect($parameters[0]->getName())->toBe('logger');
    expect($parameters[0]->getType()->getName())->toBe(LoggerInterface::class);
});

test('it returns true from send method', function (): void {
    $logger = $this->createMock(LoggerInterface::class);
    $mailer = new LogMailer($logger);

    $message = Message::create()
        ->from('sender@example.com')
        ->to('recipient@example.com')
        ->subject('Test')
        ->text('Hello');

    $result = $mailer->send($message);

    expect($result)->toBeTrue();
});

test('it returns true from sendRaw method', function (): void {
    $logger = $this->createMock(LoggerInterface::class);
    $mailer = new LogMailer($logger);

    $rawMessage = "From: sender@example.com\r\n";
    $rawMessage .= "To: recipient@example.com\r\n";
    $rawMessage .= "Subject: Raw Email\r\n";
    $rawMessage .= "\r\n";
    $rawMessage .= 'This is a raw email body.';

    $result = $mailer->sendRaw('recipient@example.com', $rawMessage);

    expect($result)->toBeTrue();
});
