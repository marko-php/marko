<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Marko\Mail\Exception\MailException;

test('MailException has noDriverInstalled factory method', function () {
    $exception = MailException::noDriverInstalled();

    expect($exception)
        ->toBeInstanceOf(MailException::class)
        ->toBeInstanceOf(MarkoException::class)
        ->getMessage()->toBe('No mail driver installed.')
        ->getContext()->toBe('Attempted to resolve MailerInterface but no implementation is bound.')
        ->getSuggestion()->toBe('Install a mail driver package: composer require marko/mail-smtp');
});

test('MailException has configFileNotFound factory method', function () {
    $exception = MailException::configFileNotFound('/path/to/config/mail.php');

    expect($exception)
        ->toBeInstanceOf(MailException::class)
        ->getMessage()->toBe('Mail configuration file not found.')
        ->getContext()->toBe('Expected configuration file at: /path/to/config/mail.php')
        ->getSuggestion()->toBe('Create a mail.php configuration file or publish the default config.');
});
