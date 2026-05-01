<?php

declare(strict_types=1);

namespace Marko\DevServer\Command;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\DevServer\Process\PidFile;

/** @noinspection PhpUnused */
#[Command(name: 'dev:status', description: 'Show development environment status', aliases: ['status'])]
readonly class DevStatusCommand implements CommandInterface
{
    public function __construct(
        private PidFile $pidFile,
    ) {}

    public function execute(
        Input $input,
        Output $output,
    ): int {
        $entries = $this->pidFile->read();

        if ($entries === []) {
            $output->writeLine('No development services running.');

            return 0;
        }

        $output->writeLine(
            str_pad('NAME', 12) . str_pad('PID', 8) . str_pad('STATUS', 10) . str_pad('PORT', 8) . 'STARTED',
        );
        $output->writeLine(str_repeat('-', 60));

        foreach ($entries as $entry) {
            $status = $this->pidFile->isRunning($entry->pid) ? 'running' : 'stopped';
            $port = $entry->port > 0 ? (string) $entry->port : '-';
            $output->writeLine(
                str_pad($entry->name, 12) .
                str_pad((string) $entry->pid, 8) .
                str_pad($status, 10) .
                str_pad($port, 8) .
                $entry->startedAt,
            );
        }

        return 0;
    }
}
