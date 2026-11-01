<?php

declare(strict_types=1);

/*
 * Child process for the Redis lock store race test. Opens its own Redis client,
 * waits until a shared start time so every worker races for the same lock, then
 * prints "1" when its acquire() won and "0" when it did not.
 *
 * Usage: php redis-lock-worker.php <autoload> <prefix> <name> <owner> <start-microtime>
 */

use Marko\Cache\Redis\Config\RedisCacheConfig;
use Marko\Cache\Redis\Lock\RedisLockStore;
use Marko\Cache\Redis\Tests\Fixtures\IntegrationRedis;
use Marko\Testing\Fake\FakeConfigRepository;

[, $autoload, $prefix, $name, $owner, $startAt] = $argv;

require $autoload;

$store = new RedisLockStore(
    IntegrationRedis::connectionFactory(),
    new RedisCacheConfig(new FakeConfigRepository([
        'cache-redis.connection' => IntegrationRedis::CONNECTION,
        'cache-redis.prefix' => $prefix,
    ])),
);

// Connect before the start time, so the race is between the SET commands.
$store->currentOwner('warm-up');

while (microtime(true) < (float) $startAt) {
    usleep(100);
}

echo $store->acquire($name, $owner, 60) ? '1' : '0';
