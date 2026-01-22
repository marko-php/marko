<?php

declare(strict_types=1);

use Marko\Session\Exceptions\SessionException;
use Marko\Session\Exceptions\SessionNotStartedException;

it('extends SessionException', function () {
    $exception = SessionNotStartedException::forOperation('get');

    expect($exception)->toBeInstanceOf(SessionException::class);
});

it('includes operation in message', function () {
    $exception = SessionNotStartedException::forOperation('get');

    expect($exception->getMessage())->toBe("Session not started: Cannot perform 'get'");
});

it('includes context', function () {
    $exception = SessionNotStartedException::forOperation('set');

    expect($exception->getContext())->toBe('Attempted to access session before calling start()');
});

it('includes suggestion', function () {
    $exception = SessionNotStartedException::forOperation('remove');

    expect($exception->getSuggestion())->toBe('Call $session->start() before accessing session data');
});
