<?php

declare(strict_types=1);

use Marko\Log\Exceptions\LogException;
use Marko\Log\Exceptions\LogWriteException;

it('extends LogException', function () {
    $exception = LogWriteException::forPath('/var/log/app.log');

    expect($exception)->toBeInstanceOf(LogException::class);
});

it('creates exception for path', function () {
    $exception = LogWriteException::forPath('/var/log/app.log');

    expect($exception->getMessage())->toBe('Failed to write to log file')
        ->and($exception->getContext())->toContain('/var/log/app.log');
});

it('includes reason when provided', function () {
    $exception = LogWriteException::forPath('/var/log/app.log', 'Permission denied');

    expect($exception->getContext())->toContain('Permission denied');
});

it('provides suggestion to check directory', function () {
    $exception = LogWriteException::forPath('/var/log/app.log');

    expect($exception->getSuggestion())->toContain('writable');
});

it('creates exception for non-writable directory', function () {
    $exception = LogWriteException::directoryNotWritable('/var/log');

    expect($exception->getMessage())->toBe('Log directory is not writable')
        ->and($exception->getContext())->toContain('/var/log')
        ->and($exception->getSuggestion())->toContain('chmod');
});
