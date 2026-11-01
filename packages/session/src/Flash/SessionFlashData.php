<?php

declare(strict_types=1);

namespace Marko\Session\Flash;

use Marko\Session\Contracts\FlashDataInterface;
use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Exceptions\InvalidFlashDataException;

/**
 * Next-request flash data stored in the session under a reserved key:
 *
 *     '_flash_data' => ['new' => [...put on this request], 'current' => [...put on the previous request]]
 *
 * The session ages the data once per request, when it loads (see age()):
 * 'current' is dropped and 'new' becomes 'current'. So a value lives for
 * exactly one request after the one that put it, read or not.
 */
readonly class SessionFlashData implements FlashDataInterface
{
    public const string SESSION_KEY = '_flash_data';

    private const string NEW = 'new';

    private const string CURRENT = 'current';

    public function __construct(
        private SessionInterface $session,
    ) {}

    /**
     * Age the flash data in freshly loaded session data: drop what the previous
     * request read, and make what it put readable. Removes the reserved key
     * entirely once nothing is left, so a session without flash data is unchanged.
     *
     * @param array<string, mixed> $sessionData
     * @return array<string, mixed>
     */
    public static function age(
        array $sessionData,
    ): array {
        if (!isset($sessionData[self::SESSION_KEY])) {
            return $sessionData;
        }

        $new = $sessionData[self::SESSION_KEY][self::NEW] ?? [];

        if ($new === []) {
            unset($sessionData[self::SESSION_KEY]);

            return $sessionData;
        }

        $sessionData[self::SESSION_KEY] = [self::NEW => [], self::CURRENT => $new];

        return $sessionData;
    }

    /**
     * @throws InvalidFlashDataException
     */
    public function put(
        string $key,
        mixed $value,
    ): void {
        $this->assertSerialisable($key, $value);

        $flashData = $this->flashData();
        $flashData[self::NEW][$key] = $value;

        $this->session->set(self::SESSION_KEY, $flashData);
    }

    public function get(
        string $key,
        mixed $default = null,
    ): mixed {
        $current = $this->flashData()[self::CURRENT];

        return array_key_exists($key, $current) ? $current[$key] : $default;
    }

    public function has(
        string $key,
    ): bool {
        return array_key_exists($key, $this->flashData()[self::CURRENT]);
    }

    public function keep(
        string ...$keys,
    ): void {
        $flashData = $this->flashData();
        $current = $flashData[self::CURRENT];
        $kept = $keys === [] ? $current : array_intersect_key($current, array_flip($keys));

        if ($kept === []) {
            return;
        }

        $flashData[self::NEW] = array_replace($flashData[self::NEW], $kept);

        $this->session->set(self::SESSION_KEY, $flashData);
    }

    /**
     * @return array{new: array<string, mixed>, current: array<string, mixed>}
     */
    private function flashData(): array
    {
        $stored = $this->session->get(self::SESSION_KEY);

        return [
            self::NEW => $stored[self::NEW] ?? [],
            self::CURRENT => $stored[self::CURRENT] ?? [],
        ];
    }

    /**
     * @throws InvalidFlashDataException
     */
    private function assertSerialisable(
        string $path,
        mixed $value,
    ): void {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $this->assertSerialisable("$path.$key", $item);
            }

            return;
        }

        if ($value !== null && !is_scalar($value)) {
            throw InvalidFlashDataException::forValue($path, $value);
        }
    }
}
