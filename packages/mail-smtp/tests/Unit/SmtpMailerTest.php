<?php

declare(strict_types=1);

namespace Marko\Mail\Smtp\Tests\Unit;

use Marko\Mail\Contracts\MailerInterface;
use Marko\Mail\Exception\MessageException;
use Marko\Mail\Message;
use Marko\Mail\Smtp\SmtpMailer;
use Marko\Mail\Smtp\SmtpTransport;
use Marko\Mail\Smtp\SocketInterface;

test('SmtpMailer implements MailerInterface', function (): void {
    $transport = createSmtpMockTransport();
    $mailer = new SmtpMailer($transport);

    expect($mailer)->toBeInstanceOf(MailerInterface::class);
});

test('SmtpMailer sends simple text email', function (): void {
    $socket = new SmtpMockSocket([
        '220 smtp.example.com ESMTP ready',
        '250 OK', // MAIL FROM
        '250 OK', // RCPT TO
        '354 Start mail input', // DATA
        '250 OK', // End of DATA
    ]);
    $transport = new SmtpTransport($socket);
    $transport->connect('smtp.example.com', 587);

    $mailer = new SmtpMailer($transport);

    $message = Message::create()
        ->from('sender@example.com', 'Sender')
        ->to('recipient@example.com', 'Recipient')
        ->subject('Test Subject')
        ->text('Hello, this is a test email.');

    $result = $mailer->send($message);

    expect($result)->toBeTrue();

    $written = $socket->getWritten();
    $writtenString = implode('', $written);

    // Verify SMTP commands
    expect($writtenString)->toContain('MAIL FROM:<sender@example.com>');
    expect($writtenString)->toContain('RCPT TO:<recipient@example.com>');
    expect($writtenString)->toContain('DATA');

    // Verify email headers and content
    expect($writtenString)->toContain('From: Sender <sender@example.com>');
    expect($writtenString)->toContain('To: Recipient <recipient@example.com>');
    expect($writtenString)->toContain('Subject: Test Subject');
    expect($writtenString)->toContain('Content-Type: text/plain');
    expect($writtenString)->toContain('Hello, this is a test email.');
});

test('SmtpMailer sends HTML email', function (): void {
    $socket = new SmtpMockSocket([
        '220 smtp.example.com ESMTP ready',
        '250 OK', // MAIL FROM
        '250 OK', // RCPT TO
        '354 Start mail input', // DATA
        '250 OK', // End of DATA
    ]);
    $transport = new SmtpTransport($socket);
    $transport->connect('smtp.example.com', 587);

    $mailer = new SmtpMailer($transport);

    $message = Message::create()
        ->from('sender@example.com')
        ->to('recipient@example.com')
        ->subject('HTML Email')
        ->html('<html><body><h1>Hello</h1></body></html>');

    $result = $mailer->send($message);

    expect($result)->toBeTrue();

    $written = $socket->getWritten();
    $writtenString = implode('', $written);

    // Verify Content-Type is HTML
    expect($writtenString)->toContain('Content-Type: text/html');
    expect($writtenString)->toContain('<html><body><h1>Hello</h1></body></html>');
});

test('SmtpMailer sends multipart email', function (): void {
    $socket = new SmtpMockSocket([
        '220 smtp.example.com ESMTP ready',
        '250 OK', // MAIL FROM
        '250 OK', // RCPT TO
        '354 Start mail input', // DATA
        '250 OK', // End of DATA
    ]);
    $transport = new SmtpTransport($socket);
    $transport->connect('smtp.example.com', 587);

    $mailer = new SmtpMailer($transport);

    $message = Message::create()
        ->from('sender@example.com')
        ->to('recipient@example.com')
        ->subject('Multipart Email')
        ->text('Plain text version')
        ->html('<html><body>HTML version</body></html>');

    $result = $mailer->send($message);

    expect($result)->toBeTrue();

    $written = $socket->getWritten();
    $writtenString = implode('', $written);

    // Verify multipart structure
    expect($writtenString)->toContain('Content-Type: multipart/alternative');
    expect($writtenString)->toContain('boundary=');
    expect($writtenString)->toContain('Content-Type: text/plain');
    expect($writtenString)->toContain('Content-Type: text/html');
    expect($writtenString)->toContain('Plain text version');
    expect($writtenString)->toContain('HTML version');
});

test('SmtpMailer handles attachments', function (): void {
    // Create a temporary file for attachment
    $tempFile = sys_get_temp_dir() . '/test_attachment.txt';
    file_put_contents($tempFile, 'This is attachment content');

    $socket = new SmtpMockSocket([
        '220 smtp.example.com ESMTP ready',
        '250 OK', // MAIL FROM
        '250 OK', // RCPT TO
        '354 Start mail input', // DATA
        '250 OK', // End of DATA
    ]);
    $transport = new SmtpTransport($socket);
    $transport->connect('smtp.example.com', 587);

    $mailer = new SmtpMailer($transport);

    $message = Message::create()
        ->from('sender@example.com')
        ->to('recipient@example.com')
        ->subject('Email with Attachment')
        ->text('See attached file.')
        ->attach($tempFile, 'document.txt', 'text/plain');

    $result = $mailer->send($message);

    expect($result)->toBeTrue();

    $written = $socket->getWritten();
    $writtenString = implode('', $written);

    // Verify multipart/mixed for attachments
    expect($writtenString)->toContain('Content-Type: multipart/mixed');
    expect($writtenString)->toContain('Content-Disposition: attachment; filename="document.txt"');
    expect($writtenString)->toContain('Content-Transfer-Encoding: base64');

    // Verify the attachment content is base64 encoded
    $encodedContent = base64_encode('This is attachment content');
    expect($writtenString)->toContain($encodedContent);

    // Cleanup
    unlink($tempFile);
});

