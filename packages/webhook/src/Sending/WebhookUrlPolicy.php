<?php

declare(strict_types=1);

namespace Marko\Webhook\Sending;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Webhook\Contracts\HostResolverInterface;
use Marko\Webhook\Contracts\WebhookUrlPolicyInterface;
use Marko\Webhook\Exceptions\UnsafeWebhookUrlException;

/**
 * The default outgoing webhook URL policy. A URL passes only when it uses https (or http
 * when webhook.allow_http is true) and every address its host resolves to is public:
 * loopback, private (RFC 1918), carrier-grade NAT, link-local (cloud metadata), unique
 * local, multicast and reserved ranges are rejected, including IPv4 addresses embedded
 * in IPv6 (IPv4-mapped, NAT64 and 6to4).
 */
readonly class WebhookUrlPolicy implements WebhookUrlPolicyInterface
{
    /** @var array<string, string> CIDR => description of the range */
    private const array BLOCKED_IPV4 = [
        '0.0.0.0/8' => 'the "this network" range (0.0.0.0/8)',
        '10.0.0.0/8' => 'a private range (10.0.0.0/8)',
        '100.64.0.0/10' => 'the carrier-grade NAT range (100.64.0.0/10)',
        '127.0.0.0/8' => 'the loopback range (127.0.0.0/8)',
        '169.254.0.0/16' => 'the link-local range (169.254.0.0/16), home of cloud metadata endpoints',
        '172.16.0.0/12' => 'a private range (172.16.0.0/12)',
        '192.0.0.0/24' => 'the IETF protocol assignments range (192.0.0.0/24)',
        '192.168.0.0/16' => 'a private range (192.168.0.0/16)',
        '198.18.0.0/15' => 'the benchmarking range (198.18.0.0/15)',
        '224.0.0.0/4' => 'the multicast range (224.0.0.0/4)',
        '240.0.0.0/4' => 'the reserved range (240.0.0.0/4)',
    ];

    /** @var array<string, string> CIDR => description of the range */
    private const array BLOCKED_IPV6 = [
        '::1/128' => 'the IPv6 loopback address (::1)',
        '::/96' => 'the unspecified and IPv4-compatible range (::/96)',
        'fc00::/7' => 'the unique local range (fc00::/7)',
        'fe80::/10' => 'the link-local range (fe80::/10)',
        'fec0::/10' => 'the site-local range (fec0::/10)',
        'ff00::/8' => 'the multicast range (ff00::/8)',
    ];

    /** @var array<string, array{offset: int, name: string}> IPv6 prefixes that embed an IPv4 address */
    private const array EMBEDDED_IPV4 = [
        '::ffff:0:0/96' => ['offset' => 12, 'name' => 'IPv4-mapped'],
        '64:ff9b::/96' => ['offset' => 12, 'name' => 'NAT64'],
        '2002::/16' => ['offset' => 2, 'name' => '6to4'],
    ];

    private bool $allowHttp;

    /**
     * @throws ConfigNotFoundException
     */
    public function __construct(
        ConfigRepositoryInterface $config,
        private HostResolverInterface $resolver,
    ) {
        $this->allowHttp = $config->getBool('webhook.allow_http');
    }

    /**
     * Returns the first address the host resolved to; every address was checked, so any of them is safe to pin.
     *
     * @throws UnsafeWebhookUrlException
     */
    public function validate(
        string $url,
    ): string {
        // Backslashes and whitespace are where URL parsers disagree, so parse_url() could see a
        // different host than the HTTP client. Such URLs are rejected rather than interpreted.
        $parts = preg_match('/[\\\\\s]/', $url) === 1 ? false : parse_url($url);

        if (
            $parts === false
            || !isset($parts['scheme'], $parts['host'])
            || $parts['host'] === ''
        ) {
            throw UnsafeWebhookUrlException::malformed($url);
        }

        $scheme = strtolower($parts['scheme']);

        if (
            $scheme !== 'https'
            && !(
                $scheme === 'http'
                && $this->allowHttp
            )
        ) {
            throw UnsafeWebhookUrlException::disallowedScheme($url, $scheme, $this->allowHttp);
        }

        $host = rtrim(strtolower(trim($parts['host'], '[]')), '.');

        if (preg_match('/^[a-z0-9.\-_:]+$/', $host) !== 1) {
            throw UnsafeWebhookUrlException::malformed($url);
        }

        if ($this->isAmbiguousNumericHost($host)) {
            throw UnsafeWebhookUrlException::ambiguousNumericHost($url, $host);
        }

        $addresses = $this->resolver->resolve($host);

        if ($addresses === []) {
            throw UnsafeWebhookUrlException::unresolvableHost($url, $host);
        }

        foreach ($addresses as $address) {
            $binary = filter_var($address, FILTER_VALIDATE_IP) !== false ? inet_pton($address) : false;

            if ($binary === false) {
                throw UnsafeWebhookUrlException::unresolvableHost($url, $host);
            }

            $range = $this->blockedRange($binary);

            if ($range !== null) {
                throw UnsafeWebhookUrlException::disallowedAddress($url, $host, $address, $range);
            }
        }

        return $addresses[0];
    }

    /**
     * Hosts like "2130706433", "0x7f.1" or "127.1" are not dotted-quad IPs, yet the system
     * resolver and HTTP clients read them as IPv4 addresses. No real TLD is numeric.
     */
    private function isAmbiguousNumericHost(
        string $host,
    ): bool {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return false;
        }

        $labels = explode('.', $host);

        return preg_match('/^(0x[0-9a-f]*|[0-9]+)$/', end($labels)) === 1;
    }

    /**
     * The description of the blocked range the address falls in, or null for a public address.
     */
    private function blockedRange(
        string $binary,
    ): ?string {
        if (strlen($binary) === 4) {
            return $this->firstMatch($binary, self::BLOCKED_IPV4);
        }

        foreach (self::EMBEDDED_IPV4 as $cidr => $embedding) {
            if ($this->inRange($binary, $cidr)) {
                $range = $this->firstMatch(substr($binary, $embedding['offset'], 4), self::BLOCKED_IPV4);

                return $range === null ? null : "{$embedding['name']} form of $range";
            }
        }

        return $this->firstMatch($binary, self::BLOCKED_IPV6);
    }

    /**
     * @param array<string, string> $ranges
     */
    private function firstMatch(
        string $binary,
        array $ranges,
    ): ?string {
        foreach ($ranges as $cidr => $description) {
            if ($this->inRange($binary, $cidr)) {
                return $description;
            }
        }

        return null;
    }

    private function inRange(
        string $binary,
        string $cidr,
    ): bool {
        [$network, $prefixLength] = explode('/', $cidr);
        $networkBinary = (string) inet_pton($network);

        if (strlen($networkBinary) !== strlen($binary)) {
            return false;
        }

        $prefixLength = (int) $prefixLength;
        $fullBytes = intdiv($prefixLength, 8);

        if (substr($binary, 0, $fullBytes) !== substr($networkBinary, 0, $fullBytes)) {
            return false;
        }

        $remainingBits = $prefixLength % 8;

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF00 >> $remainingBits) & 0xFF;

        return (ord($binary[$fullBytes]) & $mask) === (ord($networkBinary[$fullBytes]) & $mask);
    }
}
