<?php

declare(strict_types=1);

/*
 * Child process for the file driver's concurrent add() test. Waits until a
 * shared start time so every worker races for the same key, then prints "1"
 * when its add() stored the value and "0" when it did not.
 *
 * Usage: php add-worker.php <autoload> <cache-path> <signing-key> <start-microtime> <value>
 */

use Marko\Cache\CacheOperations;
use Marko\Cache\Config\CacheConfig;
use Marko\Cache\File\Driver\FileCacheDriver;
use Marko\Cache\Signer\CacheValueSigner;
use Marko\Encryption\Config\EncryptionConfig;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;

[, $autoload, $cachePath, $signingKey, $startAt, $value] = $argv;

require $autoload;

$driver = new FileCacheDriver(
    new CacheConfig(new FakeConfigRepository([
        'cache.path' => $cachePath,
        'cache.default_ttl' => 3600,
        'cache.driver' => 'file',
    ])),
    new FakeClock(),
    new CacheValueSigner(new EncryptionConfig(new FakeConfigRepository([
        'encryption.key' => $signingKey,
    ]))),
    new CacheOperations(),
);

while (microtime(true) < (float) $startAt) {
    usleep(100);
}

echo $driver->add('once', $value, 60) ? '1' : '0';
