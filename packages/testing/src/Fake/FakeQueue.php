<?php

declare(strict_types=1);

namespace Marko\Testing\Fake;

use Marko\Cache\Contracts\LockFactoryInterface;
use Marko\Cache\Exceptions\LockException;
use Marko\Core\Container\Container;
use Marko\Queue\Exceptions\QueueException;
use Marko\Queue\JobInterface;
use Marko\Queue\Plugin\UniqueJobPushPlugin;
use Marko\Queue\PushResult;
use Marko\Queue\QueueInterface;
use Marko\Queue\UniqueJobLocks;
use Marko\Testing\Exceptions\AssertionFailedException;

/**
 * Records pushed jobs instead of queuing them.
 *
 * Pushes go through the same UniqueJobPushPlugin the container puts on every QueueInterface,
 * so a duplicate UniqueJobInterface job is skipped (and recorded in $skipped) as in production.
 * Pass a LockFactoryInterface (for example LockFactory over marko/cache-array's ArrayLockStore)
 * to push unique jobs; without one, pushing a unique job throws, as it does in an app with no
 * lock driver. Jobs that are not unique never need it.
 */
class FakeQueue implements QueueInterface
{
    /** @var array<array{job: JobInterface, queue: ?string, delay: int, id: string}> */
    public private(set) array $pushed = [];

    /** @var array<array{job: JobInterface, queue: ?string, delay: int, reason: string}> */
    public private(set) array $skipped = [];

    private int $nextId = 1;

    private readonly UniqueJobPushPlugin $uniqueJobPushPlugin;

    public function __construct(
        ?LockFactoryInterface $lockFactory = null,
    ) {
        $container = new Container();

        if ($lockFactory !== null) {
            $container->instance(LockFactoryInterface::class, $lockFactory);
        }

        $this->uniqueJobPushPlugin = new UniqueJobPushPlugin(new UniqueJobLocks($container));
    }

    /**
     * @throws QueueException|LockException
     */
    public function push(
        JobInterface $job,
        ?string $queue = null,
    ): PushResult {
        $skipped = $this->uniqueJobPushPlugin->beforePush($job, $queue);

        if ($skipped !== null) {
            return $this->recordSkipped($job, $queue, 0, $skipped);
        }

        $id = (string) $this->nextId++;
        $job->setId($id);
        $this->pushed[] = [
            'job' => $job,
            'queue' => $queue,
            'delay' => 0,
            'id' => $id,
        ];

        return PushResult::pushed($id);
    }

    /**
     * @throws QueueException|LockException
     */
    public function later(
        int $delay,
        JobInterface $job,
        ?string $queue = null,
    ): PushResult {
        $skipped = $this->uniqueJobPushPlugin->beforeLater($delay, $job, $queue);

        if ($skipped !== null) {
            return $this->recordSkipped($job, $queue, $delay, $skipped);
        }

        $id = (string) $this->nextId++;
        $job->setId($id);
        $this->pushed[] = [
            'job' => $job,
            'queue' => $queue,
            'delay' => $delay,
            'id' => $id,
        ];

        return PushResult::pushed($id);
    }

    public function pop(
        ?string $queue = null,
    ): ?JobInterface {
        foreach ($this->pushed as $index => $entry) {
            if ($entry['queue'] === $queue) {
                unset($this->pushed[$index]);
                $this->pushed = array_values($this->pushed);

                return $entry['job'];
            }
        }

        return null;
    }

    public function size(
        ?string $queue = null,
    ): int {
        return count(array_filter(
            $this->pushed,
            fn (array $entry) => $entry['queue'] === $queue,
        ));
    }

    public function clear(
        ?string $queue = null,
    ): int {
        $count = $this->size($queue);
        $this->pushed = array_values(array_filter(
            $this->pushed,
            fn (array $entry) => $entry['queue'] !== $queue,
        ));

        return $count;
    }

    public function delete(
        string $jobId,
    ): bool {
        foreach ($this->pushed as $index => $entry) {
            if ($entry['id'] === $jobId) {
                unset($this->pushed[$index]);
                $this->pushed = array_values($this->pushed);

                return true;
            }
        }

        return false;
    }

    /**
     * Store the given job, with its current state, in place of the pushed entry that has its id.
     */
    public function release(
        JobInterface $job,
        int $delay = 0,
    ): bool {
        foreach ($this->pushed as $index => $entry) {
            if ($entry['id'] === $job->id) {
                $this->pushed[$index]['job'] = $job;
                $this->pushed[$index]['delay'] = $delay;

                return true;
            }
        }

        return false;
    }

    /**
     * @throws AssertionFailedException
     */
    public function assertPushed(
        string $jobClass,
        ?callable $callback = null,
    ): void {
        $matches = array_filter(
            $this->pushed,
            fn (array $entry) => $entry['job'] instanceof $jobClass,
        );

        if (count($matches) === 0) {
            throw AssertionFailedException::expectedContains('queue', $jobClass);
        }

        if ($callback !== null) {
            $passed = array_any($matches, fn (array $entry) => $callback($entry['job']));

            if (!$passed) {
                throw AssertionFailedException::expectedContains('queue', $jobClass);
            }
        }
    }

    /**
     * Assert exactly one job of the class was pushed (skipped duplicates do not count).
     *
     * @throws AssertionFailedException
     */
    public function assertPushedOnce(
        string $jobClass,
    ): void {
        $actual = count(array_filter(
            $this->pushed,
            fn (array $entry) => $entry['job'] instanceof $jobClass,
        ));

        if ($actual !== 1) {
            throw AssertionFailedException::expectedCount("$jobClass jobs pushed", 1, $actual);
        }
    }

    /**
     * Assert a job of the class was skipped, optionally one the callback accepts.
     *
     * @throws AssertionFailedException
     */
    public function assertSkipped(
        string $jobClass,
        ?callable $callback = null,
    ): void {
        $matches = array_filter(
            $this->skipped,
            fn (array $entry) => $entry['job'] instanceof $jobClass
                && ($callback === null || $callback($entry['job'])),
        );

        if ($matches === []) {
            throw AssertionFailedException::expectedContains('skipped jobs', $jobClass);
        }
    }

    /**
     * @throws AssertionFailedException
     */
    public function assertNotPushed(
        string $jobClass,
    ): void {
        $matches = array_filter(
            $this->pushed,
            fn (array $entry) => $entry['job'] instanceof $jobClass,
        );

        if (count($matches) > 0) {
            throw AssertionFailedException::unexpectedContains('queue', $jobClass);
        }
    }

    /**
     * @throws AssertionFailedException
     */
    public function assertPushedCount(
        int $expected,
    ): void {
        $actual = count($this->pushed);

        if ($actual !== $expected) {
            throw AssertionFailedException::expectedCount('jobs pushed', $expected, $actual);
        }
    }

    /**
     * @throws AssertionFailedException
     */
    public function assertNothingPushed(): void
    {
        if (count($this->pushed) > 0) {
            throw AssertionFailedException::expectedEmpty('jobs');
        }
    }

    private function recordSkipped(
        JobInterface $job,
        ?string $queue,
        int $delay,
        PushResult $result,
    ): PushResult {
        $this->skipped[] = [
            'job' => $job,
            'queue' => $queue,
            'delay' => $delay,
            'reason' => (string) $result->skipReason,
        ];

        return $result;
    }
}
