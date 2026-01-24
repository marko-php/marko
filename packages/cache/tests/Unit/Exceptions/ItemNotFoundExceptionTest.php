<?php

declare(strict_types=1);

use Marko\Cache\Exceptions\CacheException;
use Marko\Cache\Exceptions\ItemNotFoundException;

it('extends CacheException', function () {
    $exception = ItemNotFoundException::forKey('my-key');

    expect($exception)->toBeInstanceOf(CacheException::class);
});

it('creates exception with key in message', function () {
    $exception = ItemNotFoundException::forKey('my-key');

    expect($exception->getMessage())->toBe("Cache item not found: 'my-key'");
});

it('creates exception with context', function () {
    $exception = ItemNotFoundException::forKey('user.123');

    expect($exception->getContext())->toBe('Requested key: user.123');
});

it('creates exception with suggestion', function () {
    $exception = ItemNotFoundException::forKey('my-key');

    expect($exception->getSuggestion())->toBe(
        'Use CacheInterface::has() to check if an item exists before accessing it',
    );
});
