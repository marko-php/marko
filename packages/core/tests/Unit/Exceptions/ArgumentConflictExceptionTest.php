<?php

declare(strict_types=1);

use Marko\Core\Exceptions\ArgumentConflictException;
use Marko\Core\Exceptions\MarkoException;

it('lists both modules when two set the same argument at the same priority', function (): void {
    $exception = ArgumentConflictException::multipleModules('App\Mailer', 'host', ['vendor/a', 'vendor/b']);

    expect($exception)
        ->toBeInstanceOf(MarkoException::class)
        ->and($exception->getMessage())
        ->toContain('App\Mailer')
        ->toContain('host')
        ->toContain('vendor/a')
        ->toContain('vendor/b')
        ->and($exception->getSuggestion())
        ->toContain('higher-priority module');
});
