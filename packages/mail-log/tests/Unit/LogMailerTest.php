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

test('it logs email sent message at info level', function (): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
        ->method('info')
        ->with('Email sent', $this->isArray());

    $mailer = new LogMailer($logger);

    $message = Message::create()
        ->from('sender@example.com')
        ->to('recipient@example.com')
        ->subject('Test')
        ->text('Hello');

    $mailer->send($message);
});

test('it includes from address in log context', function (): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
        ->method('info')
        ->with(
            'Email sent',
            $this->callback(function (array $context): bool {
                return isset($context['from']) && $context['from'] === 'sender@example.com';
            }),
        );

    $mailer = new LogMailer($logger);

    $message = Message::create()
        ->from('sender@example.com')
        ->to('recipient@example.com')
        ->subject('Test')
        ->text('Hello');

    $mailer->send($message);
});

test('it includes to addresses in log context', function (): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
        ->method('info')
        ->with(
            'Email sent',
            $this->callback(function (array $context): bool {
                return isset($context['to'])
                    && $context['to'] === ['recipient@example.com', 'other@example.com'];
            }),
        );

    $mailer = new LogMailer($logger);

    $message = Message::create()
        ->from('sender@example.com')
        ->to('recipient@example.com')
        ->to('other@example.com')
        ->subject('Test')
        ->text('Hello');

    $mailer->send($message);
});

test('it includes cc addresses in log context when present', function (): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
        ->method('info')
        ->with(
            'Email sent',
            $this->callback(function (array $context): bool {
                return isset($context['cc'])
                    && $context['cc'] === ['cc1@example.com', 'cc2@example.com'];
            }),
        );

    $mailer = new LogMailer($logger);

    $message = Message::create()
        ->from('sender@example.com')
        ->to('recipient@example.com')
        ->cc('cc1@example.com')
        ->cc('cc2@example.com')
        ->subject('Test')
        ->text('Hello');

    $mailer->send($message);
});

test('it includes bcc addresses in log context when present', function (): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
        ->method('info')
        ->with(
            'Email sent',
            $this->callback(function (array $context): bool {
                return isset($context['bcc'])
                    && $context['bcc'] === ['bcc1@example.com', 'bcc2@example.com'];
            }),
        );

    $mailer = new LogMailer($logger);

    $message = Message::create()
        ->from('sender@example.com')
        ->to('recipient@example.com')
        ->bcc('bcc1@example.com')
        ->bcc('bcc2@example.com')
        ->subject('Test')
        ->text('Hello');

    $mailer->send($message);
});

test('it includes subject in log context', function (): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
        ->method('info')
        ->with(
            'Email sent',
            $this->callback(function (array $context): bool {
                return isset($context['subject']) && $context['subject'] === 'Test Subject';
            }),
        );

    $mailer = new LogMailer($logger);

    $message = Message::create()
        ->from('sender@example.com')
        ->to('recipient@example.com')
        ->subject('Test Subject')
        ->text('Hello');

    $mailer->send($message);
});

test('it includes has_html flag in log context', function (): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
        ->method('info')
        ->with(
            'Email sent',
            $this->callback(function (array $context): bool {
                return isset($context['has_html']) && $context['has_html'] === true;
            }),
        );

    $mailer = new LogMailer($logger);

    $message = Message::create()
        ->from('sender@example.com')
        ->to('recipient@example.com')
        ->subject('Test')
        ->html('<p>Hello</p>');

    $mailer->send($message);
});

test('it includes has_text flag in log context', function (): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
        ->method('info')
        ->with(
            'Email sent',
            $this->callback(function (array $context): bool {
                return isset($context['has_text']) && $context['has_text'] === true;
            }),
        );

    $mailer = new LogMailer($logger);

    $message = Message::create()
        ->from('sender@example.com')
        ->to('recipient@example.com')
        ->subject('Test')
        ->text('Hello');

    $mailer->send($message);
});

