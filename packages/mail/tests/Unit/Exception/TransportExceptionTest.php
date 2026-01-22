<?php

declare(strict_types=1);

use Marko\Mail\Exception\MailException;
use Marko\Mail\Exception\TransportException;

test('TransportException has connectionFailed factory method', function () {
    $exception = TransportException::connectionFailed('smtp.example.com', 587);

    expect($exception)
        ->toBeInstanceOf(TransportException::class)
        ->toBeInstanceOf(MailException::class)
        ->getMessage()->toBe('Failed to connect to mail server.')
        ->getContext()->toBe('Could not establish connection to smtp.example.com:587')
        ->getSuggestion()->toBe('Verify the server address and port are correct and the server is reachable.');
});

test('TransportException has tlsFailed factory method', function () {
    $exception = TransportException::tlsFailed('smtp.example.com');

    expect($exception)
        ->toBeInstanceOf(TransportException::class)
        ->getMessage()->toBe('TLS negotiation failed.')
        ->getContext()->toBe('Could not establish secure connection to smtp.example.com')
        ->getSuggestion()->toBe('Check server TLS configuration or try disabling TLS verification for testing.');
});

test('TransportException has authenticationFailed factory method', function () {
    $exception = TransportException::authenticationFailed('user@example.com');

    expect($exception)
        ->toBeInstanceOf(TransportException::class)
        ->getMessage()->toBe('SMTP authentication failed.')
        ->getContext()->toBe('Failed to authenticate as user@example.com')
        ->getSuggestion()->toBe('Verify your username and password are correct.');
});

test('TransportException has unexpectedResponse factory method', function () {
    $exception = TransportException::unexpectedResponse(550, '5.1.1 Mailbox not found');

    expect($exception)
        ->toBeInstanceOf(TransportException::class)
        ->getMessage()->toBe('Unexpected SMTP response.')
        ->getContext()->toBe('Server responded with code 550: 5.1.1 Mailbox not found')
        ->getSuggestion()->toBe('Check the recipient address and server error message for details.');
});
