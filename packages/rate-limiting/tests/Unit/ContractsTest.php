<?php

declare(strict_types=1);

use Marko\RateLimiting\Contracts\RateLimiterInterface;
use Marko\RateLimiting\RateLimiter;

describe('RateLimiterInterface', function (): void {
    it('defines attempt, tooManyAttempts, and clear methods', function (): void {
        $reflection = new ReflectionClass(RateLimiterInterface::class);

        expect($reflection->isInterface())->toBeTrue()
            ->and($reflection->hasMethod('attempt'))->toBeTrue()
            ->and($reflection->hasMethod('tooManyAttempts'))->toBeTrue()
            ->and($reflection->hasMethod('clear'))->toBeTrue();
    });

    it('is implemented by RateLimiter', function (): void {
        $reflection = new ReflectionClass(RateLimiter::class);

        expect($reflection->implementsInterface(RateLimiterInterface::class))->toBeTrue();
    });
});
