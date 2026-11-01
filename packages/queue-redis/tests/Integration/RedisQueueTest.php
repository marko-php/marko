<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;
use Marko\Queue\JobIdGenerator;
use Marko\Queue\Redis\Tests\Fixtures\IntegrationRedis;
use Marko\Queue\Redis\Tests\Fixtures\QueueHarness;
use Marko\Queue\Redis\Tests\Fixtures\TestJob;

/*
 * Runs RedisQueue against a real Redis server (see IntegrationRedis for the environment variables). Every test
 * gets its own key prefix through QueueHarness and deletes its keys afterwards.
 */

pest()->group('integration-services');

beforeEach(function (): void {
    if (IntegrationRedis::unavailable()) {
        $this->markTestSkipped(IntegrationRedis::skipReason());
    }

    TestJob::$handled = [];
    $this->harnesses = [];
    $this->harness = function (array $config = []): QueueHarness {
        return $this->harnesses[] = new QueueHarness($config);
    };
});

afterEach(function (): void {
    foreach ($this->harnesses ?? [] as $harness) {
        $harness->flush();
    }
});

/**
 * Run a PHP fixture script in a child process with the test autoloader as its first argument.
 *
 * @param list<string> $arguments
 * @return array{0: resource, 1: array<int, resource>}
 */
function startRedisQueueChild(
    string $script,
    array $arguments,
): array {
    $autoload = dirname(new ReflectionClass(ClassLoader::class)->getFileName(), 2) . '/autoload.php';
    $process = proc_open(
        [PHP_BINARY, dirname(__DIR__) . "/Fixtures/$script", $autoload, ...$arguments],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
    );

    expect($process)->toBeResource();

    return [$process, $pipes];
}

/**
 * Wait for a child process and return its output, failing the test when it exited with an error.
 *
 * @param array{0: resource, 1: array<int, resource>} $child
 */
function finishRedisQueueChild(
    array $child,
): string {
    [$process, $pipes] = $child;
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    expect(proc_close($process))->toBe(0, "child process failed: $errors");

    return $output;
}

describe('RedisQueue push and pop', function (): void {
    it('pops a pushed job with its id, its data and the attempt it starts', function (): void {
        $queue = ($this->harness)()->queue();

        $result = $queue->push(new TestJob('hello'));
        $job = $queue->pop();

        expect($result->pushed)->toBeTrue()
            ->and($job)->toBeInstanceOf(TestJob::class)
            ->and($job->id)->toBe($result->id)
            ->and($job->message)->toBe('hello')
            ->and($job->attempts)->toBe(0)
            ->and($queue->pop())->toBeNull();
    });

    it('gives a pushed job the id from the bound JobIdGenerator', function (): void {
        $harness = ($this->harness)();
        $harness->container->instance(JobIdGenerator::class, new class () extends JobIdGenerator
        {
            public function generate(): string
            {
                return 'fixed-job-id';
            }
        });

        expect($harness->queue()->push(new TestJob())->id)->toBe('fixed-job-id')
            ->and($harness->queue()->pop()?->id)->toBe('fixed-job-id');
    });

    it('pops jobs in the order they were pushed', function (): void {
        $queue = ($this->harness)()->queue();

        $queue->push(new TestJob('first'));
        $queue->push(new TestJob('second'));

        expect($queue->pop()?->message)->toBe('first')
            ->and($queue->pop()?->message)->toBe('second');
    });

    it('keeps named queues apart and uses queue.queue when no queue is given', function (): void {
        $harness = ($this->harness)(['queue.queue' => 'emails']);
        $queue = $harness->queue();

        $queue->push(new TestJob('email'));
        $queue->push(new TestJob('report'), 'reports');

        expect($harness->client()->llen($harness->prefix . 'queues:emails'))->toBe(1)
            ->and($queue->pop('reports')?->message)->toBe('report')
            ->and($queue->pop('reports'))->toBeNull()
            ->and($queue->pop()?->message)->toBe('email');
    });

    it('returns null from an empty queue and leaves no keys behind', function (): void {
        $harness = ($this->harness)();

        expect($harness->queue()->pop())->toBeNull()
            ->and($harness->client()->keys($harness->prefix . '*'))->toBe([]);
    });
});

