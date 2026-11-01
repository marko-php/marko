<?php

declare(strict_types=1);

namespace Marko\Queue\Tests\Fixtures;

use Marko\Core\Support\ErrorCapture;
use Marko\Redis\RedisConnectionFactory;
use Marko\Testing\Fake\FakeConfigRepository;
use Predis\Client;
use RuntimeException;
use Throwable;

/**
 * The live Redis server for marko/queue integration tests: MARKO_TEST_REDIS_HOST
 * and MARKO_TEST_REDIS_PORT (default 127.0.0.1:6379), database 15. Tests skip
 * with the reason when no server answers; with MARKO_INTEGRATION_REQUIRED set
 * (CI) an unreachable server is a failure instead.
 */
class IntegrationRedis
{
    public const string CONNECTION = 'integration';

    private static bool $probed = false;

    private static ?string $unreachableReason = null;

    public static function host(): string
    {
        return getenv('MARKO_TEST_REDIS_HOST') ?: '127.0.0.1';
    }

    public static function port(): int
    {
        return (int) (getenv('MARKO_TEST_REDIS_PORT') ?: 6379);
    }

    /**
     * @throws RuntimeException when MARKO_INTEGRATION_REQUIRED is set and Redis is unreachable
     */
    public static function unavailable(): bool
    {
        if (!self::$probed) {
            self::$probed = true;

            try {
                // Predis suppresses its own connect warning with @, which PHPUnit
                // still records, so the ping runs inside ErrorCapture.
                ErrorCapture::run($warning, fn (): mixed => new Client([
                    'host' => self::host(),
                    'port' => self::port(),
                    'timeout' => 0.5,
                ])->ping());
            } catch (Throwable $e) {
                self::$unreachableReason = $e->getMessage();
            }
        }

        $required = in_array(strtolower((string) getenv('MARKO_INTEGRATION_REQUIRED')), ['1', 'true', 'yes'], true);

        if (self::$unreachableReason !== null && $required) {
            throw new RuntimeException(
                'MARKO_INTEGRATION_REQUIRED is set but Redis is unreachable: ' . self::skipReason(),
            );
        }

        return self::$unreachableReason !== null;
    }

    public static function skipReason(): string
    {
        return sprintf(
            'Redis is not reachable at %s:%d%s. Start one (e.g. `docker run -p 6379:6379 redis:7-alpine`) or set MARKO_TEST_REDIS_HOST / MARKO_TEST_REDIS_PORT.',
            self::host(),
            self::port(),
            self::$unreachableReason !== null ? ': ' . self::$unreachableReason : '',
        );
    }

    /**
     * A marko/redis factory with one 'integration' connection on database 15.
     */
    public static function connectionFactory(): RedisConnectionFactory
    {
        $settings = [
            'host' => self::host(),
            'port' => self::port(),
            'password' => null,
            'database' => 15,
            'scheme' => 'tcp',
        ];
        $config = ['redis.connections' => [self::CONNECTION => $settings]];

        foreach ($settings as $key => $value) {
            $config['redis.connections.' . self::CONNECTION . ".$key"] = $value;
        }

        return new RedisConnectionFactory(new FakeConfigRepository($config));
    }
}