test('SmtpMailer handles inline images', function (): void {
    // Create a temporary image file
    $tempImage = sys_get_temp_dir() . '/test_image.png';
    // Create a minimal PNG (1x1 transparent pixel)
    $pngData = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
    );
    file_put_contents($tempImage, $pngData);

    $socket = new SmtpMockSocket([
        '220 smtp.example.com ESMTP ready',
        '250 OK', // MAIL FROM
        '250 OK', // RCPT TO
        '354 Start mail input', // DATA
        '250 OK', // End of DATA
    ]);
    $transport = new SmtpTransport($socket);
    $transport->connect('smtp.example.com', 587);

    $mailer = new SmtpMailer($transport);

    $message = Message::create()
        ->from('sender@example.com')
        ->to('recipient@example.com')
        ->subject('Email with Inline Image')
        ->html('<html><body><img src="cid:logo123"></body></html>')
        ->embed($tempImage, 'logo123', 'logo.png', 'image/png');

    $result = $mailer->send($message);

    expect($result)->toBeTrue();

    $written = $socket->getWritten();
    $writtenString = implode('', $written);

    // Verify multipart/related structure for inline images
    expect($writtenString)->toContain('Content-Type: multipart/related');
    expect($writtenString)->toContain('Content-ID: <logo123>');
    expect($writtenString)->toContain('Content-Disposition: inline; filename="logo.png"');

    // Cleanup
    unlink($tempImage);
});

test('SmtpMailer sets proper headers', function (): void {
    $socket = new SmtpMockSocket([
        '220 smtp.example.com ESMTP ready',
        '250 OK', // MAIL FROM
        '250 OK', // RCPT TO
        '250 OK', // RCPT TO (Cc)
        '354 Start mail input', // DATA
        '250 OK', // End of DATA
    ]);
    $transport = new SmtpTransport($socket);
    $transport->connect('smtp.example.com', 587);

    $mailer = new SmtpMailer($transport);

    $message = Message::create()
        ->from('sender@example.com', 'Sender Name')
        ->to('recipient@example.com', 'Recipient Name')
        ->cc('cc@example.com', 'CC Name')
        ->replyTo('reply@example.com', 'Reply Name')
        ->subject('Test Subject')
        ->priority(1)
        ->header('X-Custom-Header', 'custom-value')
        ->text('Hello');

    $result = $mailer->send($message);

    expect($result)->toBeTrue();

    $written = $socket->getWritten();
    $writtenString = implode('', $written);

    // Verify all headers are set correctly
    expect($writtenString)->toContain('From: Sender Name <sender@example.com>');
    expect($writtenString)->toContain('To: Recipient Name <recipient@example.com>');
    expect($writtenString)->toContain('Cc: CC Name <cc@example.com>');
    expect($writtenString)->toContain('Reply-To: Reply Name <reply@example.com>');
    expect($writtenString)->toContain('Subject: Test Subject');
    expect($writtenString)->toContain('MIME-Version: 1.0');
    expect($writtenString)->toContain('X-Priority: 1');
    expect($writtenString)->toContain('X-Custom-Header: custom-value');
});

test('SmtpMailer throws on no recipients', function (): void {
    $socket = new SmtpMockSocket([
        '220 smtp.example.com ESMTP ready',
    ]);
    $transport = new SmtpTransport($socket);
    $transport->connect('smtp.example.com', 587);

    $mailer = new SmtpMailer($transport);

    $message = Message::create()
        ->from('sender@example.com')
        ->subject('No Recipients')
        ->text('This has no recipients');

    $mailer->send($message);
})->throws(MessageException::class, 'No recipients specified');

test('SmtpMailer sendRaw sends pre-formatted message', function (): void {
    $socket = new SmtpMockSocket([
        '220 smtp.example.com ESMTP ready',
        '250 OK', // MAIL FROM
        '250 OK', // RCPT TO
        '354 Start mail input', // DATA
        '250 OK', // End of DATA
    ]);
    $transport = new SmtpTransport($socket);
    $transport->connect('smtp.example.com', 587);

    $mailer = new SmtpMailer($transport);

    $rawMessage = "From: sender@example.com\r\n";
    $rawMessage .= "To: recipient@example.com\r\n";
    $rawMessage .= "Subject: Raw Email\r\n";
    $rawMessage .= "MIME-Version: 1.0\r\n";
    $rawMessage .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $rawMessage .= "\r\n";
    $rawMessage .= 'This is a raw email body.';

    $result = $mailer->sendRaw('recipient@example.com', $rawMessage);

    expect($result)->toBeTrue();

    $written = $socket->getWritten();
    $writtenString = implode('', $written);

    // Verify the raw message was sent as-is
    expect($writtenString)->toContain('RCPT TO:<recipient@example.com>');
    expect($writtenString)->toContain('Subject: Raw Email');
    expect($writtenString)->toContain('This is a raw email body.');
});

function createSmtpMockTransport(
    array $responses = [],
): SmtpTransport {
    $socket = new SmtpMockSocket($responses);

    return new SmtpTransport($socket);
}

class SmtpMockSocket implements SocketInterface
{
    private bool $connected = false;

    private int $responseIndex = 0;

    /** @var array<string> */
    private array $written = [];

    public function __construct(
        private array $responses = [],
    ) {}

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
        return $this->responses[$this->responseIndex++] ?? '';
    }

    public function write(
        string $data,
    ): void {
        $this->written[] = $data;
    }

    public function enableTls(): bool
    {
        return true;
    }

    public function close(): void
    {
        $this->connected = false;
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    /** @return array<string> */
    public function getWritten(): array
    {
        return $this->written;
    }
}
