<?php

declare(strict_types=1);

namespace Marko\RateLimiter;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\RateLimiter\Exceptions\ClientIpException;
use Marko\RateLimiter\Support\IpAddress;
use Marko\Routing\Http\Request;

readonly class ClientIpResolver
{
    public function __construct(
        private ConfigRepositoryInterface $configRepository,
    ) {}

    /**
     * Resolve the real client IP from the request.
     *
     * When REMOTE_ADDR is in the trusted_proxies list (an exact address or a
     * CIDR range), the right-most untrusted hop from the X-Forwarded-For chain
     * is used. Otherwise, REMOTE_ADDR is used directly and X-Forwarded-For is
     * ignored (preventing header forgery).
     *
     * @throws ClientIpException|ConfigNotFoundException
     */
    public function resolve(
        Request $request,
    ): string {
        $remoteAddr = $request->ip();

        if (
            $remoteAddr === null
            || $remoteAddr === ''
        ) {
            throw ClientIpException::missingRemoteAddr();
        }

        $trustedProxies = $this->parseTrustedProxies(
            $this->configRepository->getArray('ratelimiter.trusted_proxies'),
        );

        if ($this->isTrustedProxy($remoteAddr, $trustedProxies)) {
            $xff = $request->header('X-Forwarded-For');

            if (
                $xff !== null
                && $xff !== ''
            ) {
                $resolved = $this->resolveFromXff($xff, $trustedProxies);

                if ($resolved !== null) {
                    return $resolved;
                }
            }
        }

        return $remoteAddr;
    }

    /**
     * Parse every trusted_proxies entry up front, so a typo fails loudly on the
     * first request instead of silently never matching.
     *
     * @param array<mixed> $entries
     *
     * @return list<array{network: string, prefixLength: int}>
     *
     * @throws ClientIpException
     */
    private function parseTrustedProxies(
        array $entries,
    ): array {
        $ranges = [];

        foreach ($entries as $entry) {
            if (!is_string($entry)) {
                throw ClientIpException::invalidTrustedProxy(get_debug_type($entry));
            }

            $address = $entry;
            $prefixLength = null;

            if (str_contains($entry, '/')) {
                [$address, $prefix] = explode('/', $entry, 2);

                if (!ctype_digit($prefix)) {
                    throw ClientIpException::invalidTrustedProxy($entry);
                }

                $prefixLength = (int) $prefix;
            }

            $network = IpAddress::pack(trim($address));

            if ($network === null) {
                throw ClientIpException::invalidTrustedProxy($entry);
            }

            $maxPrefixLength = strlen($network) * 8;

            // An IPv4-mapped network (::ffff:10.0.0.0/104) packs as IPv4, so its
            // prefix length shifts down by the 96 bits of the mapping prefix.
            if (
                $prefixLength !== null
                && $maxPrefixLength === 32
                && str_contains($address, ':')
            ) {
                $prefixLength -= 96;
            }

            $prefixLength ??= $maxPrefixLength;

            if (
                $prefixLength < 0
                || $prefixLength > $maxPrefixLength
            ) {
                throw ClientIpException::invalidTrustedProxy($entry);
            }

            $ranges[] = ['network' => $network, 'prefixLength' => $prefixLength];
        }

        return $ranges;
    }

    /**
     * @param list<array{network: string, prefixLength: int}> $trustedProxies
     */
    private function isTrustedProxy(
        string $ip,
        array $trustedProxies,
    ): bool {
        $packed = IpAddress::pack($ip);

        if ($packed === null) {
            return false;
        }

        return array_any(
            $trustedProxies,
            fn (array $range): bool => IpAddress::inRange($packed, $range['network'], $range['prefixLength']),
        );
    }

    /**
     * Walk the XFF chain from right to left, skipping trusted proxies,
     * and return the right-most untrusted IP.
     *
     * @param list<array{network: string, prefixLength: int}> $trustedProxies
     */
    private function resolveFromXff(
        string $xff,
        array $trustedProxies,
    ): ?string {
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
}