describe('RedisQueue delayed jobs', function (): void {
    it('makes a delayed job available once its delay has passed', function (): void {
        $harness = ($this->harness)();
        $queue = $harness->queue();

        $queue->later(60, new TestJob('later'));

        expect($queue->pop())->toBeNull()
            ->and($queue->size())->toBe(0);

        $harness->clock->travel('+59 seconds');

        expect($queue->pop())->toBeNull();

        $harness->clock->travel('+1 second');

        expect($queue->size())->toBe(1)
            ->and($queue->pop()?->message)->toBe('later');
    });

    it('pops a ready job while a delayed job is still waiting', function (): void {
        $queue = ($this->harness)()->queue();

        $queue->later(300, new TestJob('later'));
        $queue->push(new TestJob('now'));

        expect($queue->pop()?->message)->toBe('now')
            ->and($queue->pop())->toBeNull();
    });
});

describe('RedisQueue release', function (): void {
    it(
        'stores the released job with its attempts and exceptions and makes it available after the delay',
        function (): void {
            $harness = ($this->harness)();
            $queue = $harness->queue();
            $queue->push(new TestJob('retry'));

            // As the worker does: count the attempt it runs, and the exception it threw
            $job = $queue->pop();
            $job->incrementAttempts();
            $job->incrementExceptions();

            expect($queue->release($job, 30))->toBeTrue()
                ->and($queue->pop())->toBeNull();

            $harness->clock->travel('+30 seconds');
            $retried = $queue->pop();

            expect($retried?->id)->toBe($job->id)
                ->and($retried->attempts)->toBe(1)
                ->and($retried->exceptions)->toBe(1);
        },
    );

    it('makes a job released without a delay available straight away', function (): void {
        $queue = ($this->harness)()->queue();
        $queue->push(new TestJob('again'));

        $queue->release($queue->pop(), 0);

        expect($queue->pop()?->message)->toBe('again');
    });

    it('returns false for a job that is not reserved', function (): void {
        $queue = ($this->harness)()->queue();
        $queue->push(new TestJob());
        $job = $queue->pop();
        $queue->delete($job->id);

        expect($queue->release($job))->toBeFalse()
            ->and($queue->release(new TestJob()))->toBeFalse();
    });
});

describe('RedisQueue reservations', function (): void {
    it('puts back a job reserved by a crashed worker once queue.retry_after expires', function (): void {
        $harness = ($this->harness)();
        $queue = $harness->queue();
        $id = $queue->push(new TestJob('crash'))->id;

        // A worker reserves the job and dies before completing or releasing it
        $queue->pop();
        $harness->clock->travel('+89 seconds');

        expect($queue->pop())->toBeNull();

        $harness->clock->travel('+1 second');
        $job = $queue->pop();

        expect($job?->id)->toBe($id)
            ->and($job->attempts)->toBe(1);
    });

    it('fails a job whose attempts were all reserved by crashed workers', function (): void {
        $harness = ($this->harness)(['queue.max_attempts' => 2]);
        $queue = $harness->queue();
        $id = $queue->push(new TestJob())->id;

        $queue->pop();
        $harness->clock->travel('+90 seconds');
        $second = $queue->pop();
        $harness->clock->travel('+90 seconds');

        expect($second?->id)->toBe($id)
            ->and($harness->failedJobs()->count())->toBe(0)
            ->and($queue->pop())->toBeNull()
            ->and($harness->failedJobs()->find($id)?->exception)->toContain('after worker crash or timeout')
            ->and($harness->client()->exists($harness->prefix . "jobs:$id"))->toBe(0);
    });

    it('never hands the same job to two concurrent workers', function (): void {
        $harness = ($this->harness)();
        $queue = $harness->queue();
        $pushed = [];

        foreach (range(1, 60) as $i) {
            $pushed[] = $queue->push(new TestJob("job-$i"))->id;
        }

        $startAt = (string) (microtime(true) + 0.5);
        $children = array_map(
            fn (): array => startRedisQueueChild('pop-worker.php', [$harness->prefix, $startAt]),
            range(1, 6),
        );
        $popped = [];

        foreach ($children as $child) {
            $popped = [...$popped, ...array_filter(explode("\n", finishRedisQueueChild($child)))];
        }

        sort($pushed);
        sort($popped);

        expect($popped)->toBe($pushed);
    });
});

