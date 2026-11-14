<?php

declare(strict_types=1);

namespace Marko\RateLimiter\Support;

/**
 * Byte-level IP helpers shared by trusted-proxy CIDR matching and IPv6
 * prefix bucketing.
 */
class IpAddress
{
    private const string IPV4_MAPPED_PREFIX = "\0\0\0\0\0\0\0\0\0\0\xff\xff";

    /**
     * Pack an IP address into its binary form: 4 bytes for IPv4, 16 for IPv6.
     * An IPv4-mapped IPv6 address (::ffff:203.0.113.1) packs as its IPv4 form,
     * so a dual-stack socket and an IPv4 rule agree. Returns null for anything
     * that is not a valid IP address.
     */
    public static function pack(
        string $ip,
    ): ?string {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        $packed = inet_pton($ip);

        if ($packed === false) {
            return null;
        }

        if (
            strlen($packed) === 16
            && str_starts_with($packed, self::IPV4_MAPPED_PREFIX)
        ) {
            return substr($packed, 12);
        }

        return $packed;
    }

    /**
     * Zero every bit after the first $prefixLength bits of a packed address.
     */
    public static function mask(
        string $packed,
        int $prefixLength,
    ): string {
        $fullBytes = intdiv($prefixLength, 8);
        $remainingBits = $prefixLength % 8;
        $masked = substr($packed, 0, $fullBytes);

        if ($remainingBits > 0) {
            $droppedBits = 8 - $remainingBits;
            $masked .= chr(ord($packed[$fullBytes]) & (0xFF << $droppedBits) & 0xFF);
        }

        return str_pad($masked, strlen($packed), "\0");
    }

    /**
     * Whether a packed address falls inside a packed network of the given
     * prefix length. Addresses of different families never match.
     */
    public static function inRange(
        string $packedIp,
        string $packedNetwork,
        int $prefixLength,
    ): bool {
        return strlen($packedIp) === strlen($packedNetwork)
            && self::mask($packedIp, $prefixLength) === self::mask($packedNetwork, $prefixLength);
    }
}
