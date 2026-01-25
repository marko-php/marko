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

    $written = $socket->written;
    $writtenString = implode('', $written);

    // Verify SMTP commands
    expect($writtenString)->toContain('MAIL FROM:<sender@example.com>')
        ->and($writtenString)->toContain('RCPT TO:<recipient@example.com>')
        ->and($writtenString)->toContain('DATA')
        ->and($writtenString)->toContain('From: Sender <sender@example.com>')
        ->and($writtenString)->toContain('To: Recipient <recipient@example.com>')
        ->and($writtenString)->toContain('Subject: Test Subject')
        ->and($writtenString)->toContain('Content-Type: text/plain')
        ->and($writtenString)->toContain('Hello, this is a test email.');

    // Verify email headers and content
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
        ->html('<html lang=""><body><h1>Hello</h1></body></html>');

    $result = $mailer->send($message);

    expect($result)->toBeTrue();

    $written = $socket->written;
    $writtenString = implode('', $written);

    // Verify Content-Type is HTML (note: = is encoded as =3D in quoted-printable)
    expect($writtenString)->toContain('Content-Type: text/html')
        ->and($writtenString)->toContain('<html lang=3D""><body><h1>Hello</h1></body></html>');
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
        ->html('<html lang=""><body>HTML version</body></html>');

    $result = $mailer->send($message);

    expect($result)->toBeTrue();

    $written = $socket->written;
    $writtenString = implode('', $written);

    // Verify multipart structure
    expect($writtenString)->toContain('Content-Type: multipart/alternative')
        ->and($writtenString)->toContain('boundary=')
        ->and($writtenString)->toContain('Content-Type: text/plain')
        ->and($writtenString)->toContain('Content-Type: text/html')
        ->and($writtenString)->toContain('Plain text version')
        ->and($writtenString)->toContain('HTML version');
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

    $written = $socket->written;
    $writtenString = implode('', $written);

    // Verify multipart/mixed for attachments
    expect($writtenString)->toContain('Content-Type: multipart/mixed')
        ->and($writtenString)->toContain('Content-Disposition: attachment; filename="document.txt"')
        ->and($writtenString)->toContain('Content-Transfer-Encoding: base64');

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
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
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
        ->html('<html lang=""><body><img src="cid:logo123" alt=""></body></html>')
        ->embed($tempImage, 'logo123', 'logo.png', 'image/png');

    $result = $mailer->send($message);

    expect($result)->toBeTrue();

    $written = $socket->written;
    $writtenString = implode('', $written);

    // Verify multipart/related structure for inline images
    expect($writtenString)->toContain('Content-Type: multipart/related')
        ->and($writtenString)->toContain('Content-ID: <logo123>')
        ->and($writtenString)->toContain('Content-Disposition: inline; filename="logo.png"');

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

    $written = $socket->written;
    $writtenString = implode('', $written);

    // Verify all headers are set correctly
    expect($writtenString)->toContain('From: Sender Name <sender@example.com>')
        ->and($writtenString)->toContain('To: Recipient Name <recipient@example.com>')
        ->and($writtenString)->toContain('Cc: CC Name <cc@example.com>')
        ->and($writtenString)->toContain('Reply-To: Reply Name <reply@example.com>')
        ->and($writtenString)->toContain('Subject: Test Subject')
        ->and($writtenString)->toContain('MIME-Version: 1.0')
        ->and($writtenString)->toContain('X-Priority: 1')
        ->and($writtenString)->toContain('X-Custom-Header: custom-value');
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

test('SmtpMailer generates correct MIME boundaries', function (): void {
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
        ->subject('Test Boundaries')
        ->text('Plain text version')
        ->html('<html lang=""><body>HTML version</body></html>');

    $mailer->send($message);

    $writtenString = implode('', $socket->written);

    // Verify boundary has correct prefix format
    expect($writtenString)->toMatch('/boundary="=_Part_[a-f0-9]{32}"/');

    // Extract boundaries and verify they are unique
    preg_match_all('/boundary="(=_Part_[a-f0-9]{32})"/', $writtenString, $matches);
    $boundaries = $matches[1];

    // For multipart/alternative, there should be exactly one boundary
    expect($boundaries)->toHaveCount(1);

    // Verify boundary is used correctly in content
    $boundary = $boundaries[0];
    expect($writtenString)->toContain("--$boundary")
        ->and($writtenString)->toContain("--$boundary--");
});

