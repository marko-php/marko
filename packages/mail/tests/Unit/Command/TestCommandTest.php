<?php

declare(strict_types=1);

use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Mail\Command\TestCommand;
use Marko\Mail\Config\MailConfig;
use Marko\Mail\Contracts\MailerInterface;
use Marko\Mail\Exception\TransportException;
use Marko\Mail\Message;

it('mail:test command requires email argument', function (): void {
    $mailer = $this->createMock(MailerInterface::class);
    $config = $this->createMock(MailConfig::class);

    $command = new TestCommand($mailer, $config);
    $input = new Input(['marko', 'mail:test']);
    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);

    $result = $command->execute($input, $output);

    rewind($stream);
    $content = stream_get_contents($stream);

    expect($result)->toBe(1)
        ->and($content)->toContain('email');
});

it('mail:test command sends test email', function (): void {
    $mailer = $this->createMock(MailerInterface::class);
    $mailer->expects($this->once())
        ->method('send')
        ->willReturn(true);

    $config = $this->createMock(MailConfig::class);
    $config->method('fromAddress')->willReturn('sender@example.com');
    $config->method('fromName')->willReturn('Test Sender');

    $command = new TestCommand($mailer, $config);
    $input = new Input(['marko', 'mail:test', 'recipient@example.com']);
    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);

    $result = $command->execute($input, $output);

    expect($result)->toBe(0);
});

it('mail:test command supports subject option', function (): void {
    $capturedMessage = null;

    $mailer = $this->createMock(MailerInterface::class);
    $mailer->expects($this->once())
        ->method('send')
        ->willReturnCallback(function (Message $message) use (&$capturedMessage) {
            $capturedMessage = $message;

            return true;
        });

    $config = $this->createMock(MailConfig::class);
    $config->method('fromAddress')->willReturn('sender@example.com');
    $config->method('fromName')->willReturn('Test Sender');

    $command = new TestCommand($mailer, $config);
    $input = new Input(['marko', 'mail:test', 'recipient@example.com', '--subject=Custom Subject']);
    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);

    $command->execute($input, $output);

    expect($capturedMessage)->not->toBeNull()
        ->and($capturedMessage->getSubject())->toBe('Custom Subject');
});

it('mail:test command shows success message', function (): void {
    $mailer = $this->createMock(MailerInterface::class);
    $mailer->method('send')->willReturn(true);

    $config = $this->createMock(MailConfig::class);
    $config->method('fromAddress')->willReturn('sender@example.com');
    $config->method('fromName')->willReturn('Test Sender');

    $command = new TestCommand($mailer, $config);
    $input = new Input(['marko', 'mail:test', 'recipient@example.com']);
    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);

    $command->execute($input, $output);

    rewind($stream);
    $content = stream_get_contents($stream);

    expect($content)->toContain('recipient@example.com')
        ->and($content)->toContain('success');
});

it('mail:test command shows failure message on error', function (): void {
    $mailer = $this->createMock(MailerInterface::class);
    $mailer->method('send')
        ->willThrowException(TransportException::connectionFailed('smtp.example.com', 587));

    $config = $this->createMock(MailConfig::class);
    $config->method('fromAddress')->willReturn('sender@example.com');
    $config->method('fromName')->willReturn('Test Sender');

    $command = new TestCommand($mailer, $config);
    $input = new Input(['marko', 'mail:test', 'recipient@example.com']);
    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);

    $result = $command->execute($input, $output);

    rewind($stream);
    $content = stream_get_contents($stream);

    expect($result)->toBe(1)
        ->and($content)->toContain('Failed')
        ->and($content)->toContain('connect');
});