test('it includes attachment count in log context', function (): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
        ->method('info')
        ->with(
            'Email sent',
            $this->callback(function (array $context): bool {
                return isset($context['attachment_count']) && $context['attachment_count'] === 2;
            }),
        );

    $mailer = new LogMailer($logger);

    // Create temp files for attachments
    $tempFile1 = tempnam(sys_get_temp_dir(), 'attachment1_');
    $tempFile2 = tempnam(sys_get_temp_dir(), 'attachment2_');
    file_put_contents($tempFile1, 'content1');
    file_put_contents($tempFile2, 'content2');

    try {
        $message = Message::create()
            ->from('sender@example.com')
            ->to('recipient@example.com')
            ->subject('Test')
            ->text('Hello')
            ->attach($tempFile1)
            ->attach($tempFile2);

        $mailer->send($message);
    } finally {
        unlink($tempFile1);
        unlink($tempFile2);
    }
});

test('it logs text body at debug level', function (): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
        ->method('debug')
        ->with(
            'Email body (text)',
            $this->callback(function (array $context): bool {
                return isset($context['body']) && $context['body'] === 'Hello World';
            }),
        );

    $mailer = new LogMailer($logger);

    $message = Message::create()
        ->from('sender@example.com')
        ->to('recipient@example.com')
        ->subject('Test')
        ->text('Hello World');

    $mailer->send($message);
});

test('it logs html body at debug level', function (): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
        ->method('debug')
        ->with(
            'Email body (html)',
            $this->callback(function (array $context): bool {
                return isset($context['body']) && $context['body'] === '<p>Hello World</p>';
            }),
        );

    $mailer = new LogMailer($logger);

    $message = Message::create()
        ->from('sender@example.com')
        ->to('recipient@example.com')
        ->subject('Test')
        ->html('<p>Hello World</p>');

    $mailer->send($message);
});

test('it logs raw email content for sendRaw', function (): void {
    $rawContent = "From: sender@example.com\r\n";
    $rawContent .= "To: recipient@example.com\r\n";
    $rawContent .= "Subject: Raw Email\r\n";
    $rawContent .= "\r\n";
    $rawContent .= 'This is a raw email body.';

    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
        ->method('info')
        ->with(
            'Raw email sent',
            $this->callback(function (array $context) use ($rawContent): bool {
                return isset($context['to'])
                    && $context['to'] === 'recipient@example.com'
                    && isset($context['raw_length'])
                    && $context['raw_length'] === strlen($rawContent);
            }),
        );
    $logger->expects($this->once())
        ->method('debug')
        ->with(
            'Raw email content',
            $this->callback(function (array $context) use ($rawContent): bool {
                return isset($context['content']) && $context['content'] === $rawContent;
            }),
        );

    $mailer = new LogMailer($logger);

    $mailer->sendRaw('recipient@example.com', $rawContent);
});

test('it includes attachment metadata without binary content', function (): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
        ->method('info')
        ->with(
            'Email sent',
            $this->callback(function (array $context): bool {
                if (!isset($context['attachments']) || count($context['attachments']) !== 1) {
                    return false;
                }

                $attachment = $context['attachments'][0];

                return isset($attachment['name'])
                    && $attachment['name'] === 'test.txt'
                    && isset($attachment['size'])
                    && $attachment['size'] === 12
                    && isset($attachment['mime_type'])
                    && $attachment['mime_type'] === 'text/plain'
                    && !isset($attachment['content']);
            }),
        );

    $mailer = new LogMailer($logger);

    // Create temp file for attachment
    $tempFile = tempnam(sys_get_temp_dir(), 'attachment_');
    file_put_contents($tempFile, 'test content');

    try {
        $message = Message::create()
            ->from('sender@example.com')
            ->to('recipient@example.com')
            ->subject('Test')
            ->text('Hello')
            ->attach($tempFile, 'test.txt', 'text/plain');

        $mailer->send($message);
    } finally {
        unlink($tempFile);
    }
});
