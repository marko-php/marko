<?php

declare(strict_types=1);

use Marko\Cache\Redis\Config\RedisCacheConfig;
use Marko\Cache\Redis\Lock\RedisLockStore;
use Marko\Cache\Redis\Tests\Fixtures\IntegrationRedis;
use Marko\Clock\SystemClock;
use Marko\Core\Command\Output;
use Marko\Scheduler\Mutex\DistributedLockResolver;
use Marko\Scheduler\Mutex\FileTaskMutex;
use Marko\Scheduler\Mutex\LockTaskMutex;
use Marko\Scheduler\Mutex\LockTaskServerClaim;
use Marko\Scheduler\Mutex\TaskMutexInterface;
use Marko\Scheduler\Schedule;
use Marko\Scheduler\ScheduleRunner;
use Marko\Scheduler\Tests\Fixtures\SchedulerFactory;
use Marko\Testing\Fake\FakeConfigRepository;

/*
 * Two scheduler "servers" sharing one real Redis server through cache-redis (see
 * IntegrationRedis for the environment variables). Each server has its own Redis
 * connection, lock factory and runner; only the Redis keys are shared.
 */

pest()->group('integration-services');

beforeEach(function (): void {
    if (IntegrationRedis::unavailable()) {
        return;
    }

    $this->prefix = 'marko:test:scheduler:' . bin2hex(random_bytes(6)) . ':';
    $this->mutexDirectory = sys_get_temp_dir() . '/marko-scheduler-redis-' . bin2hex(random_bytes(6));
    $this->clock = new SystemClock();
    $this->output = new Output(fopen('php://memory', 'r+'));
    $this->lockResolver = function (): DistributedLockResolver {
        $store = new RedisLockStore(
            IntegrationRedis::connectionFactory(),
            new RedisCacheConfig(new FakeConfigRepository([
                'cache-redis.connection' => IntegrationRedis::CONNECTION,
                'cache-redis.prefix' => $this->prefix,
            ])),
        );

        return SchedulerFactory::lockResolver($store, $this->clock);
    };
    $this->server = fn (Schedule $schedule, TaskMutexInterface $mutex): ScheduleRunner => SchedulerFactory::runner(
        $schedule,
        $mutex,
        taskServerClaim: new LockTaskServerClaim(($this->lockResolver)()),
    );
});

afterEach(function (): void {
    if (IntegrationRedis::unavailable()) {
        return;
    }

    $client = IntegrationRedis::connectionFactory()->connection(IntegrationRedis::CONNECTION)->client();
    $keys = $client->keys($this->prefix . '*');

    if ($keys !== []) {
        $client->del($keys);
    }

    if (is_dir($this->mutexDirectory)) {
        exec('rm -rf ' . escapeshellarg($this->mutexDirectory));
    }
});

it('runs an onOneServer task on only one of two servers for the same minute', function (): void {
    $runs = 0;
    $now = new DateTimeImmutable();
    $results = [];

    foreach (['a', 'b'] as $server) {
        $schedule = SchedulerFactory::schedule();
        $schedule->call(function () use (&$runs): void {
            $runs++;
        })->everyMinute()->description('Send digest')->onOneServer();
        $mutex = new FileTaskMutex("$this->mutexDirectory/$server", $this->clock);
        $results[] = ($this->server)($schedule, $mutex)->run($now, $this->output);
    }

    expect($runs)->toBe(1)
        ->and($results[0]->executed)->toBe(1)
        ->and($results[1]->skippedByServer)->toBe(1);

    $nextMinute = ($this->server)($schedule, new FileTaskMutex("$this->mutexDirectory/b", $this->clock))
        ->run($now->modify('+1 minute'), $this->output);

    expect($nextMinute->executed)->toBe(1)
        ->and($runs)->toBe(2);
})->skip(fn (): bool => IntegrationRedis::unavailable(), IntegrationRedis::skipReason());

it('keeps a withoutOverlapping task from overlapping across two servers with LockTaskMutex', function (): void {
    $runs = 0;
    $secondRunner = null;
    $secondResult = null;
    $now = new DateTimeImmutable();
    $longImport = function () use (&$runs, &$secondRunner, &$secondResult, $now): void {
        $runs++;

        // The second server starts the task while the first one is still running it.
        if ($runs === 1) {
            $secondResult = $secondRunner->run($now, $this->output);
        }
    };
    $firstSchedule = SchedulerFactory::schedule();
    $firstSchedule->call($longImport)->everyMinute()->description('Long import')->withoutOverlapping(5);
    $secondSchedule = SchedulerFactory::schedule();
    $secondSchedule->call($longImport)->everyMinute()->description('Long import')->withoutOverlapping(5);
    $secondRunner = ($this->server)($secondSchedule, new LockTaskMutex(($this->lockResolver)()));

    $firstResult = ($this->server)($firstSchedule, new LockTaskMutex(($this->lockResolver)()))
        ->run($now, $this->output);

    expect($runs)->toBe(1)
        ->and($firstResult->executed)->toBe(1)
        ->and($secondResult->skipped)->toBe(1)
        ->and(
            new LockTaskMutex(($this->lockResolver)())->exists($firstSchedule->tasks()[0]->mutexName()),
        )->toBeFalse();
})->skip(fn (): bool => IntegrationRedis::unavailable(), IntegrationRedis::skipReason());
