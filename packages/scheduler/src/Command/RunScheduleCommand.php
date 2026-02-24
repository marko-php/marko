<?php

declare(strict_types=1);

namespace Marko\Scheduler\Command;

use DateTimeImmutable;
use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Scheduler\Schedule;
use Throwable;

/** @noinspection PhpUnused */
#[Command(name: 'schedule:run', description: 'Run due scheduled tasks')]
class RunScheduleCommand implements CommandInterface
{
    public function __construct(
        private readonly Schedule $schedule,
    ) {}

    public function execute(
        Input $input,
        Output $output,
    ): int {
        $now = new DateTimeImmutable();
        $dueTasks = $this->schedule->dueTasksAt($now);

        if ($dueTasks === []) {
            $output->writeLine('No scheduled tasks are due.');

            return 0;
        }

        $executed = 0;
        foreach ($dueTasks as $task) {
            $description = $task->getDescription() ?? 'Task ' . ($executed + 1);
            try {
                $task->run();
                $output->writeLine("Executed: $description");
                $executed++;
            } catch (Throwable $e) {
                $output->writeLine("Failed: $description - " . $e->getMessage());
            }
        }

        $output->writeLine("Executed $executed scheduled tasks.");

        return 0;
    }
}
