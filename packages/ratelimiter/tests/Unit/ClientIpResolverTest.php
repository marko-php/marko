<?php

declare(strict_types=1);

use Marko\RateLimiter\ClientIpResolver;
use Marko\RateLimiter\Exceptions\ClientIpException;
use Marko\Routing\Http\Request;
use Marko\Testing\Fake\FakeConfigRepository;

function createResolver(array $trustedProxies = []): ClientIpResolver
{
    $config = new FakeConfigRepository([
        'ratelimiter.trusted_proxies' => $trustedProxies,
    ]);

    return new ClientIpResolver($config);
}

describe('ClientIpResolver', function (): void {
    it('resolves the client IP from REMOTE_ADDR when no proxies are trusted', function (): void {
        $resolver = createResolver([]);
        $request = new Request(server: [
            'REMOTE_ADDR' => '1.2.3.4',
            'HTTP_X_FORWARDED_FOR' => '9.9.9.9',
        ]);

        expect($resolver->resolve($request))->toBe('1.2.3.4');
    });

    it('ignores a forged X-Forwarded-For when REMOTE_ADDR is not a trusted proxy', function (): void {
        $resolver = createResolver(['10.0.0.1']);
        $request = new Request(server: [
            'REMOTE_ADDR' => '5.5.5.5',
            'HTTP_X_FORWARDED_FOR' => '9.9.9.9',
        ]);

        expect($resolver->resolve($request))->toBe('5.5.5.5');
    });

    it('honors X-Forwarded-For only when REMOTE_ADDR is in trusted_proxies', function (): void {
        $resolver = createResolver(['10.0.0.1']);
        $request = new Request(server: [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.50',
        ]);

        expect($resolver->resolve($request))->toBe('203.0.113.50');
    });

    it('resolves the right-most untrusted hop from a multi-entry X-Forwarded-For chain', function (): void {
        // XFF chain: client, proxy1, proxy2 (right to left: proxy2 connects to REMOTE_ADDR)
        // REMOTE_ADDR is proxy2 (trusted), proxy1 is also trusted, so real client is the first entry
        $resolver = createResolver(['10.0.0.1', '10.0.0.2']);
        $request = new Request(server: [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.50, 10.0.0.2',
        ]);

        expect($resolver->resolve($request))->toBe('203.0.113.50');
    });

    it('skips a forged non-IP X-Forwarded-For entry', function (): void {
        // Attacker injects a garbage value at the left; skip it and use REMOTE_ADDR
        // since with trusted proxy 10.0.0.1 and XFF of "garbage, 10.0.0.1",
        // walk from right: 10.0.0.1 is trusted (skip), then "garbage" is invalid (skip)
        // → fall back to REMOTE_ADDR
        $resolver = createResolver(['10.0.0.1']);
        $request = new Request(server: [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_FOR' => 'not-an-ip, garbage-value',
        ]);

        expect($resolver->resolve($request))->toBe('10.0.0.1');
    });

    it('matches an IPv6 REMOTE_ADDR against a trusted_proxies entry', function (): void {
        $resolver = createResolver(['::1']);
        $request = new Request(server: [
            'REMOTE_ADDR' => '::1',
            'HTTP_X_FORWARDED_FOR' => '2001:db8::1',
        ]);

        expect($resolver->resolve($request))->toBe('2001:db8::1');
    });

    it('fails closed (no shared global key) when REMOTE_ADDR is absent', function (): void {
        $resolver = createResolver([]);
        $request = new Request();

        expect(fn () => $resolver->resolve($request))
            ->toThrow(ClientIpException::class);
    });

    it('never returns a shared constant key when the client IP is resolvable', function (): void {
        $resolver = createResolver([]);
        $request = new Request(server: ['REMOTE_ADDR' => '203.0.113.1']);

        $resolved = $resolver->resolve($request);

        expect($resolved)->not->toBe('unknown')
            ->and($resolved)->toBe('203.0.113.1');
    });

    it('derives distinct rate-limit keys for two clients behind different REMOTE_ADDR values', function (): void {
        $resolver = createResolver([]);
        $requestA = new Request(server: ['REMOTE_ADDR' => '1.2.3.4']);
        $requestB = new Request(server: ['REMOTE_ADDR' => '5.6.7.8']);

        expect($resolver->resolve($requestA))->not->toBe($resolver->resolve($requestB))
            ->and($resolver->resolve($requestA))->toBe('1.2.3.4')
            ->and($resolver->resolve($requestB))->toBe('5.6.7.8');
    });

    it('defaults trusted_proxies to an empty list from config', function (): void {
        $configFile = require __DIR__ . '/../../config/ratelimiter.php';

        expect($configFile)->toHaveKey('trusted_proxies')
            ->and($configFile['trusted_proxies'])->toBeEmpty();
    });
});
