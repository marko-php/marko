<?php

declare(strict_types=1);

use Marko\Cache\CacheItem;
use Marko\Cache\Contracts\CacheItemInterface;

it('implements CacheItemInterface', function () {
    $item = CacheItem::hit('key', 'value');

    expect($item)->toBeInstanceOf(CacheItemInterface::class);
});

it('returns key correctly', function () {
    $item = CacheItem::hit('my-key', 'value');

    expect($item->getKey())->toBe('my-key');
});

it('returns value correctly', function () {
    $item = CacheItem::hit('key', 'my-value');

    expect($item->get())->toBe('my-value');
});

it('returns isHit true for hit', function () {
    $item = CacheItem::hit('key', 'value');

    expect($item->isHit())->toBeTrue();
});

it('returns isHit false for miss', function () {
    $item = CacheItem::miss('key');

    expect($item->isHit())->toBeFalse();
});

it('returns null value for miss', function () {
    $item = CacheItem::miss('key');

    expect($item->get())->toBeNull();
});

it('returns expiresAt when set', function () {
    $expiresAt = new DateTimeImmutable('+1 hour');
    $item = CacheItem::hit('key', 'value', $expiresAt);

    expect($item->expiresAt())->toBe($expiresAt);
});

it('returns null expiresAt when not set', function () {
    $item = CacheItem::hit('key', 'value');

    expect($item->expiresAt())->toBeNull();
});

it('stores complex values correctly', function () {
    $value = ['nested' => ['data' => 123]];
    $item = CacheItem::hit('key', $value);

    expect($item->get())->toBe($value);
});

it('stores objects correctly', function () {
    $object = new stdClass();
    $object->name = 'test';
    $item = CacheItem::hit('key', $object);

    expect($item->get())->toEqual($object);
});

it('stores null value correctly', function () {
    $item = CacheItem::hit('key', null);

    expect($item->get())->toBeNull()
        ->and($item->isHit())->toBeTrue();
});
