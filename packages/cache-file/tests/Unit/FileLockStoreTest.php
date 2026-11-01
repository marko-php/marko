<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;
use Marko\Cache\CacheOperations;
use Marko\Cache\Config\CacheConfig;
use Marko\Cache\Contracts\LockFactoryInterface;
use Marko\Cache\Contracts\LockStoreInterface;
use Marko\Cache\File\Driver\FileCacheDriver;
use Marko\Cache\File\Lock\FileLockStore;
use Marko\Cache\File\Storage\FileEntryStorage;
use Marko\Cache\Lock\LockFactory;
use Marko\Cache\Lock\LockScope;
use Marko\Cache\Signer\CacheValueSigner;
use Marko\Cache\Tests\Support\ClockAdvancingSleeper;
use Marko\Cache\Tests\Support\LockStoreContract;
use Marko\Cache\Tests\Support\LockStoreHarness;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Core\Path\ProjectPaths;
use Marko\Encryption\Config\EncryptionConfig;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use Psr\Clock\ClockInterface;

const FILE_LOCK_TEST_SIGNING_KEY = 'file-lock-test-signing-key';

/**
 * @return array<string, mixed>
 */
function fileLockTestConfig(
    string $path,
): array {
    return [
        'cache.path' => $path,
        'cache.default_ttl' => 3600,
        'cache.driver' => 'file',
        'encryption.key' => FILE_LOCK_TEST_SIGNING_KEY,
    ];
}

function fileLockTestStorage(
    string $path,
    ClockInterface $clock,
): FileEntryStorage {
    $config = new FakeConfigRepository(fileLockTestConfig($path));

    return new FileEntryStorage(
        new CacheConfig($config, new ProjectPaths()),
        $clock,
        new CacheValueSigner(new EncryptionConfig($config)),
    );
}

function fileLockTestRemove(
    string $path,
): void {
    if (!is_dir($path)) {
        return;
    }

    foreach (glob($path . '/*') ?: [] as $file) {
        unlink($file);
    }

    rmdir($path);
}

/**
 * Start lock-worker.php in a separate PHP process.
 *
 * @return array{0: resource, 1: array<int, resource>}
 */
function fileLockTestStartWorker(
    string $path,
    string $action,
    string $name,
    string $owner,
    string $startAt = '0',
): array {
    $autoload = dirname(new ReflectionClass(ClassLoader::class)->getFileName(), 2) . '/autoload.php';
    $worker = dirname(__DIR__) . '/Fixtures/lock-worker.php';

    $process = proc_open(
        [PHP_BINARY, $worker, $autoload, $path, FILE_LOCK_TEST_SIGNING_KEY, $action, $name, $owner, $startAt],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
    );

    if (!is_resource($process)) {
        throw new RuntimeException('Could not start lock-worker.php');
    }

    return [$process, $pipes];
}

/**
 * Wait for a worker and return what it printed ("1" or "0").
 *
 * @param array{0: resource, 1: array<int, resource>} $worker
 */
function fileLockTestFinishWorker(
    array $worker,
): string {
    [$process, $pipes] = $worker;
    $output = (string) stream_get_contents($pipes[1]);
    $errors = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    if (proc_close($process) !== 0) {
        throw new RuntimeException("lock-worker.php failed: $errors");
    }

    return $output;
}

LockStoreContract::register('FileLockStore', fn (): LockStoreHarness => new class () implements LockStoreHarness
{
    private string $path;

    private FakeClock $clock;

    private FileLockStore $store;

    public function __construct()
    {
        $this->path = sys_get_temp_dir() . '/marko-file-lock-' . bin2hex(random_bytes(8));
        $this->clock = new FakeClock('2026-01-01 12:00:00 UTC');
        $this->store = new FileLockStore(fileLockTestStorage($this->path, $this->clock));
    }

    public function store(): LockStoreInterface
    {
        return $this->store;
    }

    public function factory(): LockFactoryInterface
    {
        return new LockFactory($this->store, $this->clock, new ClockAdvancingSleeper($this->clock));
    }

    public function expire(
        string $name,
    ): void {
        // File records expire on whole seconds: a 60 second lock is gone after 61.
        $this->clock->travel('+61 seconds');
    }

    public function expectedScope(): LockScope
    {
        return LockScope::Host;
    }

    public function clockSeconds(): float
    {
        return (float) $this->clock->now()->format('U.u');
    }

    public function cleanUp(): void
    {
        fileLockTestRemove($this->path);
    }
});