describe('RedisQueue blocking pop', function (): void {
    it('waits up to block_for seconds on an empty queue when the caller can block', function (): void {
        $queue = ($this->harness)(['queue-redis.block_for' => 1])->queue();

        $start = microtime(true);

        expect($queue->pop(block: true))->toBeNull()
            ->and(microtime(true) - $start)->toBeGreaterThanOrEqual(0.9);
    });

    it('returns a job pushed by another process while it waits', function (): void {
        $harness = ($this->harness)(['queue-redis.block_for' => 5]);
        $queue = $harness->queue();
        $child = startRedisQueueChild('push-worker.php', [$harness->prefix, '300']);

        $start = microtime(true);
        $job = $queue->pop(block: true);
        $elapsed = microtime(true) - $start;
        finishRedisQueueChild($child);

        expect($job?->message)->toBe('pushed-while-waiting')
            ->and($elapsed)->toBeLessThan(4.0);
    });

    it('returns straight away when the caller cannot block', function (): void {
        $queue = ($this->harness)(['queue-redis.block_for' => 2])->queue();

        $start = microtime(true);

        expect($queue->pop())->toBeNull()
            ->and(microtime(true) - $start)->toBeLessThan(1.0);
    });

    it('returns straight away when block_for is 0', function (): void {
        $queue = ($this->harness)(['queue-redis.block_for' => 0])->queue();

        $start = microtime(true);

        expect($queue->pop(block: true))->toBeNull()
            ->and(microtime(true) - $start)->toBeLessThan(1.0);
    });
});

describe('RedisQueue size, clear and delete', function (): void {
    it('counts ready jobs and delayed jobs that are due, not reserved or future ones', function (): void {
        $harness = ($this->harness)();
        $queue = $harness->queue();
        $queue->push(new TestJob('reserved'));
        $queue->pop();
        $queue->push(new TestJob('ready'));
        $queue->later(10, new TestJob('due'));
        $queue->later(600, new TestJob('future'));
        $harness->clock->travel('+10 seconds');

        expect($queue->size())->toBe(2)
            ->and($queue->size('other'))->toBe(0);
    });

    it('clears ready, delayed and reserved jobs and returns how many it removed', function (): void {
        $harness = ($this->harness)();
        $queue = $harness->queue();
        $queue->push(new TestJob('reserved'));
        $queue->pop();
        $queue->push(new TestJob('ready'));
        $queue->later(600, new TestJob('delayed'));
        $kept = $queue->push(new TestJob('other'), 'other')->id;

        expect($queue->clear())->toBe(3)
            ->and($queue->pop())->toBeNull()
            ->and($harness->client()->keys($harness->prefix . 'jobs:*'))->toBe([$harness->prefix . "jobs:$kept"])
            ->and($queue->size('other'))->toBe(1);
    });

    it('deletes a job wherever it is and returns false for an unknown id', function (): void {
        $harness = ($this->harness)();
        $queue = $harness->queue();
        $queue->push(new TestJob('reserved'));
        $reserved = $queue->pop()->id;
        $ready = $queue->push(new TestJob('ready'))->id;
        $delayed = $queue->later(60, new TestJob('delayed'))->id;
        expect($queue->delete($ready))->toBeTrue()
            ->and($queue->delete($delayed))->toBeTrue()
            ->and($queue->delete($reserved))->toBeTrue()
            ->and($queue->delete($reserved))->toBeFalse()
            ->and($queue->delete('unknown'))->toBeFalse();

        $harness->clock->travel('+90 seconds');

        expect($queue->pop())->toBeNull()
            ->and($harness->client()->keys($harness->prefix . 'jobs:*'))->toBe([]);
    });
});
