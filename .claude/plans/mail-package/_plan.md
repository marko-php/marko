# Plan: Mail Package

## Created
2026-01-21

## Status
completed

## Objective
Implement the mail system for Marko framework with an interface package (`marko/mail`) providing a fluent Message builder and MailerInterface, and an SMTP driver implementation (`marko/mail-smtp`), following the established interface/driver split pattern.

## Scope

### In Scope
- `marko/mail` package with interfaces, Message class, mail configuration, and exceptions
  - `MailerInterface` - primary mail sending contract
  - `Message` - fluent builder for email composition (to, cc, bcc, subject, body, attachments)
  - `Address` - value object for email addresses with optional name
  - `Attachment` - value object for file attachments (path, inline, content-based)
  - `MailConfig` - configuration loaded from config/mail.php
  - `MailException` hierarchy (MailException, TransportException, MessageException)
  - Support for HTML and plain text bodies (multipart/alternative)
  - Attachment support (file path, inline images, raw content)
  - Optional view integration for email templates (when marko/view is installed)
- `marko/mail-smtp` package with SMTP driver implementation
  - `SmtpMailer` - implements MailerInterface using SMTP protocol
  - `SmtpTransport` - low-level SMTP communication
  - `SmtpConfig` - SMTP-specific configuration (host, port, encryption, auth)
  - Support for STARTTLS and SSL/TLS encryption
  - SMTP authentication (LOGIN, PLAIN)
- CLI commands: `mail:test` (send test email to verify configuration)
- Loud errors when no driver installed
- Driver conflict handling if multiple drivers installed

### Out of Scope
- Mailgun, SES, SendGrid drivers (future packages)
- Email queuing (use marko/queue integration)
- Email tracking (opens, clicks)
- Bounce handling
- DKIM/SPF signing (SMTP server responsibility)
- Mail templates/layouts management
- Batch sending optimization
- Rate limiting

## Success Criteria
- [ ] `Message` provides fluent builder for to/cc/bcc/subject/body/attachments
- [ ] `Message` supports both HTML and plain text bodies
- [ ] `Address` handles email addresses with optional display name
- [ ] `Attachment` supports file paths, inline images, and raw content
- [ ] `MailerInterface::send(Message)` sends email and returns success
- [ ] `MailConfig` loads configuration from `config/mail.php`
- [ ] `SmtpMailer` sends emails via SMTP protocol
- [ ] SMTP supports STARTTLS and SSL/TLS encryption
- [ ] SMTP supports LOGIN and PLAIN authentication
- [ ] Optional view integration renders templates when ViewInterface available
- [ ] `mail:test` command sends test email to verify setup
- [ ] Loud error when no mail driver is installed
- [ ] Loud error when multiple mail drivers conflict
- [ ] Loud error on SMTP connection failures with actionable suggestion
- [ ] All tests passing
- [ ] Code follows project standards (strict types, no final, etc.)

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Package scaffolding (composer.json for mail and mail-smtp) | - | pending |
| 002 | MailException hierarchy | 001 | pending |
| 003 | Address value object | 001 | pending |
| 004 | Attachment value object | 001 | pending |
| 005 | Message class (fluent builder) | 003, 004 | pending |
| 006 | MailerInterface contract | 005 | pending |
| 007 | MailConfig class | 001 | pending |
| 008 | mail package module.php with MailConfig binding | 007 | pending |
| 009 | SmtpConfig class | 007 | pending |
| 010 | SmtpTransport (low-level SMTP protocol) | 002 | pending |
| 011 | SmtpMailer implementation | 006, 009, 010 | pending |
| 012 | SmtpMailerFactory | 009, 011 | pending |
| 013 | mail-smtp module.php with bindings | 012 | pending |
| 014 | Optional view integration for templates | 006 | pending |
| 015 | CLI: mail:test command | 006, 007 | pending |
| 016 | Unit tests for Message class | 005 | pending |
| 017 | Unit tests for Address and Attachment | 003, 004 | pending |
| 018 | Unit tests for SmtpTransport | 010 | pending |
| 019 | Unit tests for SmtpMailer | 011 | pending |
| 020 | Integration tests | 013 | pending |

## Architecture Notes

### Package Structure
```
packages/
  mail/                         # Interface package
    src/
      Contracts/
        MailerInterface.php
      Config/
        MailConfig.php
      Exceptions/
        MailException.php
        TransportException.php
        MessageException.php
      Message/
        Message.php
        Address.php
        Attachment.php
      Command/
        TestCommand.php
    tests/
    composer.json
    module.php
  mail-smtp/                    # SMTP implementation
    src/
      Config/
        SmtpConfig.php
      Transport/
        SmtpTransport.php
      Factory/
        SmtpMailerFactory.php
      SmtpMailer.php
    tests/
    composer.json
    module.php
```

### Config Location
```php
// config/mail.php
return [
    'driver' => 'smtp',
    'from' => [
        'address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'hello@example.com',
        'name' => $_ENV['MAIL_FROM_NAME'] ?? 'Marko Application',
    ],

    'smtp' => [
        'host' => $_ENV['MAIL_HOST'] ?? 'localhost',
        'port' => (int) ($_ENV['MAIL_PORT'] ?? 587),
        'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls', // 'tls', 'ssl', or null
        'username' => $_ENV['MAIL_USERNAME'] ?? null,
        'password' => $_ENV['MAIL_PASSWORD'] ?? null,
        'timeout' => 30,
        'auth_mode' => 'login', // 'login', 'plain', or null
    ],
];
```

