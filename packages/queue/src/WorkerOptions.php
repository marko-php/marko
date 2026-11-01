<?php

declare(strict_types=1);

namespace Marko\Queue;

use Marko\Queue\Exceptions\QueueException;
use Marko\Queue\StopCondition\MaxJobsStopCondition;
use Marko\Queue\StopCondition\MaxTimeStopCondition;
use Marko\Queue\StopCondition\MemoryStopCondition;
use Psr\Clock\ClockInterface;

/**
 * Limits for one worker run. Zero disables a limit.
 */
readonly class WorkerOptions
{
    /**
     * @param int $timeout Seconds one job may run before it is failed and the worker exits (needs ext-pcntl);
     *                     a job's own $timeout replaces it
     * @param int $memory Megabytes of memory after which the worker exits once the current job is done
     * @param int $maxJobs Jobs to process before the worker exits
     * @param int $maxTime Seconds the worker runs before it exits once the current job is done
     * @param list<WorkerStopConditionInterface> $stopConditions Extra conditions checked after the built-in limits
     * @throws QueueException When a limit is negative or a stop condition has the wrong type
     */
    public function __construct(
        public int $timeout = 0,
        public int $memory = 0,
        public int $maxJobs = 0,
        public int $maxTime = 0,
        public array $stopConditions = [],
    ) {
        $limits = ['timeout' => $timeout, 'memory' => $memory, 'max-jobs' => $maxJobs, 'max-time' => $maxTime];

        foreach ($limits as $name => $value) {
            if ($value < 0) {
                throw QueueException::invalidWorkerOption($name, (string) $value);
            }
        }

        foreach ($stopConditions as $condition) {
            if (!$condition instanceof WorkerStopConditionInterface) {
                throw QueueException::invalidStopCondition(get_debug_type($condition));
            }
        }
    }

    /**
     * The stop conditions for this run: one for each limit that is on (max-jobs, memory, max-time),
     * followed by the extra $stopConditions in the order given.
     *
     * @return list<WorkerStopConditionInterface>
     */
    public function buildStopConditions(
        ClockInterface $clock,
    ): array {
        $conditions = [];

        if ($this->maxJobs > 0) {
            $conditions[] = new MaxJobsStopCondition($this->maxJobs);
        }

        if ($this->memory > 0) {
            $conditions[] = new MemoryStopCondition($this->memory);
        }

        if ($this->maxTime > 0) {
            $conditions[] = new MaxTimeStopCondition($this->maxTime, $clock);
        }

        return [...$conditions, ...$this->stopConditions];
    }
}
