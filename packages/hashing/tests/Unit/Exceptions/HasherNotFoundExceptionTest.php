<?php

declare(strict_types=1);

use Marko\Hashing\Exceptions\HasherException;
use Marko\Hashing\Exceptions\HasherNotFoundException;

it('extends HasherException', function () {
    $exception = HasherNotFoundException::forName('unknown');

    expect($exception)->toBeInstanceOf(HasherException::class);
});

it('creates exception with helpful message', function () {
    $exception = HasherNotFoundException::forName('md5');

    expect($exception->getMessage())->toBe("Hasher 'md5' not found");
});

it('includes hasher name in context', function () {
    $exception = HasherNotFoundException::forName('sha256');

    expect($exception->getContext())->toContain('sha256');
});

it('provides suggestion to check config', function () {
    $exception = HasherNotFoundException::forName('custom');

    expect($exception->getSuggestion())->toContain('config/hashing.php');
});

it('suggests checking spelling', function () {
    $exception = HasherNotFoundException::forName('bcrpyt');

    expect($exception->getSuggestion())->toContain('spelling');
});
