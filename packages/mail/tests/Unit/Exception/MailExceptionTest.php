<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Marko\Mail\Exception\MailException;

test('MailException::noDriverInstalled() method is removed', function () {
    expect(method_exists(MailException::class, 'noDriverInstalled'))->toBeFalse();
});

test('MailException has configFileNotFound factory method', function () {
    $exception = MailException::configFileNotFound('/path/to/config/mail.php');

    expect($exception)
        ->toBeInstanceOf(MailException::class)
        ->toBeInstanceOf(MarkoException::class)
        ->getMessage()->toBe('Mail configuration file not found.')
        ->getContext()->toBe('Expected configuration file at: /path/to/config/mail.php')
        ->getSuggestion()->toBe('Create a mail.php configuration file or publish the default config.');
});