test('SmtpMailer handles ASCII subjects without encoding', function (): void {
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

    // Plain ASCII subject
    $message = Message::create()
        ->from('sender@example.com')
        ->to('recipient@example.com')
        ->subject('Hello World')
        ->text('Test body');

    $mailer->send($message);

    $writtenString = implode('', $socket->written);

    // ASCII subjects should NOT be RFC 2047 encoded
    expect($writtenString)->toContain('Subject: Hello World')
        ->and($writtenString)->not->toContain('=?UTF-8?');
});

test('SmtpMailer handles UTF-8 subjects', function (): void {
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

    // UTF-8 subject with non-ASCII characters
    $message = Message::create()
        ->from('sender@example.com')
        ->to('recipient@example.com')
        ->subject('Привет мир')
        ->text('Test body');

    $mailer->send($message);

    $writtenString = implode('', $socket->written);

    // UTF-8 subject should be RFC 2047 encoded (base64 or quoted-printable)
    // Format: =?UTF-8?B?base64encoded?= or =?UTF-8?Q?quotedprintable?=
    expect($writtenString)->toMatch('/Subject: =\?UTF-8\?[BQ]\?.+\?=/');
});

test('SmtpMailer generates correct Content-ID for inline', function (): void {
    // Create a temporary image file
    $tempImage = sys_get_temp_dir() . '/test_content_id.png';
    $pngData = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
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

    // Use a specific content ID
    $contentId = 'unique-image-123';

    $message = Message::create()
        ->from('sender@example.com')
        ->to('recipient@example.com')
        ->subject('Email with Inline')
        ->html('<html lang=""><body><img src="cid:' . $contentId . '" alt=""></body></html>')
        ->embed($tempImage, $contentId, 'image.png', 'image/png');

    $mailer->send($message);

    $writtenString = implode('', $socket->written);

    // Content-ID should be wrapped in angle brackets per RFC 2045
    expect($writtenString)->toContain("Content-ID: <$contentId>")
        ->and($writtenString)->toContain("cid:$contentId")
        ->and($writtenString)->toContain('Content-Disposition: inline');

    // Also verify the HTML references the CID correctly

    // Verify inline disposition

    // Cleanup
    unlink($tempImage);
});

test('SmtpMailer handles message priority headers', function (): void {
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

    // High priority message (1 = highest)
    $message = Message::create()
        ->from('sender@example.com')
        ->to('recipient@example.com')
        ->subject('Urgent Message')
        ->priority(1)
        ->text('This is urgent!');

    $mailer->send($message);

    $writtenString = implode('', $socket->written);

    // X-Priority header should be set
    expect($writtenString)->toContain('X-Priority: 1');
});

test('SmtpMailer handles different priority levels', function (): void {
    // Test priority 3 (normal)
    $socket = new SmtpMockSocket([
        '220 smtp.example.com ESMTP ready',
        '250 OK',
        '250 OK',
        '354 Start mail input',
        '250 OK',
    ]);
    $transport = new SmtpTransport($socket);
    $transport->connect('smtp.example.com', 587);

    $mailer = new SmtpMailer($transport);

    $message = Message::create()
        ->from('sender@example.com')
        ->to('recipient@example.com')
        ->subject('Normal Priority')
        ->priority(3)
        ->text('Normal message');

    $mailer->send($message);

    $writtenString = implode('', $socket->written);
    expect($writtenString)->toContain('X-Priority: 3');
});

