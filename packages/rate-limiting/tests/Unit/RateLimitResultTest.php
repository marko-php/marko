<?php

declare(strict_types=1);

use Marko\RateLimiting\RateLimitResult;

describe('RateLimitResult', function (): void {
    it('creates allowed result with remaining attempts', function (): void {
        $result = new RateLimitResult(
            allowed: true,
            remaining: 5,
        );

        expect($result->allowed())->toBeTrue()
            ->and($result->remaining())->toBe(5)
            ->and($result->retryAfter())->toBeNull();
    });

    it('creates blocked result with retry after', function (): void {
        $result = new RateLimitResult(
            allowed: false,
            remaining: 0,
            retryAfter: 30,
        );

        expect($result->allowed())->toBeFalse()
            ->and($result->remaining())->toBe(0)
            ->and($result->retryAfter())->toBe(30);
    });

    it('defaults retry after to null', function (): void {
        $result = new RateLimitResult(
            allowed: true,
            remaining: 10,
        );

        expect($result->retryAfter())->toBeNull();
    });
});
