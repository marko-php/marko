<?php

declare(strict_types=1);

use Marko\Cache\CacheOperations;
use Marko\Cache\Tests\Support\RecordingCache;

describe('CacheOperations', function (): void {
    beforeEach(function (): void {
        $this->operations = new CacheOperations();
        $this->cache = new RecordingCache($this->operations);
    });

    it('calls the remember callback on a miss and stores its result with the given ttl', function (): void {
        $calls = 0;

        $value = $this->operations->remember($this->cache, 'key', 60, function () use (&$calls): string {
            $calls++;

            return 'computed';
        });

        expect($value)->toBe('computed')
            ->and($calls)->toBe(1)
            ->and($this->cache->sets)->toBe([['key' => 'key', 'value' => 'computed', 'ttl' => 60]]);
    });

    it('returns the cached value from remember without calling the callback on a hit', function (): void {
        $this->cache->values['key'] = 'cached';
        $calls = 0;

        $value = $this->operations->remember($this->cache, 'key', 60, function () use (&$calls): string {
            $calls++;

            return 'computed';
        });

        expect($value)->toBe('cached')
            ->and($calls)->toBe(0)
            ->and($this->cache->sets)->toBe([]);
    });

    it('returns a cached null from remember without calling the callback', function (): void {
        $this->cache->values['key'] = null;
        $calls = 0;

        $value = $this->operations->remember($this->cache, 'key', 60, function () use (&$calls): string {
            $calls++;

            return 'computed';
        });

        expect($value)->toBeNull()
            ->and($calls)->toBe(0)
            ->and($this->cache->sets)->toBe([]);
    });

    it('passes a null remember ttl through so the default ttl applies', function (): void {
        $this->operations->remember($this->cache, 'key', null, fn (): int => 1);

        expect($this->cache->sets)->toBe([['key' => 'key', 'value' => 1, 'ttl' => null]]);
    });

    it('stores a null computed by the remember callback so later calls hit', function (): void {
        $calls = 0;
        $callback = function () use (&$calls): null {
            $calls++;

            return null;
        };

        $this->operations->remember($this->cache, 'key', 60, $callback);
        $this->operations->remember($this->cache, 'key', 60, $callback);

        expect($calls)->toBe(1);
    });

    it('returns the value from pull and deletes it', function (): void {
        $this->cache->values['key'] = 'value';

        expect($this->operations->pull($this->cache, 'key', 'default'))->toBe('value')
            ->and($this->cache->has('key'))->toBeFalse()
            ->and($this->cache->deletes)->toBe(['key']);
    });

    it('returns the default from pull on a miss', function (): void {
        expect($this->operations->pull($this->cache, 'missing', 'default'))->toBe('default');
    });

    it('stores the value with no expiry for forever', function (): void {
        expect($this->operations->forever($this->cache, 'key', 'value'))->toBeTrue()
            ->and($this->cache->sets)->toBe([['key' => 'key', 'value' => 'value', 'ttl' => 0]]);
    });
});
