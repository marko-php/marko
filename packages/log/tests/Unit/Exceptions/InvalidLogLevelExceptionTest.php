<?php

declare(strict_types=1);

use Marko\Log\Exceptions\InvalidLogLevelException;
use Marko\Log\Exceptions\LogException;

it('extends LogException', function () {
    $exception = InvalidLogLevelException::forLevel('invalid');

    expect($exception)->toBeInstanceOf(LogException::class);
});

it('creates exception with helpful message', function () {
    $exception = InvalidLogLevelException::forLevel('critical2');

    expect($exception->getMessage())->toBe("Invalid log level 'critical2'");
});

it('includes level in context', function () {
    $exception = InvalidLogLevelException::forLevel('trace');

    expect($exception->getContext())->toContain('trace');
});

it('suggests valid levels', function () {
    $exception = InvalidLogLevelException::forLevel('verbose');

    expect($exception->getSuggestion())->toContain('emergency')
        ->and($exception->getSuggestion())->toContain('debug');
});
