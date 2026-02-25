<?php

declare(strict_types=1);

namespace Marko\DevServer\Command;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\DevServer\Process\PidFile;

/** @noinspection PhpUnused */
#[Command(name: 'dev:down', description: 'Stop the development environment', aliases: ['down'])]
readonly class DevDownCommand implements CommandInterface
{
    public function __construct(
        private PidFile $pidFile,
    ) {}

    public function execute(Input $input, Output $output): int
    {
        $entries = $this->pidFile->read();

        if ($entries === []) {
            $output->writeLine('No development services running.');
            return 0;
        }

        foreach ($entries as $entry) {
            if ($entry->name === 'docker') {
                $downCommand = str_replace(' up -d', ' down', $entry->command);
                $output->writeLine("  Stopping Docker: $downCommand");
                exec($downCommand);
            } elseif ($this->pidFile->isRunning($entry->pid)) {
                $output->writeLine("  Stopping $entry->name (PID $entry->pid)...");
                if (function_exists('posix_kill')) {
                    posix_kill($entry->pid, 15);
                }
            } else {
                $output->writeLine("  $entry->name (PID $entry->pid) already stopped.");
            }
        }

        $this->pidFile->clear();
        $output->writeLine('Development environment stopped.');

        return 0;
    }
}
