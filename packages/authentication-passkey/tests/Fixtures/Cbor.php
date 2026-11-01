<?php

declare(strict_types=1);

namespace Marko\AuthenticationPasskey\Tests\Fixtures;

/**
 * The few CBOR (RFC 8949) encodings an authenticator needs: integers, byte
 * strings, text strings and maps. Each method returns encoded bytes.
 */
class Cbor
{
    public static function int(
        int $value,
    ): string {
        return $value >= 0 ? self::head(0, $value) : self::head(1, -1 - $value);
    }

    public static function bytes(
        string $value,
    ): string {
        return self::head(2, strlen($value)) . $value;
    }

    public static function text(
        string $value,
    ): string {
        return self::head(3, strlen($value)) . $value;
    }

    /**
     * @param list<array{string, string}> $pairs Encoded key and encoded value pairs, in order
     */
    public static function map(
        array $pairs,
    ): string {
        return self::head(5, count($pairs)) . implode('', array_map(
            static fn (array $pair): string => $pair[0] . $pair[1],
            $pairs,
        ));
    }

    private static function head(
        int $majorType,
        int $length,
    ): string {
        $type = $majorType << 5;

        return match (true) {
            $length < 24 => chr($type | $length),
            $length < 0x100 => chr($type | 24) . chr($length),
            $length < 0x10000 => chr($type | 25) . pack('n', $length),
            default => chr($type | 26) . pack('N', $length),
        };
    }
}