describe('FileLockStore across processes', function (): void {
    beforeEach(function (): void {
        $this->path = sys_get_temp_dir() . '/marko-file-lock-' . bin2hex(random_bytes(8));
        $this->clock = new FakeClock();
        $this->store = new FileLockStore(fileLockTestStorage($this->path, $this->clock));
        $this->factory = new LockFactory($this->store, $this->clock, new ClockAdvancingSleeper($this->clock));
    });

    afterEach(function (): void {
        fileLockTestRemove($this->path);
    });

    it('releases a lock restored by name and owner in a separate process', function (): void {
        $lock = $this->factory->create('video:42', 60);
        $lock->acquire();

        $wrongOwner = fileLockTestFinishWorker(
            fileLockTestStartWorker($this->path, 'release', 'video:42', 'not-the-owner'),
        );
        $heldAfterWrongOwner = $this->store->currentOwner('video:42');
        $owner = fileLockTestFinishWorker(fileLockTestStartWorker($this->path, 'release', 'video:42', $lock->owner()));

        expect($wrongOwner)->toBe('0')
            ->and($heldAfterWrongOwner)->toBe($lock->owner())
            ->and($owner)->toBe('1')
            ->and($this->store->currentOwner('video:42'))->toBeNull()
            ->and($this->factory->create('video:42', 60)->acquire())->toBeTrue();
    });

    it('lets exactly one of several concurrent processes acquire the same lock', function (): void {
        $startAt = (string) (microtime(true) + 0.5);
        $workers = [];

        foreach (range(1, 6) as $i) {
            $workers[$i] = fileLockTestStartWorker($this->path, 'acquire', 'video:42', "worker-$i", $startAt);
        }

        $results = array_map(fileLockTestFinishWorker(...), $workers);
        $winners = array_keys($results, '1', true);

        expect($results)->each->toBeIn(['0', '1'])
            ->and($winners)->toHaveCount(1)
            ->and($this->store->currentOwner('video:42'))->toBe('worker-' . $winners[0]);
    });
});

describe('FileLockStore', function (): void {
    beforeEach(function (): void {
        $this->path = sys_get_temp_dir() . '/marko-file-lock-' . bin2hex(random_bytes(8));
        $this->clock = new FakeClock('2026-01-01 12:00:00 UTC');
    });

    afterEach(function (): void {
        fileLockTestRemove($this->path);
    });

    it('keeps lock records apart from cache entries of the same name', function (): void {
        $config = new FakeConfigRepository(fileLockTestConfig($this->path));
        $driver = new FileCacheDriver(
            new CacheConfig($config, new ProjectPaths()),
            $this->clock,
            new CacheValueSigner(new EncryptionConfig($config)),
            new CacheOperations(),
        );
        $store = new FileLockStore(fileLockTestStorage($this->path, $this->clock));

        $driver->set('video-42', 'cached');
        $store->acquire('video-42', 'owner-a', 60);

        expect($driver->get('video-42'))->toBe('cached')
            ->and($store->currentOwner('video-42'))->toBe('owner-a');
    });

    it('leaves no file behind once a lock is released', function (): void {
        $store = new FileLockStore(fileLockTestStorage($this->path, $this->clock));
        $store->acquire('video:42', 'owner-a', 60);
        $store->release('video:42', 'owner-a');
        $store->currentOwner('video:42');

        expect(glob($this->path . '/*'))->toBe([]);
    });

    it('binds LockStoreInterface to FileLockStore', function (): void {
        $container = new Container();
        $container->instance(
            ConfigRepositoryInterface::class,
            new FakeConfigRepository(fileLockTestConfig($this->path)),
        );
        $container->instance(ClockInterface::class, $this->clock);

        foreach ([dirname(__DIR__, 3) . '/cache/module.php', dirname(__DIR__, 2) . '/module.php'] as $modulePath) {
            $module = require $modulePath;

            foreach ($module['bindings'] as $id => $implementation) {
                $container->bind($id, $implementation);
            }
        }

        $lock = $container->get(LockFactoryInterface::class)->create('video:42', 60);

        expect($container->get(LockStoreInterface::class))->toBeInstanceOf(FileLockStore::class)
            ->and($lock->acquire())->toBeTrue()
            ->and($container->get(LockFactoryInterface::class)->create('video:42', 60)->acquire())->toBeFalse();
    });
});
