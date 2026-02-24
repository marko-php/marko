<?php

declare(strict_types=1);

use Marko\Mail\Contracts\MailerInterface;
use Marko\Mail\Message;
use Marko\Testing\Exceptions\AssertionFailedException;
use Marko\Testing\Fake\FakeMailer;

it('implements MailerInterface', function () {
    $mailer = new FakeMailer();

    expect($mailer)->toBeInstanceOf(MailerInterface::class);
});

it('captures sent messages in memory and returns true', function () {
    $mailer = new FakeMailer();
    $message = Message::create()->to('test@example.com')->subject('Hello');

    $result = $mailer->send($message);

    expect($result)->toBeTrue()
        ->and($mailer->sent)->toHaveCount(1)
        ->and($mailer->sent[0])->toBe($message);
});

it('returns all sent messages', function () {
    $mailer = new FakeMailer();
    $message1 = Message::create()->to('a@example.com')->subject('First');
    $message2 = Message::create()->to('b@example.com')->subject('Second');

    $mailer->send($message1);
    $mailer->send($message2);

    expect($mailer->sent)->toHaveCount(2)
        ->and($mailer->sent[0])->toBe($message1)
        ->and($mailer->sent[1])->toBe($message2);
});

it('asserts message was sent', function () {
    $mailer = new FakeMailer();
    $message = Message::create()->to('test@example.com')->subject('Hello');
    $mailer->send($message);

    expect(fn () => $mailer->assertSent())->not->toThrow(AssertionFailedException::class);
});

it('throws AssertionFailedException when asserting sent message that was not sent', function () {
    $mailer = new FakeMailer();

    expect(fn () => $mailer->assertSent())->toThrow(AssertionFailedException::class);
});

it('asserts no messages were sent', function () {
    $mailer = new FakeMailer();
    $mailer->assertNothingSent();

    expect($mailer->sent)->toBeEmpty();
});

it('asserts sent count', function () {
    $mailer = new FakeMailer();
    $mailer->send(Message::create()->to('a@example.com')->subject('First'));
    $mailer->send(Message::create()->to('b@example.com')->subject('Second'));

    expect(fn () => $mailer->assertSentCount(2))->not->toThrow(AssertionFailedException::class)
        ->and(fn () => $mailer->assertSentCount(1))->toThrow(AssertionFailedException::class);
});

it('clears all captured messages', function () {
    $mailer = new FakeMailer();
    $mailer->send(Message::create()->to('a@example.com')->subject('Hello'));
    $mailer->sendRaw('b@example.com', 'raw message');

    $mailer->clear();

    expect($mailer->sent)->toBeEmpty()
        ->and($mailer->sentRaw)->toBeEmpty();
});

it('asserts message was sent matching a callback filter', function () {
    $mailer = new FakeMailer();
    $mailer->send(Message::create()->to('a@example.com')->subject('Welcome'));
    $mailer->send(Message::create()->to('b@example.com')->subject('Goodbye'));

    expect(fn () => $mailer->assertSent(fn (Message $m) => $m->subject === 'Welcome'))
        ->not->toThrow(AssertionFailedException::class);
});

it('captures raw messages in memory and returns true', function () {
    $mailer = new FakeMailer();

    $result = $mailer->sendRaw('test@example.com', 'Subject: Test\r\n\r\nBody');

    expect($result)->toBeTrue()
        ->and($mailer->sentRaw)->toHaveCount(1)
        ->and($mailer->sentRaw[0])->toBe(['to' => 'test@example.com', 'raw' => 'Subject: Test\r\n\r\nBody']);
});
