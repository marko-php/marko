<?php

declare(strict_types=1);

namespace Marko\Queue\Command;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Queue\Exceptions\QueueException;
use Marko\Queue\ProcessControlInterface;
use Marko\Queue\QueueConfig;
use Marko\Queue\RestartSignalInterface;
use Marko\Queue\StopCondition\RestartSignalStopCondition;
use Marko\Queue\WorkerInterface;
use Marko\Queue\WorkerOptions;

/** @noinspection PhpUnused */
#[Command(name: 'queue:work', description: 'Process jobs from the queue', flags: ['once'])]
class WorkCommand implements CommandInterface
{
    private const int DEFAULT_SLEEP = 3;

    public function __construct(
        private readonly WorkerInterface $worker,
        private readonly QueueConfig $config,
        private readonly ProcessControlInterface $processControl,
        private readonly RestartSignalInterface $restartSignal,
    ) {}

    public function execute(
        Input $input,
        Output $output,
    ): int {
        $once = $input->hasOption('once');
        $queueOption = $input->getOption('queue');
        $sleep = (int) ($input->getOption('sleep') ?? self::DEFAULT_SLEEP);
        $queues = $queueOption === null ? null : $this->parseQueues($queueOption);

        if ($queues === []) {
            $output->writeLine('Error: --queue needs at least one queue name, e.g. --queue=high,default.');

            return 1;
        }

        try {
            // A bad queue.backoff would otherwise surface only when the first job fails, maybe days later
            $this->config->backoff();
            $options = $this->resolveOptions($input, $queues ?? [$this->config->queue()]);
        } catch (QueueException $e) {
            $output->writeLine('Error: ' . $e->getMessage());
            $output->writeLine($e->getContext());
            $output->writeLine($e->getSuggestion());

            return 1;
        }

        if (!$this->processControl->isSupported()) {
            $output->writeLine(
                'Warning: ext-pcntl is not loaded, so job timeouts are not enforced and SIGTERM/SIGINT'
                . ' stop the worker immediately instead of after the current job.',
            );
        }

        $output->writeLine('Processing jobs from queue...');

        $reason = $this->worker->work(queues: $queues, once: $once, sleep: $sleep, options: $options);

        return $reason->exitCode();
    }

    /**
     * Build the worker limits from --timeout (default queue.timeout), --memory, --max-jobs and --max-time.
     *
     * This is the composition root for the worker's stop conditions: WorkerOptions builds the built-in
     * ones from these limits, and any extra condition is passed through its stopConditions. The restart
     * signal condition reads the current token here, so only a queue:restart issued after this worker
     * started stops it.
     *
     * The timeout is checked against the retry_after of every queue the worker pops from, so a queue with
     * a short reservation window cannot be paired with a timeout that outlives it.
     *
     * @param list<string> $queues The queues this worker pops from
     * @throws QueueException When a value is not a non-negative integer, a queue's retry_after is invalid,
     *     or the timeout is not below the retry_after of one of the queues
     */
    private function resolveOptions(
        Input $input,
        array $queues,
    ): WorkerOptions {
        $timeout = $input->getOption('timeout');
        $options = new WorkerOptions(
            timeout: $timeout === null ? $this->config->timeout() : $this->parseLimit('timeout', $timeout),
            memory: $this->parseLimit('memory', $input->getOption('memory') ?? '0'),
            maxJobs: $this->parseLimit('max-jobs', $input->getOption('max-jobs') ?? '0'),
            maxTime: $this->parseLimit('max-time', $input->getOption('max-time') ?? '0'),
            stopConditions: [new RestartSignalStopCondition($this->restartSignal)],
        );

        // A job still running when its reservation expires would be handed to a second worker
        foreach ($queues as $queue) {
            $retryAfter = $this->config->retryAfter($queue);

            if ($options->timeout > 0 && $options->timeout >= $retryAfter) {
                throw QueueException::timeoutNotBelowRetryAfter(
                    $options->timeout,
                    $retryAfter,
                    $queue,
                    $this->config->retryAfterKey($queue),
                );
            }
        }

        return $options;
    }

    /**
     * @throws QueueException When the value is not a non-negative integer
     */
    private function parseLimit(
        string $option,
        string $value,
    ): int {
        if (!ctype_digit($value)) {
            throw QueueException::invalidWorkerOption($option, $value);
        }

        return (int) $value;
    }

    /**
     * Split a comma-separated --queue value into queue names in priority order.
     *
     * @return list<string>
     */
    private function parseQueues(
        string $value,
    ): array {
        $names = array_map(trim(...), explode(',', $value));

        return array_values(array_filter($names, fn (string $name): bool => $name !== ''));
    }
}
