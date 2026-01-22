<?php

declare(strict_types=1);

use Marko\Mail\Address;
use Marko\Mail\Attachment;
use Marko\Mail\Message;

describe('Message', function (): void {
    it('create returns new instance', function (): void {
        $message = Message::create();

        expect($message)->toBeInstanceOf(Message::class);
    });

    it('getters return correct data', function (): void {
        $message = Message::create();

        expect($message->getTo())->toBe([])
            ->and($message->getCc())->toBe([])
            ->and($message->getBcc())->toBe([])
            ->and($message->getFrom())->toBeNull()
            ->and($message->getReplyTo())->toBeNull()
            ->and($message->getSubject())->toBeNull()
            ->and($message->getHtml())->toBeNull()
            ->and($message->getText())->toBeNull()
            ->and($message->getAttachments())->toBe([])
            ->and($message->getHeaders())->toBe([])
            ->and($message->getPriority())->toBeNull()
            ->and($message->getView())->toBeNull()
            ->and($message->getViewData())->toBe([]);
    });

    it('to adds recipient', function (): void {
        $message = Message::create()
            ->to('user@example.com', 'John Doe');

        $recipients = $message->getTo();

        expect($recipients)->toHaveCount(1)
            ->and($recipients[0])->toBeInstanceOf(Address::class)
            ->and($recipients[0]->email)->toBe('user@example.com')
            ->and($recipients[0]->name)->toBe('John Doe');
    });

    it('to accepts multiple recipients', function (): void {
        $message = Message::create()
            ->to('user1@example.com', 'User One')
            ->to('user2@example.com', 'User Two');

        $recipients = $message->getTo();

        expect($recipients)->toHaveCount(2)
            ->and($recipients[0]->email)->toBe('user1@example.com')
            ->and($recipients[1]->email)->toBe('user2@example.com');
    });

    it('cc adds copy recipient', function (): void {
        $message = Message::create()
            ->cc('manager@example.com', 'Manager');

        $recipients = $message->getCc();

        expect($recipients)->toHaveCount(1)
            ->and($recipients[0])->toBeInstanceOf(Address::class)
            ->and($recipients[0]->email)->toBe('manager@example.com')
            ->and($recipients[0]->name)->toBe('Manager');
    });

    it('supports multiple cc addresses', function (): void {
        $message = Message::create()
            ->cc('manager1@example.com', 'Manager One')
            ->cc('manager2@example.com', 'Manager Two')
            ->cc('manager3@example.com');

        $recipients = $message->getCc();

        expect($recipients)->toHaveCount(3)
            ->and($recipients[0]->email)->toBe('manager1@example.com')
            ->and($recipients[0]->name)->toBe('Manager One')
            ->and($recipients[1]->email)->toBe('manager2@example.com')
            ->and($recipients[1]->name)->toBe('Manager Two')
            ->and($recipients[2]->email)->toBe('manager3@example.com')
            ->and($recipients[2]->name)->toBeNull();
    });

    it('bcc adds blind copy recipient', function (): void {
        $message = Message::create()
            ->bcc('secret@example.com', 'Secret Recipient');

        $recipients = $message->getBcc();

        expect($recipients)->toHaveCount(1)
            ->and($recipients[0])->toBeInstanceOf(Address::class)
            ->and($recipients[0]->email)->toBe('secret@example.com')
            ->and($recipients[0]->name)->toBe('Secret Recipient');
    });

    it('supports multiple bcc addresses', function (): void {
        $message = Message::create()
            ->bcc('secret1@example.com', 'Secret One')
            ->bcc('secret2@example.com', 'Secret Two')
            ->bcc('secret3@example.com');

        $recipients = $message->getBcc();

        expect($recipients)->toHaveCount(3)
            ->and($recipients[0]->email)->toBe('secret1@example.com')
            ->and($recipients[0]->name)->toBe('Secret One')
            ->and($recipients[1]->email)->toBe('secret2@example.com')
            ->and($recipients[1]->name)->toBe('Secret Two')
            ->and($recipients[2]->email)->toBe('secret3@example.com')
            ->and($recipients[2]->name)->toBeNull();
    });

    it('from sets sender', function (): void {
        $message = Message::create()
            ->from('noreply@example.com', 'My App');

        $from = $message->getFrom();

        expect($from)->toBeInstanceOf(Address::class)
            ->and($from->email)->toBe('noreply@example.com')
            ->and($from->name)->toBe('My App');
    });

    it('replyTo sets reply address', function (): void {
        $message = Message::create()
            ->replyTo('support@example.com', 'Support Team');

        $replyTo = $message->getReplyTo();

        expect($replyTo)->toBeInstanceOf(Address::class)
            ->and($replyTo->email)->toBe('support@example.com')
            ->and($replyTo->name)->toBe('Support Team');
    });

    it('subject sets subject line', function (): void {
        $message = Message::create()
            ->subject('Welcome to My App!');

        expect($message->getSubject())->toBe('Welcome to My App!');
    });

    it('html sets HTML body', function (): void {
        $message = Message::create()
            ->html('<h1>Welcome!</h1><p>Thanks for joining.</p>');

        expect($message->getHtml())->toBe('<h1>Welcome!</h1><p>Thanks for joining.</p>');
    });

    it('text sets plain text body', function (): void {
        $message = Message::create()
            ->text('Welcome! Thanks for joining.');

        expect($message->getText())->toBe('Welcome! Thanks for joining.');
    });

    it('attach adds attachment', function (): void {
        $testFile = sys_get_temp_dir() . '/test-attachment.txt';
        file_put_contents($testFile, 'Test content');

        try {
            $message = Message::create()
                ->attach($testFile);

            $attachments = $message->getAttachments();

            expect($attachments)->toHaveCount(1)
                ->and($attachments[0])->toBeInstanceOf(Attachment::class)
                ->and($attachments[0]->name)->toBe('test-attachment.txt');
        } finally {
            unlink($testFile);
        }
    });

    it('supports multiple attachments', function (): void {
        $testFile1 = sys_get_temp_dir() . '/attachment1.txt';
        $testFile2 = sys_get_temp_dir() . '/attachment2.pdf';
        $testFile3 = sys_get_temp_dir() . '/attachment3.csv';

        file_put_contents($testFile1, 'First attachment');
        file_put_contents($testFile2, 'Second attachment');
        file_put_contents($testFile3, 'Third attachment');

        try {
            $message = Message::create()
                ->attach($testFile1)
                ->attach($testFile2, 'custom-name.pdf')
                ->attach($testFile3);

            $attachments = $message->getAttachments();

            expect($attachments)->toHaveCount(3)
                ->and($attachments[0])->toBeInstanceOf(Attachment::class)
                ->and($attachments[0]->name)->toBe('attachment1.txt')
                ->and($attachments[1])->toBeInstanceOf(Attachment::class)
                ->and($attachments[1]->name)->toBe('custom-name.pdf')
                ->and($attachments[2])->toBeInstanceOf(Attachment::class)
                ->and($attachments[2]->name)->toBe('attachment3.csv');
        } finally {
            @unlink($testFile1);
            @unlink($testFile2);
            @unlink($testFile3);
        }
    });

    it('embed adds inline attachment', function (): void {
        $testFile = sys_get_temp_dir() . '/inline-image.png';
        $pngData = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAgAAAAICAIAAABLbSncAAAADklEQVQI12P4////GQYJAAAFRgF+O+bnIwAAAABJRU5ErkJggg==',
        );
        file_put_contents($testFile, $pngData);

        try {
            $message = Message::create()
                ->embed($testFile, 'logo');

            $attachments = $message->getAttachments();

            expect($attachments)->toHaveCount(1)
                ->and($attachments[0])->toBeInstanceOf(Attachment::class)
                ->and($attachments[0]->contentId)->toBe('logo');
        } finally {
            unlink($testFile);
        }
    });

    it('header adds custom header', function (): void {
        $message = Message::create()
            ->header('X-Custom-Header', 'custom-value');

        $headers = $message->getHeaders();

        expect($headers)->toHaveKey('X-Custom-Header')
            ->and($headers['X-Custom-Header'])->toBe('custom-value');
    });

    it('supports multiple headers', function (): void {
        $message = Message::create()
            ->header('X-Mailer', 'Marko Mail')
            ->header('X-Priority', '1')
            ->header('X-Custom-ID', 'abc123');

        $headers = $message->getHeaders();

        expect($headers)->toHaveCount(3)
            ->and($headers)->toHaveKey('X-Mailer')
            ->and($headers['X-Mailer'])->toBe('Marko Mail')
            ->and($headers)->toHaveKey('X-Priority')
            ->and($headers['X-Priority'])->toBe('1')
            ->and($headers)->toHaveKey('X-Custom-ID')
            ->and($headers['X-Custom-ID'])->toBe('abc123');
    });

    it('priority sets message priority', function (): void {
        $message = Message::create()
            ->priority(1);

        expect($message->getPriority())->toBe(1);
    });

    it('methods return self for chaining', function (): void {
        $testFile = sys_get_temp_dir() . '/chain-test.txt';
        file_put_contents($testFile, 'Test content');

        try {
            $message = Message::create()
                ->to('user@example.com', 'John Doe')
                ->cc('manager@example.com')
                ->bcc('secret@example.com')
                ->from('noreply@example.com', 'My App')
                ->replyTo('support@example.com')
                ->subject('Welcome!')
                ->html('<h1>Welcome!</h1>')
                ->text('Welcome!')
                ->attach($testFile)
                ->header('X-Custom', 'value')
                ->priority(1);

            expect($message)->toBeInstanceOf(Message::class)
                ->and($message->getTo())->toHaveCount(1)
                ->and($message->getCc())->toHaveCount(1)
                ->and($message->getBcc())->toHaveCount(1)
                ->and($message->getFrom())->not->toBeNull()
                ->and($message->getReplyTo())->not->toBeNull()
                ->and($message->getSubject())->toBe('Welcome!')
                ->and($message->getHtml())->toBe('<h1>Welcome!</h1>')
                ->and($message->getText())->toBe('Welcome!')
                ->and($message->getAttachments())->toHaveCount(1)
                ->and($message->getHeaders())->toHaveKey('X-Custom')
                ->and($message->getPriority())->toBe(1);
        } finally {
            unlink($testFile);
        }
    });

    it('view method sets template', function (): void {
        $message = Message::create()
            ->view('emails.welcome');

        expect($message->getView())->toBe('emails.welcome');
    });

    it('with method sets template data', function (): void {
        $message = Message::create()
            ->view('emails.welcome')
            ->with(['name' => 'John', 'email' => 'john@example.com']);

        expect($message->getViewData())->toBe(['name' => 'John', 'email' => 'john@example.com']);
    });

    it('with method supports key-value signature', function (): void {
        $message = Message::create()
            ->view('emails.welcome')
            ->with('name', 'John')
            ->with('email', 'john@example.com');

        expect($message->getViewData())->toBe(['name' => 'John', 'email' => 'john@example.com']);
    });
});