test('SmtpMailer omits priority header when not set', function (): void {
    $socket = new SmtpMockSocket([
        '220 smtp.example.com ESMTP ready',
        '250 OK',
        '250 OK',
        '354 Start mail input',
        '250 OK',
    ]);
    $transport = new SmtpTransport($socket);
    $transport->connect('smtp.example.com', 587);

    $mailer = new SmtpMailer($transport);

    $message = Message::create()
        ->from('sender@example.com')
        ->to('recipient@example.com')
        ->subject('No Priority')
        ->text('Normal message without priority');

    $mailer->send($message);

    $writtenString = implode('', $socket->written);

    // X-Priority should NOT be present
    expect($writtenString)->not->toContain('X-Priority');
});

test('SmtpMailer generates Content-ID with special characters', function (): void {
    // Create a temporary image file
    $tempImage = sys_get_temp_dir() . '/test_special_cid.png';
    $pngData = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
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

    // Content ID with dots and hyphens (common in email)
    $contentId = 'logo.v2-2024';

    $message = Message::create()
        ->from('sender@example.com')
        ->to('recipient@example.com')
        ->subject('Email with Special CID')
        ->html('<html lang=""><body><img src="cid:' . $contentId . '" alt=""></body></html>')
        ->embed($tempImage, $contentId, 'logo.png', 'image/png');

    $mailer->send($message);

    $writtenString = implode('', $socket->written);

    // Content-ID should preserve special characters
    expect($writtenString)->toContain("Content-ID: <$contentId>");

    // Cleanup
    unlink($tempImage);
});

test('SmtpMailer encodes headers properly', function (): void {
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
        ->subject('Test Headers')
        ->header('X-Test-Header', 'simple-value')
        ->header('X-Numeric', '12345')
        ->text('Test body');

    $mailer->send($message);

    $writtenString = implode('', $socket->written);

    // Verify headers are properly formatted
    expect($writtenString)
        ->toContain('X-Test-Header: simple-value')
        ->and($writtenString)->toContain('X-Numeric: 12345')
        ->and($writtenString)->toContain('MIME-Version: 1.0')
        ->and($writtenString)->toContain('Content-Type: text/plain; charset=UTF-8');
});

test('SmtpMailer generates unique boundaries for nested multipart', function (): void {
    // Create a temporary file for attachment
    $tempFile = sys_get_temp_dir() . '/test_boundary_attachment.txt';
    file_put_contents($tempFile, 'Attachment content');

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

    // Message with text, HTML, AND attachment requires nested boundaries
    $message = Message::create()
        ->from('sender@example.com')
        ->to('recipient@example.com')
        ->subject('Nested Boundaries')
        ->text('Plain text')
        ->html('<html lang=""><body>HTML</body></html>')
        ->attach($tempFile, 'file.txt', 'text/plain');

    $mailer->send($message);

    $writtenString = implode('', $socket->written);

    // Extract all boundaries
    preg_match_all('/boundary="(=_Part_[a-f0-9]{32})"/', $writtenString, $matches);
    $boundaries = $matches[1];

    // Should have 2 boundaries: mixed (outer) and alternative (inner)
    expect($boundaries)->toHaveCount(2)
        ->and(array_unique($boundaries))->toHaveCount(2);

    // Verify boundaries are unique (no conflicts)

    // Cleanup
    unlink($tempFile);
});

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

    $written = $socket->written;
    $writtenString = implode('', $written);

    // Verify the raw message was sent as-is
    expect($writtenString)->toContain('RCPT TO:<recipient@example.com>')
        ->and($writtenString)->toContain('Subject: Raw Email')
        ->and($writtenString)->toContain('This is a raw email body.');
});

function createSmtpMockTransport(
    array $responses = [],
): SmtpTransport {
    $socket = new SmtpMockSocket($responses);

    return new SmtpTransport($socket);
}

class SmtpMockSocket implements SocketInterface
{
    public private(set) bool $connected = false;

    private int $responseIndex = 0;

    /** @var array<string> */
    public private(set) array $written = [];

    public function __construct(
        private readonly array $responses = [],
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
}
