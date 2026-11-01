<?php

declare(strict_types=1);

/*
 * Child process for the file lock store tests. Builds its own FileLockStore on
 * the given cache directory, then either restores a lock by name and owner and
 * releases it ("release"), or waits until a shared start time and races for it
 * ("acquire"). Prints "1" when the call succeeded and "0" when it did not.
 *
 * Usage: php lock-worker.php <autoload> <cache-path> <signing-key> <release|acquire> <name> <owner> [<start-microtime>]
 */

use Marko\Cache\Config\CacheConfig;
use Marko\Cache\File\Lock\FileLockStore;
use Marko\Cache\File\Storage\FileEntryStorage;
use Marko\Cache\Lock\LockFactory;
use Marko\Cache\Lock\UsleepSleeper;
use Marko\Cache\Signer\CacheValueSigner;
use Marko\Clock\SystemClock;
use Marko\Encryption\Config\EncryptionConfig;
use Marko\Testing\Fake\FakeConfigRepository;

[, $autoload, $cachePath, $signingKey, $action, $name, $owner] = $argv;
$startAt = (float) ($argv[7] ?? 0);

require $autoload;

$clock = new SystemClock();
$store = new FileLockStore(new FileEntryStorage(
    new CacheConfig(new FakeConfigRepository([
        'cache.path' => $cachePath,
        'cache.default_ttl' => 3600,
        'cache.driver' => 'file',
    ])),
    $clock,
    new CacheValueSigner(new EncryptionConfig(new FakeConfigRepository([
        'encryption.key' => $signingKey,
    ]))),
));

while (microtime(true) < $startAt) {
    usleep(100);
}

$succeeded = $action === 'release'
    ? new LockFactory($store, $clock, new UsleepSleeper())->restore($name, $owner)->release()
    : $store->acquire($name, $owner, 60);

echo $succeeded ? '1' : '0';
