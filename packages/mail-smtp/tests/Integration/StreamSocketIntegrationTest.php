<?php

declare(strict_types=1);

namespace Marko\Mail\Smtp\Tests\Integration;

use Marko\Mail\Smtp\StreamSocket;

it(
    'opens, reads the greeting from, and closes a real TCP stream against a reachable SMTP endpoint',
    function (): void {
        // gmail SMTP as a reliable reachable endpoint
        $socket = new StreamSocket();
        $socket->connect('smtp.gmail.com', 25);

        expect($socket->connected)->toBeTrue();

        $greeting = $socket->read();

        expect($greeting)->toContain('220');

        $socket->close();

        expect($socket->connected)->toBeFalse();
    },
)->group('integration-destructive');
