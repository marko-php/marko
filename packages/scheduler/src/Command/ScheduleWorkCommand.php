<?php

declare(strict_types=1);

namespace Marko\Scheduler\Command;

use Closure;
use DateTimeImmutable;
use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Core\RequestStateResetter;
use Marko\Scheduler\Exceptions\SchedulerException;
use Marko\Scheduler\ScheduleRunner;
use Psr\Clock\ClockInterface;
use Throwable;

/**
 * Foreground scheduler loop for local development and containers without a cron daemon.
 *
 * Sleeps until the top of each minute, then runs the tasks due at that minute. Tasks run
 * inline and sequentially, so a run that overruns the minute waits for the next boundary.
 * Resettable services are reset before each run, so a run never starts inside state the
 * previous one left behind. A reset that throws ends the command before that minute's tasks run.
 * Stops gracefully on SIGINT/SIGTERM when the pcntl extension is available.
 *
 * @noinspection PhpUnused
 */
#[Command(name: 'schedule:work', description: 'Run due scheduled tasks every minute in the foreground')]
class ScheduleWorkCommand implements CommandInterface
{
    private bool $shouldStop = false;

    /**
     * @param Closure(int): mixed|null $sleeper Sleeps for the given number of seconds; defaults to sleep()
     */
    public function __construct(
        private readonly ScheduleRunner $scheduleRunner,
        private readonly ClockInterface $clock,
        private readonly RequestStateResetter $requestStateResetter,
        private readonly ?Closure $sleeper = null,
    ) {}

    /**
     * @throws SchedulerException|Throwable
     */
    public function execute(
        Input $input,
        Output $output,
    ): int {
        $this->shouldStop = false;
        $previousAsyncSignals = $this->registerSignalHandlers();

        $output->writeLine('Running scheduled tasks every minute. Press Ctrl+C to stop.');

        try {
            $nextRun = $this->startOfNextMinute($this->clock->now());

            while (!$this->shouldStop) {
                $secondsUntilNextRun = $nextRun->getTimestamp() - $this->clock->now()->getTimestamp();

                if ($secondsUntilNextRun > 0) {
                    $this->sleep($secondsUntilNextRun);

                    continue;
                }

                $this->requestStateResetter->reset();
                $this->scheduleRunner->run($nextRun, $output);
                $nextRun = $this->startOfNextMinute($this->clock->now());
            }
        } finally {
            $this->restoreSignalHandlers($previousAsyncSignals);
        }

        $output->writeLine('Scheduler stopped.');

        return 0;
    }

    /**
     * Ask the loop to exit after the current sleep or run finishes.
     */
    public function stop(): void
    {
        $this->shouldStop = true;
    }

    private function sleep(
        int $seconds,
    ): void {
        if ($this->sleeper !== null) {
            ($this->sleeper)($seconds);

            return;
        }

        // Returns early when a signal arrives, so the loop notices stop() promptly.
        sleep($seconds);
    }

    private function startOfNextMinute(
        DateTimeImmutable $time,
    ): DateTimeImmutable {
        return $time
            ->setTime((int) $time->format('H'), (int) $time->format('i'))
            ->modify('+1 minute');
    }

    /**
     * @return bool|null The previous async-signals setting, or null when pcntl is unavailable
     */
    private function registerSignalHandlers(): ?bool
    {
        if (!function_exists('pcntl_async_signals')) {
            return null;
        }

        $previous = pcntl_async_signals(true);
        pcntl_signal(SIGINT, fn () => $this->stop());
        pcntl_signal(SIGTERM, fn () => $this->stop());

        return $previous;
    }

    private function restoreSignalHandlers(
        ?bool $previousAsyncSignals,
    ): void {
        if ($previousAsyncSignals === null) {
            return;
        }

        pcntl_signal(SIGINT, SIG_DFL);
        pcntl_signal(SIGTERM, SIG_DFL);
        pcntl_async_signals($previousAsyncSignals);
    }
}
