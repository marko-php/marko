<?php

declare(strict_types=1);

use Marko\Queue\Redis\Exceptions\RedisQueueException;
use Marko\Queue\Redis\RedisQueueConfig;
use Marko\Testing\Fake\FakeConfigRepository;

function createRedisQueueConfig(
    array $values = [],
): RedisQueueConfig {
    return new RedisQueueConfig(new FakeConfigRepository([
        'queue-redis.connection' => 'queue',
        'queue-redis.prefix' => 'app:',
        'queue-redis.block_for' => 5,
        ...$values,
    ]));
}

describe('RedisQueueConfig', function (): void {
    it('reads the connection name, key prefix and block time', function (): void {
        $config = createRedisQueueConfig();

        expect($config->connection())->toBe('queue')
            ->and($config->prefix())->toBe('app:')
            ->and($config->blockFor())->toBe(5);
    });

    it('rejects a negative block_for loudly', function (): void {
        createRedisQueueConfig(['queue-redis.block_for' => -1])->blockFor();
    })->throws(RedisQueueException::class, 'queue-redis.block_for');
});
