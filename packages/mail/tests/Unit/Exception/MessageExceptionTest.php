<?php

declare(strict_types=1);

use Marko\Mail\Exception\MailException;
use Marko\Mail\Exception\MessageException;

test('MessageException has invalidEmailAddress factory method', function () {
    $exception = MessageException::invalidEmailAddress('not-an-email');

    expect($exception)
        ->toBeInstanceOf(MessageException::class)
        ->toBeInstanceOf(MailException::class)
        ->getMessage()->toBe('Invalid email address.')
        ->getContext()->toBe('The address "not-an-email" is not a valid email address.')
        ->getSuggestion()->toBe('Provide a valid email address in the format: name@example.com');
});

test('MessageException has attachmentNotFound factory method', function () {
    $exception = MessageException::attachmentNotFound('/path/to/missing/file.pdf');

    expect($exception)
        ->toBeInstanceOf(MessageException::class)
        ->getMessage()->toBe('Attachment file not found.')
        ->getContext()->toBe('Could not find file at: /path/to/missing/file.pdf')
        ->getSuggestion()->toBe('Verify the file path is correct and the file exists.');
});

test('MessageException has noRecipients factory method', function () {
    $exception = MessageException::noRecipients();

    expect($exception)
        ->toBeInstanceOf(MessageException::class)
        ->getMessage()->toBe('No recipients specified.')
        ->getContext()->toBe('Message has no To, Cc, or Bcc recipients.')
        ->getSuggestion()->toBe('Add at least one recipient using to(), cc(), or bcc().');
});
