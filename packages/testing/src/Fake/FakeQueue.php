<?php

declare(strict_types=1);

namespace Marko\Testing\Fake;

use Marko\Queue\JobInterface;
use Marko\Queue\PushResult;
use Marko\Queue\QueueInterface;
use Marko\Testing\Exceptions\AssertionFailedException;

class FakeQueue implements QueueInterface
{
    /** @var array<array{job: JobInterface, queue: ?string, delay: int, id: string}> */
    public private(set) array $pushed = [];

    private int $nextId = 1;

    public function push(
        JobInterface $job,
        ?string $queue = null,
    ): PushResult {
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

    public function later(
        int $delay,
        JobInterface $job,
        ?string $queue = null,
    ): PushResult {
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
}