### MailerInterface Contract
```php
interface MailerInterface
{
    /**
     * Send an email message.
     *
     * @throws TransportException On delivery failure
     */
    public function send(Message $message): bool;

    /**
     * Send a raw email (pre-formatted string).
     */
    public function sendRaw(string $to, string $raw): bool;
}
```

### Message Class (Fluent Builder)
```php
$message = Message::create()
    ->to('user@example.com', 'John Doe')
    ->cc('manager@example.com')
    ->bcc('archive@example.com')
    ->from('noreply@example.com', 'My App')
    ->replyTo('support@example.com')
    ->subject('Welcome to Our App!')
    ->html('<h1>Welcome!</h1><p>Thanks for signing up.</p>')
    ->text('Welcome! Thanks for signing up.')
    ->attach('/path/to/file.pdf', 'Document.pdf')
    ->embed('/path/to/logo.png', 'logo')  // Use in HTML as src="cid:logo"
    ->priority(1)
    ->header('X-Custom-Header', 'value');
```

### Address Value Object
```php
readonly class Address
{
    public function __construct(
        public string $email,
        public ?string $name = null,
    ) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw MessageException::invalidEmailAddress($email);
        }
    }

    // Formats as "John Doe <john@example.com>" or "john@example.com"
    public function toString(): string;
}
```

### Attachment Value Object
```php
readonly class Attachment
{
    public static function fromPath(string $path, ?string $name = null, ?string $mimeType = null): self;
    public static function fromContent(string $content, string $name, ?string $mimeType = null): self;
    public static function inline(string $path, string $contentId, ?string $mimeType = null): self;
}
```

### SmtpTransport
Handles low-level SMTP protocol:
- Connect with optional SSL/TLS
- EHLO handshake
- STARTTLS negotiation
- Authentication (LOGIN, PLAIN)
- MAIL FROM / RCPT TO / DATA commands
- Proper response code handling

### Exception Classes
```php
// MailException - base exception
class MailException extends MarkoException
{
    public static function noDriverInstalled(): self;
    public static function configFileNotFound(string $path): self;
}

// TransportException - delivery failures
class TransportException extends MailException
{
    public static function connectionFailed(string $host, int $port, string $error): self;
    public static function tlsFailed(string $host): self;
    public static function authenticationFailed(string $username): self;
    public static function unexpectedResponse(string $response): self;
}

// MessageException - message building errors
class MessageException extends MailException
{
    public static function invalidEmailAddress(string $email): self;
    public static function attachmentNotFound(string $path): self;
    public static function noRecipients(): self;
}
```

### Driver Conflict Handling
```
BindingConflictException: Multiple implementations bound for MailerInterface.

Context: Both SmtpMailer and MailgunMailer are attempting to bind.

Suggestion: Install only one mail driver package. Remove one with:
  composer remove marko/mail-smtp
  or
  composer remove marko/mail-mailgun
```

### No Driver Installed Handling
```
MailException: No mail driver installed.

Context: Attempted to resolve MailerInterface but no implementation is bound.

Suggestion: Install a mail driver package:
  composer require marko/mail-smtp
```

### CLI Commands

**mail:test**
```
$ marko mail:test user@example.com
Sending test email to user@example.com...
✓ Email sent successfully!

$ marko mail:test user@example.com --subject="Custom Subject"
Sending test email to user@example.com...
✓ Email sent successfully!
```

### Module Bindings

**mail/module.php**
```php
return [
    'enabled' => true,
    'bindings' => [
        MailConfig::class => MailConfig::class,
    ],
];
```

**mail-smtp/module.php**
```php
return [
    'enabled' => true,
    'bindings' => [
        SmtpConfig::class => SmtpConfig::class,
        MailerInterface::class => function (ContainerInterface $container): MailerInterface {
            return $container->get(SmtpMailerFactory::class)->create();
        },
    ],
];
```

### Usage Examples

**Basic Email:**
```php
$message = Message::create()
    ->to($user->email, $user->name)
    ->subject('Welcome!')
    ->html('<h1>Welcome, ' . htmlspecialchars($user->name) . '!</h1>')
    ->text('Welcome, ' . $user->name . '!');

$mailer->send($message);
```

**With Attachments:**
```php
$message = Message::create()
    ->to('client@example.com')
    ->subject('Your Invoice')
    ->html('<p>Please find your invoice attached.</p>')
    ->attach('/path/to/invoice.pdf', 'Invoice-2026-001.pdf');

$mailer->send($message);
```

**With Inline Images:**
```php
$message = Message::create()
    ->to('user@example.com')
    ->subject('Check out our logo!')
    ->html('<p>Here is our logo: <img src="cid:logo"></p>')
    ->embed('/path/to/logo.png', 'logo');

$mailer->send($message);
```

## Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| **SMTP connection failures** | Clear error messages with actionable suggestions; timeout configuration |
| **TLS/SSL compatibility** | Support both STARTTLS and implicit SSL; clear error when negotiation fails |
| **Authentication failures** | Support LOGIN and PLAIN auth modes; clear credential error messages |
| **Large attachments** | Document size limits; stream-based processing for memory efficiency in future |
| **Email encoding issues** | Use quoted-printable for body, base64 for attachments; proper UTF-8 handling |
| **MIME format correctness** | Follow RFC 2045-2049; comprehensive testing with various email clients |
| **View package not installed** | Optional integration with clear error when view features used without package |
| **Message validation** | Validate addresses on construction; throw early with clear errors |
