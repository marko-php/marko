<?php

declare(strict_types=1);

namespace Marko\RateLimiter;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\RateLimiter\Exceptions\ClientIpException;
use Marko\Routing\Http\Request;

readonly class ClientIpResolver
{
    public function __construct(
        private ConfigRepositoryInterface $configRepository,
    ) {}

    /**
     * Resolve the real client IP from the request.
     *
     * When REMOTE_ADDR is in the trusted_proxies list, the right-most untrusted
     * hop from the X-Forwarded-For chain is used. Otherwise, REMOTE_ADDR is used
     * directly and X-Forwarded-For is ignored (preventing header forgery).
     *
     * @throws ClientIpException|ConfigNotFoundException
     */
    public function resolve(Request $request): string
    {
        $remoteAddr = $request->ip();

        if ($remoteAddr === null || $remoteAddr === '') {
            throw ClientIpException::missingRemoteAddr();
        }

        $trustedProxies = $this->configRepository->getArray('ratelimiter.trusted_proxies');

        if ($this->isTrustedProxy($remoteAddr, $trustedProxies)) {
            $xff = $request->header('X-Forwarded-For');

            if ($xff !== null && $xff !== '') {
                $resolved = $this->resolveFromXff($xff, $trustedProxies);

                if ($resolved !== null) {
                    return $resolved;
                }
            }
        }

        return $remoteAddr;
    }

    /**
     * @param array<string> $trustedProxies
     */
    private function isTrustedProxy(
        string $ip,
        array $trustedProxies,
    ): bool
    {
        $normalized = $this->normalizeIp($ip);

        return array_any($trustedProxies, fn (string $proxy) => $this->normalizeIp($proxy) === $normalized);
    }

    /**
     * Walk the XFF chain from right to left, skipping trusted proxies,
     * and return the right-most untrusted IP.
     *
     * @param  array<string> $trustedProxies
     */
    private function resolveFromXff(
        string $xff,
        array $trustedProxies,
    ): ?string
    {
        $entries = array_reverse(array_map('trim', explode(',', $xff)));

        foreach ($entries as $entry) {
            if (!filter_var($entry, FILTER_VALIDATE_IP)) {
                continue;
            }

            if (!$this->isTrustedProxy($entry, $trustedProxies)) {
                return $entry;
            }
        }

        return null;
    }

    private function normalizeIp(string $ip): string
    {
        // For IPv6, normalize to lowercase compact form
        if (str_contains($ip, ':')) {
            $packed = inet_pton($ip);

            if ($packed === false) {
                return $ip;
            }

            return inet_ntop($packed) ?: $ip;
        }

        return $ip;
    }
}
