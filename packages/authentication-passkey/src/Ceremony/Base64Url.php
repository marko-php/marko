<?php

declare(strict_types=1);

namespace Marko\AuthenticationPasskey\Ceremony;

/**
 * Unpadded base64url (RFC 4648 §5), the encoding WebAuthn JSON uses for every
 * binary value.
 */
class Base64Url
{
    public static function encode(
        string $binary,
    ): string {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }

    /**
     * The decoded bytes, or null when $value is not base64url.
     */
    public static function decode(
        string $value,
    ): ?string {
        if (preg_match('/^[A-Za-z0-9_-]*$/', $value) !== 1 || strlen($value) % 4 === 1) {
            return null;
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
